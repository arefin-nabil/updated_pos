<?php
// customers.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'customers';
require_once 'includes/header.php';

$success_msg = '';
$error_msg = '';

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = clean_input($_POST['name']);
    $mobile = clean_input($_POST['mobile']);
    $address = clean_input($_POST['address']);
    // Convert empty Beetech ID to NULL to prevent unique constraint violation on empty strings
    if (empty($beetech_id)) {
        $beetech_id = null;
    }

    // Check Duplicate Mobile (if adding or if editing a different ID)
    $checkSql = "SELECT id FROM customers WHERE mobile = ?";
    $params = [$mobile];
    if ($action === 'edit' && $id) {
        $checkSql .= " AND id != ?";
        $params[] = $id;
    }
    
    $dupHeader = false;
    try {
        $stmt = $pdo->prepare($checkSql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $error_msg = "Customer with this mobile number already exists!";
        } else {
             if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO customers (name, mobile, address, beetech_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $mobile, $address, $beetech_id]);
                set_flash_message('success', 'Customer added successfully!');
                header("Location: customers.php");
                exit;
            } elseif ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE customers SET name=?, mobile=?, address=?, beetech_id=? WHERE id=?");
                $stmt->execute([$name, $mobile, $address, $beetech_id, $id]);
                set_flash_message('success', 'Customer updated successfully!');
                header("Location: customers.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$del_id]);
        set_flash_message('success', 'Customer deleted successfully!');
        header("Location: customers.php");
        exit;
    } catch (PDOException $e) {
       $error_msg = "Cannot delete customer. They might have existing sales history.";
    }
}

// Search
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search by Mobile or BeetechID
$sql = "SELECT * FROM customers WHERE mobile LIKE :s OR beetech_id LIKE :s OR name LIKE :s ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':s', "%$search%");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

// Total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE mobile LIKE :s OR beetech_id LIKE :s OR name LIKE :s");
$count_stmt->execute(['s' => "%$search%"]);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary">Customer Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
        <i class="fas fa-user-plus me-2"></i> Add Customer
    </button>
</div>

<?php 
display_flash_message(); 
if (!empty($error_msg)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($error_msg) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}
?>

