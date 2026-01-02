// assets/js/pos.js

let cart = [];
let products = [];
let selectedCustomer = null;
let isManualDiscount = false;

$(document).ready(function () {
    loadProducts(''); // Initial load
    $('#productSearch').focus(); // Auto focus for immediate scanning

    // Admin Check
    if (typeof POS_CONFIG !== 'undefined' && POS_CONFIG.user_role === 'admin') {
        $('#adminOverride').show();
    }

    // Debounce search
    let debounceTimer;
    $('#productSearch').on('input', function () {
        clearTimeout(debounceTimer);
        let val = $(this).val();

        // If it looks like a barcode (numeric & long), search immediately
        if (/^\d{8,}$/.test(val)) {
            fetchProductByBarcode(val);
        } else {
            debounceTimer = setTimeout(() => {
                loadProducts(val);
            }, 300);
        }
    });

    // Press enter on search to try auto-add if only 1 result or exact barcode
    $('#productSearch').on('keypress', function (e) {
        if (e.which == 13) {
            let val = $(this).val();
            // 1. Try Product Barcode first
            $.get('api.php', { action: 'get_product_by_barcode', barcode: val }, function (res) {
                if (res.success && res.found) {
                    addToCart(res.data);
                    $('#productSearch').val('').focus();
                    $('#searchResults').hide();
                } else {
                    // 2. If not product, try Customer ID (assuming 5-7 digits)
                    if (val.length >= 5 && val.length <= 11) { // Mobile or Custom ID
                        $.get('api.php', { action: 'get_customer_by_id', id: val }, function (cRes) {
                            if (cRes.success && cRes.found) {
                                selectCustomer(cRes.data);
                                $('#productSearch').val('').focus();
                                alert('Customer Detected: ' + cRes.data.name);
                            } else {
                                alert('No product or customer found with this code.');
                            }
                        });
                    }
                }
            });
        }
    });

    // Customer Search Setup
    $('#customerSearch').on('input', function () {
        // Simple autocomplete implementation could go here
    });

    // We'll use a simple custom implementation for customer search to keep it dependency free
    // Attach a dropdown div
    var $custSearch = $('#customerSearch');
    var $dropdown = $('<div class="list-group position-absolute w-100 shadow" style="z-index: 2000; display:none; top: 100%;"></div>').insertAfter($custSearch);

    $custSearch.on('input', function () {
        let term = $(this).val();
        if (term.length < 2) {
            $dropdown.hide();
            return;
        }

        $.get('api.php', { action: 'search_customers', term: term }, function (res) {
            $dropdown.empty();
            if (res.success && res.data.length > 0) {
                res.data.forEach(c => {
                    let item = $(`<a href="#" class="list-group-item list-group-item-action">
                        <div class="fw-bold">${c.name}</div>
                        <div class="small text-muted">${c.mobile} | ID: ${c.beetech_id || 'N/A'}</div>
                    </a>`);
                    item.click(function (e) {
                        e.preventDefault();
                        selectCustomer(c);
                        $dropdown.hide();
                    });
                    $dropdown.append(item);
                });
                $dropdown.show();
            } else {
                $dropdown.hide();
            }
        });
    });

    // Click outside to close customer dropdown
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#customerSearch').length && !$(e.target).closest('.list-group').length) {
            $dropdown.hide();
        }
    });

    // Create Customer Form Handler - Delegated event
    $(document).on('submit', '#createCustomerForm', function (e) {
        e.preventDefault();

        let form = $(this);
        let btn = $('#saveCustomerBtn');
        let msg = $('#customerMsg');
        let originalText = "Create Customer";

        console.log("Create Customer Form Submitted");

        msg.addClass('d-none').removeClass('alert-success alert-danger');

        // Simple validation check
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        // Serialize
        let data = {};
        form.serializeArray().forEach(item => data[item.name] = item.value);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'api.php?action=create_customer',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function (res) {
                console.log("Response", res);
                if (res.success) {
                    msg.text(res.message).addClass('alert alert-success').removeClass('d-none');

                    setTimeout(() => {
                        // Hide modal safely
                        const modalEl = document.getElementById('createCustomerModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) {
                            modal.hide();
                        } else {
                            // Fallback if instance not found
                            const newModal = new bootstrap.Modal(modalEl);
                            newModal.hide();
                        }

                        form[0].reset();
                        msg.addClass('d-none');
                        btn.prop('disabled', false).html(originalText); // Re-enable for next time

                        // Select the new customer
                        selectCustomer(res.customer);
                    }, 1000);

                } else {
                    msg.text(res.message || 'Error creating customer').addClass('alert alert-danger').removeClass('d-none');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                msg.text('System Error: ' + error).addClass('alert alert-danger').removeClass('d-none');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Admin Override Events
    $('#manualBeetechToggle').change(function () {
        isManualDiscount = $(this).is(':checked');
        if (isManualDiscount) {
            $('#manualBeetechInputBox').slideDown();
        } else {
            $('#manualBeetechInputBox').slideUp();
            $('#manualDiscount').val(''); // clear input
        }
        renderCart(); // Re-calc points based on toggle
    });

    $('#manualDiscount').on('input', function () {
        renderCart();
    });
});

function loadProducts(term) {
    $.get('api.php', { action: 'search_products', term: term }, function (res) {
        if (res.success) {
            renderProductGrid(res.data);
        } else {
            $('#productGrid').html('<div class="col-12 text-center mt-5 text-danger">Error loading products</div>');
        }
    });
}

function fetchProductByBarcode(barcode) {
    $.get('api.php', { action: 'get_product_by_barcode', barcode: barcode }, function (res) {
        if (res.success && res.found) {
            addToCart(res.data);
            $('#productSearch').val('').focus(); // Clear and Keep Focus for next scan
        }
    });
}

function renderProductGrid(data) {
    let grid = $('#productGrid');
    grid.empty();

    if (data.length === 0) {
        grid.html('<div class="col-12 text-center mt-5 text-muted">No products found</div>');
        return;
    }

    data.forEach(p => {
        let card = $(`
            <div class="product-card" onclick='addToCart(${JSON.stringify(p)})'>
                <div class="product-img-box">
                    ${p.image ? `<img src="${p.image}" class="img-fluid">` : '<i class="fas fa-box fa-2x"></i>'}
                </div>
                <div class="product-details">
                    <div class="text-truncate fw-bold mb-1">${p.name}</div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-primary">৳${p.sell_price}</div>
                        <div class="stock-badge bg-light border">Qty: ${p.stock_qty}</div>
                    </div>
                </div>
            </div>
        `);
        grid.append(card);
    });
}

function addToCart(product) {
    // Check if already in cart
    let existing = cart.find(item => item.id == product.id);
    if (existing) {
        if (existing.qty < product.stock_qty) {
            existing.qty++;
        } else {
            alert('Max stock reached for this item');
            return;
        }
    } else {
        // Clone object to avoid ref issues
        let item = { ...product, qty: 1 };
        cart.push(item);
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id != id);
    renderCart();
}

function updateQty(id, delta) {
    let item = cart.find(item => item.id == id);
    if (item) {
        let newQty = item.qty + delta;
        if (newQty > 0 && newQty <= item.stock_qty) {
            item.qty = newQty;
        } else if (newQty > item.stock_qty) {
            alert("Insufficient stock");
        }
        renderCart();
    }
}

function renderCart() {
    let container = $('#cartItemsContainer');
    container.empty();

    if (cart.length === 0) {
        container.html(`
            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Cart is empty</p>
            </div>
        `);
        $('#checkoutBtn').prop('disabled', true);
        $('#cartTotal').text('0.00');
        $('#cartSubtotal').text('0.00');
        $('#estPoints').text('0');
        return;
    }

    let total = 0;

    // Config: 5% of purchase value
    let sharePercent = (typeof POS_CONFIG !== 'undefined') ? POS_CONFIG.profit_share_percent : 0.05;

    cart.forEach(item => {
        let lineTotal = item.sell_price * item.qty;
        total += lineTotal;

        let row = $(`
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <div class="flex-grow-1">
                    <div class="fw-bold text-truncate" style="max-width: 150px;">${item.name}</div>
                    <div class="small text-secondary">৳${item.sell_price} x ${item.qty}</div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="btn-group btn-group-sm me-2">
                        <button class="btn btn-outline-secondary px-2" onclick="updateQty(${item.id}, -1)">-</button>
                        <button class="btn btn-outline-secondary px-2" disabled>${item.qty}</button>
                        <button class="btn btn-outline-secondary px-2" onclick="updateQty(${item.id}, 1)">+</button>
                    </div>
                    <div class="fw-bold me-2" style="width: 60px; text-align: right;">${lineTotal.toFixed(2)}</div>
                    <button class="btn btn-sm text-danger" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `);
        container.append(row);
    });

    $('#cartTotal').text(total.toFixed(2));
    $('#cartSubtotal').text(total.toFixed(2));

    // Calculate Est Points
    let discountAmount = 0;

    if (isManualDiscount) {
        let manualVal = parseFloat($('#manualDiscount').val());
        if (!isNaN(manualVal) && manualVal >= 0) {
            discountAmount = manualVal;
        }
    } else {
        // Auto: 5% of total
        discountAmount = total * sharePercent;
    }

    // 6 Tk Discount = 1 Point
    let estPoints = discountAmount / 6;
    $('#estPoints').text(estPoints.toFixed(2));
    $('#estAmount').text(discountAmount.toFixed(2));

    // Enable checkout only if customer selected
    if (selectedCustomer) {
        $('#checkoutBtn').prop('disabled', false);
    } else {
        $('#checkoutBtn').prop('disabled', true);
    }
}

function selectCustomer(c) {
    selectedCustomer = c;
    $('#selectedCustomerId').val(c.id);

    $('#custName').text(c.name);
    $('#custMobile').text(c.mobile);
    $('#custBeetechId').text(c.beetech_id || 'None');

    if (c.beetech_id) {
        $('#custBeetechBadge').show();
    } else {
        $('#custBeetechBadge').hide();
    }

    $('#customerSearch').val('').parent().parent().hide(); // Hide input container
    $('#customerCard').removeClass('d-none');

    renderCart(); // To re-evaluate checkout button
}

function clearCustomer() {
    selectedCustomer = null;
    $('#selectedCustomerId').val('');
    $('#customerCard').addClass('d-none');
    $('#customerSearch').parent().parent().show(); // Show input container
    renderCart();
}


function processCheckout() {
    if (!selectedCustomer || cart.length === 0) return;

    // Open Payment Modal
    $('#payTotal').text($('#cartTotal').text());
    $('#payGiven').val('');
    $('#payChange').text('0.00');

    // Auto-focus input when modal opens
    let myModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    myModal.show();

    setTimeout(() => {
        $('#payGiven').focus();
    }, 500);
}

function calcChange() {
    let total = parseFloat($('#payTotal').text());
    let given = parseFloat($('#payGiven').val());

    if (isNaN(given)) given = 0;

    let change = given - total;
    $('#payChange').text(change.toFixed(2));

    if (change < 0) {
        $('#payChange').addClass('text-danger').removeClass('text-success');
    } else {
        $('#payChange').addClass('text-success').removeClass('text-danger');
    }
}

function confirmCheckout() {
    let given = parseFloat($('#payGiven').val());
    let total = parseFloat($('#payTotal').text());

    if (isNaN(given) || given < total) {
        if (!confirm('Cash received is less than total! Continue anyway?')) {
            return;
        }
    }

    let btn = $('#confirmPayBtn');
    let originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    let payload = {
        customer_id: selectedCustomer.id,
        cart: cart.map(i => ({ id: i.id, qty: i.qty })),
        paid_amount: given,
        change_amount: parseFloat($('#payChange').text())
    };

    // If manual discount enabled, send it
    if (isManualDiscount) {
        let manualVal = parseFloat($('#manualDiscount').val());
        if (!isNaN(manualVal) && manualVal >= 0) {
            payload.manual_discount = manualVal;
        }
    }

    $.ajax({
        url: 'api.php?action=checkout',
        type: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        success: function (res) {
            if (res.success) {
                // Success! Redirect to invoice or show success modal
                let autoPrint = $('#autoPrint').is(':checked');
                window.location.href = 'invoice.php?id=' + res.sale_id + (autoPrint ? '&autoprint=1' : '');
            } else {
                alert('Error: ' + res.message);
                btn.prop('disabled', false).html(originalText);
            }
        },
        error: function () {
            alert('System Error');
            btn.prop('disabled', false).html(originalText);
        }
    });
}
