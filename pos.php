<?php
// pos.php
require_once 'config.php';
$current_page = 'pos';
require_once 'includes/header.php';

// Initial fetch of products for grid (Top 20 or popular)
// We will fetch via AJAX usually, but initial load can be PHP
?>
<script>
    const POS_CONFIG = {
        profit_share_percent: 0.05, // Now used as Purchase Share (5%)
        user_role: '<?php echo $_SESSION['role'] ?? 'salesman'; ?>'
    };
</script>
<div class="pos-layout">
    <!-- Left: Product Section -->
    <div class="d-flex flex-column h-100 overflow-hidden">
        <!-- Search Bar -->
        <div class="mb-3">
             <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-primary"><i class="fas fa-search"></i></span>
                <input type="text" id="productSearch" class="form-control border-start-0" placeholder="Scan Barcode or Search Product..." autocomplete="off">
            </div>
        </div>

        <!-- Product Grid -->
        <div class="product-grid p-1" id="productGrid">
            <!-- Loading state or initial content comes here -->
            <div class="col-12 text-center mt-5 text-muted">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p class="mt-2">Loading Products...</p>
            </div>
        </div>
    </div>

    <!-- Right: Cart Section -->
    <div class="pos-cart-panel shadow-sm">
        <!-- Customer Selection -->
        <div class="p-3 border-bottom bg-light">
            <div class="mb-2">
                <label class="form-label small text-uppercase fw-bold text-secondary">Customer (Required)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-user-circle"></i></span>
                    <input type="text" id="customerSearch" class="form-control" placeholder="Search Mobile / BeetechID">
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#createCustomerModal" title="Add New Customer">
                        <i class="fas fa-plus"></i>
                    </button>
                    <input type="hidden" id="selectedCustomerId">
                </div>
            </div>
            
            <!-- Selected Customer Display -->
            <div id="customerCard" class="d-none bg-white p-2 rounded border border-primary mt-2 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold text-primary" id="custName">Name</div>
                        <div class="small text-secondary" id="custMobile">017...</div>
                        <div class="badge bg-warning text-dark mt-1" id="custBeetechBadge"><i class="fas fa-star me-1"></i> <span id="custBeetechId">--</span></div>
                    </div>
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="clearCustomer()"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>

        <!-- Cart Items -->
        <div class="cart-items" id="cartItemsContainer">
            <!-- Empty State -->
            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Cart is empty</p>
            </div>
        </div>

        <!-- Cart Summary -->
        <div class="cart-summary">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary">Subtotal</span>
                <span class="fw-bold" id="cartSubtotal">0.00</span>
            </div>
                <span class="text-secondary">Beetech Review</span>
                <small class="text-success"><i class="fas fa-gift me-1"></i> Points: <span id="estPoints">0</span></small>
            </div>
            
            <!-- Admin Override Section -->
            <div class="mb-3" id="adminOverride" style="display: none;">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="manualBeetechToggle">
                    <label class="form-check-label small text-secondary" for="manualBeetechToggle">Admin Override (Manual Discount)</label>
                </div>
                <div class="mt-2" id="manualBeetechInputBox" style="display: none;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">BDT</span>
                        <input type="number" id="manualDiscount" class="form-control" placeholder="Enter Discount Amount" min="0" step="0.01">
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                <h4 class="fw-bold mb-0 text-primary">Total</h4>
                <h3 class="fw-bold mb-0 text-primary" id="cartTotal">0.00</h3>
            </div>
            
            <button class="btn btn-primary w-100 btn-lg mt-3 rounded-pill" onclick="processCheckout()" id="checkoutBtn" disabled>
                Charge <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </div>
</div>


<!-- Customer Search Modal (Dropdown like behavior implemented in JS, but fallback modal if needed? No, inline is better for POS) -->

<!-- Create Customer Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-primary">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="customerMsg" class="alert d-none"></div>
                <form id="createCustomerForm">
                    <div class="mb-3">
                        <label class="form-label text-secondary small text-uppercase fw-bold">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small text-uppercase fw-bold">Mobile <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="mobile" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small text-uppercase fw-bold">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small text-uppercase fw-bold">Beetech ID (Optional)</label>
                        <input type="text" class="form-control" name="beetech_id">
                    </div>
                    <div class="d-grid">
                        <button type="submit" id="saveCustomerBtn" class="btn btn-primary btn-lg rounded-pill">Create Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/pos.js?v=<?php echo time(); ?>"></script>