<div class="card glass-panel border-0">
    <div class="card-body">
        <!-- Instant Search -->
        <div class="mb-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Type Name, Mobile, or BeetechID to search..." autocomplete="off">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>BeetechID</th>
                        <th>Spend / Points</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <?php 
                    // To show Total Spend/Points in main table effectively, we need to join.
                    // Doing a subquery join for performance optimization on small datasets
                    $ids = array_column($customers, 'id');
                    $stats = [];
                    if(!empty($ids)) {
                        $in = str_repeat('?,', count($ids) - 1) . '?';
                        $stat_sql = "SELECT customer_id, SUM(total_amount - COALESCE(final_discount_amount, 0)) as total_spend, SUM(points_earned) as total_points 
                                     FROM sales WHERE customer_id IN ($in) GROUP BY customer_id";
                        $stmt_stat = $pdo->prepare($stat_sql);
                        $stmt_stat->execute($ids);
                        while($row = $stmt_stat->fetch()) {
                            $stats[$row['customer_id']] = $row;
                        }
                    }
                    
                    foreach($customers as $index => $c): 
                            $s = $stats[$c['id']] ?? ['total_spend' => 0, 'total_points' => 0];
                    ?>
                    <tr>
                        <td><?php echo $total_rows - $offset - $index; ?></td>
                        <td class="fw-medium">
                            <?php echo htmlspecialchars($c['name']); ?>
                            <div class="small text-muted d-block d-md-none"><?php echo htmlspecialchars($c['mobile']); ?></div>
                        </td>
                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($c['mobile']); ?></td>
                        <td>
                            <?php if(!empty($c['beetech_id'])): ?>
                                <span class="badge bg-primary px-2 rounded-pill" style="letter-spacing: 0.5px;">
                                    <?php echo htmlspecialchars($c['beetech_id']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small fw-bold">৳<?php echo number_format($s['total_spend'], 2); ?></div>
                            <div class="small text-success"><i class="fas fa-star text-warning"></i> <?php echo number_format($s['total_points'], 2); ?> pts</div>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-info btn-card-customer" title="ID Card" data-customer='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="fas fa-id-card"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary btn-history-customer" title="History" data-id="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary btn-edit-customer" title="Edit" data-customer='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this customer?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav class="mt-4" id="paginationNav">
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

<!-- Customer Card Modal -->
<div class="modal fade" id="cardModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-primary">Beetech ID Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="idCardNode" class="p-4 bg-white rounded shadow-sm d-inline-block position-relative" style="width: 300px; border: 1px solid #eee;">
                    <!-- Brand -->
                    <div class="mb-3">
                        <h4 class="fw-bold text-primary mb-0"><?php echo APP_NAME; ?></h4>
                        <div class="text-xs text-muted">Arefin Nabil</div>
                    </div>
                    
                    <!-- Content -->
                    <div class="mb-3">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 64px; height: 64px; font-size: 24px;">
                            <span id="cardInitials">N</span>
                        </div>
                        <h5 class="fw-bold mb-0" id="cardName">Name</h5>
                        <div class="text-secondary small" id="cardMobile">017...</div>
                    </div>
                    
                    <!-- Barcode -->
                    <div class="my-3 text-center">
                         <svg id="cardBarcode"></svg>
                         <div class="text-xs text-muted mt-1">Scan at Checkout</div>
                    </div>
                    
                    <div class="border-top pt-2 text-xs text-secondary">
                        Beetech Membership Card
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 justify-content-center">
                <button type="button" class="btn btn-primary" onclick="downloadCard()">
                    <i class="fas fa-download me-2"></i> Download ID
                </button>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Purchase History: <span id="historyName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 text-center">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Total Spent</div>
                            <div class="h5 mb-0 fw-bold">৳<span id="histTotalSpend">0.00</span></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                             <div class="text-muted small">Total Points</div>
                            <div class="h5 mb-0 fw-bold text-success"><span id="histTotalPoints">0</span></div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Points</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="customer_id" id="customerId">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="cName" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="mobile" id="cMobile" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">BeetechID</label>
                        <div class="input-group">
                             <input type="text" name="beetech_id" id="cBeetech" class="form-control" placeholder="Optional">
                             <button type="button" class="btn btn-outline-secondary" onclick="generateRandomID()">Generate</button>
                        </div>
                        <div class="form-text">Unique ID for loyalty points.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="cAddress" class="form-control" rows="2"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JSBarcode and Html2Canvas -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script>
// ... Existing JS search logic ...
// We need to update search logic to include new columns if we want dynamic update
$(document).ready(function() {
    let debounceTimer;
    $('#searchInput').on('input', function() {
        clearTimeout(debounceTimer);
        let term = $(this).val();
        
        if(term.length === 0) {
           $('#paginationNav').show();
           // Optional: Reload page or just show current table if we stored it? 
           // Simplest: just reload or let user refresh. Or query empty term to get defaults?
           // Actually, query empty term returns first 20.
        } else {
           $('#paginationNav').hide();
        }

        debounceTimer = setTimeout(function() {
            $.get('api.php', { action: 'search_customers', term: term }, function(res) {
                if(res.success) {
                    let rows = '';
                    if(res.data.length === 0) {
                         rows = '<tr><td colspan="6" class="text-center text-muted">No customers found</td></tr>';
                    } else {
                        res.data.forEach((c, index) => {
                            // Safe JSON for onclick
                            let cJson = JSON.stringify(c).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
                            
                            let beetech = c.beetech_id ? 
                                `<span class="badge bg-primary px-2 rounded-pill" style="letter-spacing: 0.5px;">${c.beetech_id}</span>` : 
                                `<span class="text-muted small">N/A</span>`;

                            let spend = parseFloat(c.total_spend || 0).toFixed(2);
                            let points = parseFloat(c.total_points || 0).toFixed(2);

                            rows += `<tr>
                                <td>${res.data.length - index}</td>
                                <td class="fw-medium">
                                    ${c.name}
                                    <div class="small text-muted d-block d-md-none">${c.mobile}</div>
                                </td>
                                <td class="d-none d-md-table-cell">${c.mobile}</td>
                                <td>${beetech}</td>
                                <td>
                                    <div class="small fw-bold">৳${spend}</div>
                                    <div class="small text-success"><i class="fas fa-star text-warning"></i> ${points} pts</div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-info" title="ID Card" onclick='showCard(${cJson})'>
                                            <i class="fas fa-id-card"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" title="History" onclick='showHistory(${c.id}, "${c.name.replace(/'/g, "\\'")}")'>
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" title="Edit" onclick='editCustomer(${cJson})'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_id=${c.id}" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this customer?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>`;
                        });
                    }
                    $('#customersTableBody').html(rows);
                }
            });
        }, 300);
    });
    // Delegated Events for dynamic buttons (Search or Initial)
    $(document).on('click', '.btn-edit-customer', function() {
        let c = $(this).data('customer');
        editCustomer(c);
    });

    $(document).on('click', '.btn-history-customer', function() {
        let id = $(this).data('id');
        let name = $(this).data('name');
        showHistory(id, name);
    });

    $(document).on('click', '.btn-card-customer', function() {
        let c = $(this).data('customer');
        showCard(c);
    });
});



function resetForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('customerId').value = '';
    document.getElementById('modalTitle').innerText = 'Add Customer';
}

