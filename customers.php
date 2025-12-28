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
    $beetech_id = clean_input($_POST['beetech_id']); // Can be empty if auto-generated or optional? User said Create/Edit.
    $id = $_POST['customer_id'] ?? null;

    // BeetechID optional or unique?
    // "BeetechID must be highlighted... Customer table has BeetechID"
    // Assuming it's a manual entry or generated. We will treat as string.

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, mobile, address, beetech_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $mobile, $address, $beetech_id]);
            set_flash_message('success', 'Customer added successfully!');
            header("Location: customers.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error adding customer: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && $id) {
        try {
            $stmt = $pdo->prepare("UPDATE customers SET name=?, mobile=?, address=?, beetech_id=? WHERE id=?");
            $stmt->execute([$name, $mobile, $address, $beetech_id, $id]);
            set_flash_message('success', 'Customer updated successfully!');
            header("Location: customers.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error updating customer: " . $e->getMessage();
        }
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

<?php display_flash_message(); ?>

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
                        <th>Address</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <?php foreach($customers as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td class="fw-medium"><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                        <td>
                            <?php if(!empty($c['beetech_id'])): ?>
                                <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm" style="letter-spacing: 0.5px;">
                                    <?php echo htmlspecialchars($c['beetech_id']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($c['address']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light text-primary me-2" 
                                onclick='editCustomer(<?php echo json_encode($c); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination (Hidden during search if not needed, or simple prev/next implemented later. For instant search, result list usually replaces pagination for simplicity) -->
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

<!-- Add/Edit Modal (existing code) -->


<script>
$(document).ready(function() {
    let debounceTimer;
    $('#searchInput').on('input', function() {
        clearTimeout(debounceTimer);
        let term = $(this).val();

        if(term.length === 0) {
           // If empty, just search with empty string (returns default/all) and show pagination
           // location.reload(); // BAD: Causes focus loss
           $('#paginationNav').show();
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
                        res.data.forEach(c => {
                           let beetechBadge = c.beetech_id ? 
                               `<span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm" style="letter-spacing: 0.5px;">${c.beetech_id}</span>` : 
                               '<span class="text-muted small">N/A</span>';
                            
                            // Safe json encode for attribute
                            let jsonStr = JSON.stringify(c).replace(/'/g, "&apos;");

                            rows += `<tr>
                                <td>${c.id}</td>
                                <td class="fw-medium">${c.name}</td>
                                <td>${c.mobile}</td>
                                <td>${beetechBadge}</td>
                                <td class="text-muted small text-truncate" style="max-width: 200px;">${c.address || ''}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light text-primary me-2" onclick='editCustomer(${jsonStr})'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>`;
                        });
                    }
                    $('#customersTableBody').html(rows);
                    $('#paginationNav').hide(); // Hide pagination during instant search
                }
            });
        }, 300);
    });
});
</script>

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
                        <input type="text" name="beetech_id" id="cBeetech" class="form-control" placeholder="Optional">
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

<script>
function resetForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('customerId').value = '';
    document.getElementById('modalTitle').innerText = 'Add Customer';
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
</script>

<?php require_once 'includes/footer.php'; ?>
