<?php
// products.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'products';
require_once 'includes/header.php';

// Only Admin can access
require_admin();

$success_msg = '';
$error_msg = '';

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = clean_input($_POST['name']);
    $barcode = clean_input($_POST['barcode']);
    $buy_price = floatval($_POST['buy_price']);
    $sell_price = floatval($_POST['sell_price']);
    $stock_qty = intval($_POST['stock_qty']);
    $alert = intval($_POST['alert_threshold']);
    $id = $_POST['product_id'] ?? null;

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, barcode, buy_price, sell_price, stock_qty, alert_threshold) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $barcode, $buy_price, $sell_price, $stock_qty, $alert]);
            set_flash_message('success', 'Product added successfully!');
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error adding product: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && $id) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name=?, barcode=?, buy_price=?, sell_price=?, stock_qty=?, alert_threshold=? WHERE id=?");
            $stmt->execute([$name, $barcode, $buy_price, $sell_price, $stock_qty, $alert, $id]);
            set_flash_message('success', 'Product updated successfully!');
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error updating product: " . $e->getMessage();
        }
    } elseif ($action === 'delete' && $id) {
         try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$id]);
            set_flash_message('success', 'Product deleted successfully!');
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error deleting product: " . $e->getMessage();
        }
    }
}

// Pagination / Search
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM products WHERE name LIKE :s OR barcode LIKE :s ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':s', "%$search%");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE name LIKE :s OR barcode LIKE :s");
$count_stmt->execute(['s' => "%$search%"]);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary">Product Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
        <i class="fas fa-plus me-2"></i> Add New Product
    </button>
</div>

<?php display_flash_message(); ?>

<div class="card glass-panel border-0">
    <div class="card-body">
        <!-- Search -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name or barcode..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Barcode</th>
                        <th>Name</th>
                        <th>Buy Price</th>
                        <th>Sell Price</th>
                        <th>Stock</th>
                        <th>Alert</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td class="font-monospace text-secondary"><?php echo htmlspecialchars($p['barcode']); ?></td>
                        <td class="fw-medium"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo format_money($p['buy_price']); ?></td>
                        <td class="fw-bold text-success"><?php echo format_money($p['sell_price']); ?></td>
                        <td>
                            <?php if($p['stock_qty'] <= $p['alert_threshold']): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $p['stock_qty']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-success rounded-pill"><?php echo $p['stock_qty']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $p['alert_threshold']; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light text-primary me-2" 
                                onclick='editProduct(<?php echo json_encode($p); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <!-- Print Barcode Btn -->
                            <button class="btn btn-sm btn-light text-dark" onclick="printBarcode('<?php echo $p['barcode']; ?>', '<?php echo addslashes($p['name']); ?>')">
                                <i class="fas fa-barcode"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="productForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="product_id" id="productId">

                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" id="pName" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Barcode (EAN-13 Numeric)</label>
                        <div class="input-group">
                            <input type="text" name="barcode" id="pBarcode" class="form-control" required pattern="[0-9]+">
                            <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">Generate</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Buy Price</label>
                            <input type="number" step="0.01" name="buy_price" id="pBuy" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sell Price</label>
                            <input type="number" step="0.01" name="sell_price" id="pSell" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Qty</label>
                            <input type="number" name="stock_qty" id="pStock" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Low Stock Alert</label>
                            <input type="number" name="alert_threshold" id="pAlert" class="form-control" value="5">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Barcode Print Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
             <div class="modal-header">
                <h5 class="modal-title">Barcode Preview</h5>
                <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="barcodePrintArea">
                <h5 class="mb-2 fw-bold" id="bcProductName"></h5>
                <svg id="barcodeDisplay"></svg>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
function resetForm() {
    document.getElementById('productForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('productId').value = '';
    document.getElementById('modalTitle').innerText = 'Add Product';
}

function editProduct(product) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('productId').value = product.id;
    document.getElementById('pName').value = product.name;
    document.getElementById('pBarcode').value = product.barcode;
    document.getElementById('pBuy').value = product.buy_price;
    document.getElementById('pSell').value = product.sell_price;
    document.getElementById('pStock').value = product.stock_qty;
    document.getElementById('pAlert').value = product.alert_threshold;
    document.getElementById('modalTitle').innerText = 'Edit Product';
    
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}

function generateBarcode() {
    // Generate a simplified 12 digit random number + check digit (JsBarcode handles check digit automatically if we pass 12 digits, but simpler is just 13 random digits)
    // Actually EAN-13 is 12 digits + checksum. 
    var date = new Date();
    var val = Math.floor(Math.random() * 900000000000) + 100000000000;
    document.getElementById('pBarcode').value = val;
}

function printBarcode(code, name) {
    document.getElementById('bcProductName').innerText = name;
    JsBarcode("#barcodeDisplay", code, {
        format: "EAN13",
        lineColor: "#000",
        width: 2,
        height: 60,
        displayValue: true
    });
    var myModal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    myModal.show();
}
</script>

<!-- CSS for Printing Barcode Only -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #barcodePrintArea, #barcodePrintArea * {
        visibility: visible;
    }
    #barcodePrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        text-align: center;
        padding-top: 20px;
    }
    .modal-dialog {
        margin: 0;
        pointer-events: none;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
