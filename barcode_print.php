<?php
// barcode_print.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'barcode_print';
require_once 'includes/header.php';

// Only Admin can access logic (or generic users? let's stick to admin/staff)
// require_admin(); // Maybe allow salesman to print labels too? Let's keep it open for logged in users.
require_login();

// Handle Bulk Add from Products Page
$initial_queue = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_print'])) {
    $selected_ids = $_POST['selected_products'] ?? [];
    if (!empty($selected_ids)) {
        // Fetch details
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, name, barcode, sell_price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($selected_ids);
        $products = $stmt->fetchAll();
        
        foreach ($products as $p) {
            $initial_queue[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'barcode' => $p['barcode'],
                'price' => $p['sell_price'],
                'count' => 1
            ];
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h2 class="fw-bold text-primary">Barcode Label Printer</h2>
    <button class="btn btn-outline-secondary" onclick="window.location.href='products.php'"><i class="fas fa-arrow-left me-2"></i> Back to Products</button>
</div>

<div class="row no-print">
    <div class="col-md-5">
        <div class="card glass-panel border-0 mb-4">
            <div class="card-header bg-transparent border-bottom-0 fw-bold">Add Products</div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    <input type="text" id="productSearch" class="form-control" placeholder="Search product by name or barcode..." autocomplete="off">
                </div>
                <!-- Added bg-white and higher z-index -->
                <div id="searchResults" class="list-group shadow-lg" style="display:none; position: absolute; width: 90%; z-index: 2000; background: white; max-height: 300px; overflow-y: auto;"></div>
                
                <div class="text-muted small mt-2">
                    <i class="fas fa-info-circle me-1"></i> Type name to search or scan barcode to add automatically.
                </div>
            </div>
        </div>
        
        <div class="card glass-panel border-0">
             <div class="card-header bg-transparent border-bottom-0 fw-bold">Print Settings</div>
             <div class="card-body">
                 <div class="mb-3">
                     <label class="form-label">Paper Size</label>
                     <select class="form-select" disabled>
                         <option>A4 (Default)</option>
                     </select>
                 </div>
                 <div class="form-check form-switch mb-3">
                      <input class="form-check-input" type="checkbox" id="showPrice" checked>
                      <label class="form-check-label" for="showPrice">Show Price on Label</label>
                 </div>
                 <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="showName" checked>
                      <label class="form-check-label" for="showName">Show Product Name</label>
                 </div>
             </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card glass-panel border-0 h-100">
            <div class="card-header bg-transparent border-bottom-0 d-flex justify-content-between align-items-center">
                <span class="fw-bold">Label Queue</span>
                <button class="btn btn-sm btn-danger" onclick="clearQueue()">Clear All</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Product</th>
                                <th style="width: 120px;">Qty Labels</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="labelQueue">
                            <!-- Items go here -->
                        </tbody>
                    </table>
                </div>
                <div id="emptyMsg" class="text-center py-5 text-muted">
                    <i class="fas fa-barcode fa-3x mb-3 opacity-25"></i>
                    <p>No items in print queue</p>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0 text-end p-3">
                <button class="btn btn-primary btn-lg w-100" onclick="generatePreview()">
                    <i class="fas fa-print me-2"></i> Generate & Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Area (Hidden until print) -->
<div id="printArea" class="d-none">
    <!-- Grid of labels generated here -->
</div>



<style>
/* A4 Print Layout */
@media print {
    body {
        background: white;
    }
    .no-print, .navbar, .sidebar, .card, .btn {
        display: none !important;
    }
    
    /* Ensure only print area is visible and takes up space */
    body * {
        visibility: hidden;
    }
    #printArea, #printArea * {
        visibility: visible;
    }
    
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        display: grid !important;
        grid-template-columns: repeat(4, 1fr); /* 4 columns for A4 */
        gap: 15px; 
        padding: 20px;
    }
    
    .barcode-label {
        border: 1px dotted #999;
        padding: 2px; /* Reduced padding */
        text-align: center;
        page-break-inside: avoid;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 80px; /* Reduced min-height */
        break-inside: avoid;
    }
    
    @page {
        size: A4;
        margin: 5mm; /* Reduced print margin */
    }
}

.barcode-label {
    overflow: hidden;
    font-family: sans-serif;
}
.label-name {
    font-size: 10px; /* Smaller font */
    font-weight: bold;
    max-width: 100%;
    margin-bottom: 0;
    line-height: 1.1;
}
.label-shop {
    font-size: 8px;
    text-transform: uppercase;
    margin-bottom: 1px;
    font-weight: bold;
}
.label-price {
    font-size: 12px;
    margin-top: 0;
    font-weight: bold;
}
</style>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
let queue = <?php echo !empty($initial_queue) ? json_encode($initial_queue) : '[]'; ?>;
console.log("Initial Queue:", queue);