function generateRandomID() {
    let id = Math.floor(100000 + Math.random() * 900000); // 6 digits
    document.getElementById('cBeetech').value = id;
}

function editCustomer(customer) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('customerId').value = customer.id;
    document.getElementById('cName').value = customer.name;
    document.getElementById('cMobile').value = customer.mobile;
    document.getElementById('cBeetech').value = customer.beetech_id;
    document.getElementById('cAddress').value = customer.address;
    document.getElementById('modalTitle').innerText = 'Edit Customer';
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}

// Show Card
function showCard(c) {
    $('#cardName').text(c.name);
    $('#cardMobile').text(c.mobile);
    $('#cardInitials').text(c.name.charAt(0).toUpperCase());
    
    // Barcode: Use BeetechID if available, else Mobile
    let code = c.beetech_id ? c.beetech_id : c.mobile;
    
    try {
        JsBarcode("#cardBarcode", code, {
            format: "CODE128",
            width: 2,
            height: 50,
            displayValue: true
        });
    } catch(e) { console.error(e); }
    
    new bootstrap.Modal(document.getElementById('cardModal')).show();
}

function downloadCard() {
    html2canvas(document.querySelector("#idCardNode")).then(canvas => {
        let link = document.createElement('a');
        link.download = 'Beetech_Card_' + $('#cardName').text() + '.png';
        link.href = canvas.toDataURL();
        link.click();
    });
}

// Show History
function showHistory(cid, name) {
    $('#historyName').text(name);
    $('#historyBody').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
    
    new bootstrap.Modal(document.getElementById('historyModal')).show();
    
    $.get('api.php', { action: 'get_customer_history', cid: cid }, function(res) {
        if(res.success) {
            let rows = '';
            if(res.data.length === 0) {
                 rows = '<tr><td colspan="6" class="text-center text-muted">No purchase history</td></tr>';
            } else {
                res.data.forEach(s => {
                    rows += `<tr>
                        <td><a href="invoice.php?id=${s.id}" target="_blank">${s.invoice_no}</a></td>
                        <td>${new Date(s.created_at).toLocaleDateString()}</td>
                        <td>${s.item_count} Items</td>
                        <td>${parseFloat(s.total_amount).toFixed(2)}</td>
                        <td>${parseFloat(s.points_earned).toFixed(2)}</td>
                         <td><a href="invoice.php?id=${s.id}" class="btn btn-xs btn-light" target="_blank"><i class="fas fa-eye"></i></a></td>
                    </tr>`;
                });
            }
            $('#historyBody').html(rows);
            
            // Stats
            $('#histTotalSpend').text(parseFloat(res.summary.total_spend || 0).toFixed(2));
            $('#histTotalPoints').text(parseFloat(res.summary.total_points || 0).toFixed(2));
        }
    });
}



</script>

<?php require_once 'includes/footer.php'; ?>