$(document).ready(function() {
    renderQueue(); // Render initial if any
    // Product Search
    // Debounce Timer
    let debounceTimer;

    $('#productSearch').on('input', function() {
        let term = $(this).val();
        clearTimeout(debounceTimer); // Clear previous timer

        if(term.length < 2) {
            $('#searchResults').hide(); 
            return;
        }

        debounceTimer = setTimeout(() => {
            console.log("Searching for:", term);
            $.get('api.php', { action: 'search_products', term: term }, function(res) {
                console.log("Search Result:", res);
                let list = $('#searchResults');
                list.empty();
                
                if(res.success && res.data && res.data.length > 0) {
                    res.data.forEach(p => {
                        let item = $(`<a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <strong>${p.name}</strong>
                                <small>${p.barcode}</small>
                            </div>
                        </a>`);
                        item.click(function(e) {
                             e.preventDefault();
                             console.log("Clicked item:", p);
                             addToQueue(p);
                             $('#productSearch').val('').focus();
                             list.hide();
                        });
                        list.append(item);
                    });
                    list.show();
                } else {
                    list.hide();
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("API Error:", textStatus, errorThrown);
            });
        }, 300); // 300ms delay
    });

    // Enter key to add top result or barcode match
    $('#productSearch').on('keypress', function(e) {
        if(e.which == 13) {
            let term = $(this).val();
            // Try explicit barcode fetch
             $.get('api.php', { action: 'get_product_by_barcode', barcode: term }, function(res) {
                 if(res.success && res.found) {
                     addToQueue(res.data);
                     $('#productSearch').val('').focus();
                     $('#searchResults').hide();
                 }
             });
        }
    });
});

function addToQueue(product) {
    // Check if exists
    let existing = queue.find(i => i.id == product.id);
    if(existing) {
        // Just flash the row or increment? specific request says "select items... in wanted number". 
        // User might want to specific number, so maybe just increment default to 1?
        // Let's just highlight it or increment to be helpful
        // existing.count++; // Maybe better not to force increment if they set it.
        // renderQueue();
         alert('Product is already in the queue');
    } else {
        queue.push({
            id: product.id,
            name: product.name,
            barcode: product.barcode,
            price: product.sell_price,
            count: 1 // Default 1 label
        });
        renderQueue();
    }
}

function removeFromQueue(index) {
    queue.splice(index, 1);
    renderQueue();
}

function updateCount(index, val) {
    let count = parseInt(val);
    if(isNaN(count) || count < 1) count = 1;
    console.log(`Updating item ${index} count to ${count}`);
    queue[index].count = count;
}

function clearQueue() {
    queue = [];
    renderQueue();
}

function renderQueue() {
    let tbody = $('#labelQueue');
    tbody.empty();
    
    if(queue.length === 0) {
        $('#emptyMsg').show();
        return;
    }
    $('#emptyMsg').hide();

    queue.forEach((item, index) => {
        let row = $(`
            <tr>
                <td>
                    <div class="fw-bold">${item.name}</div>
                    <div class="small text-muted">${item.barcode}</div>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" value="${item.count}" min="1" oninput="updateCount(${index}, this.value)">
                </td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger border-0" onclick="removeFromQueue(${index})"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `);
        tbody.append(row);
    });
}

function generatePreview() {
    if(queue.length === 0) return;

    let area = $('#printArea');
    area.empty();
    
    let showPrice = $('#showPrice').is(':checked');
    let showName = $('#showName').is(':checked');

    queue.forEach(item => {
        console.log(`Generating ${item.count} labels for ${item.name}`);
        for(let i=0; i < item.count; i++) {
            // Create Label Element
            let label = $(`
                <div class="barcode-label">
                    <div class="label-shop"><?php echo APP_NAME; ?></div>
                    ${showName ? `<div class="label-name text-truncate">${item.name}</div>` : ''}
                    <svg class="barcode-svg" jsbarcode-value="${item.barcode}" jsbarcode-format="EAN13" jsbarcode-width="1.4" jsbarcode-height="30" jsbarcode-fontSize="10" jsbarcode-margin="2"></svg>
                    ${showPrice ? `<div class="label-price fw-bold text-center">` + "<?php echo CURRENCY; ?>" + item.price + `</div>` : ''}
                </div>
            `);
            area.append(label);
        }
    });
    
    // Initialize Barcodes with safety
    try {
        JsBarcode(".barcode-svg").init();
    } catch(e) {
        console.error("Barcode rendering error", e);
    }
    
    // Trigger Print after short delay to ensure rendering
    setTimeout(() => {
        window.print();
    }, 500);
}

</script>
