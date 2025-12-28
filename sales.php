<?php
// sales.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'sales';
require_once 'includes/header.php';

// Handle Points Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_points'])) {
    $sale_ids = $_POST['sale_ids'] ?? [];
    if (!empty($sale_ids)) {
        // Only update to 1. Cannot revert to 0 once 1.
        $placeholders = str_repeat('?,', count($sale_ids) - 1) . '?';
        $sql = "UPDATE sales SET points_given = 1 WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sale_ids);
        set_flash_message('success', count($sale_ids) . ' Sales marked as Points Given.');
        header("Location: sales.php");
        exit;
    }
}

// Search
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // More rows for sales
$offset = ($page - 1) * $limit;

// Query with User and Customer joins
$sql = "SELECT s.*, c.name as customer_name, c.beetech_id, u.username as cashier_name,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.invoice_no LIKE :s OR c.name LIKE :s OR c.beetech_id LIKE :s
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':s', "%$search%");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sales = $stmt->fetchAll();

// Total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.invoice_no LIKE :s OR c.name LIKE :s");
$count_stmt->execute(['s' => "%$search%"]);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary">Sales History</h2>
</div>

<?php display_flash_message(); ?>

<div class="card glass-panel border-0">
    <div class="card-body">
        <!-- Instant Search -->
        <div class="mb-4">
             <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" id="searchSalesInput" class="form-control border-start-0 ps-0" placeholder="Search Invoice, Customer, Mobile..." autocomplete="off">
            </div>
        </div>

        <form method="POST" id="pointsForm">
            <input type="hidden" name="update_points" value="1">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Salesman</th>
                            <th>Customer</th>
                            <th>BeetechID</th>
                            <th>Items</th>
                            <th>Total (<?php echo CURRENCY; ?>)</th>
                            <th>Discount Amt</th>
                            <th>Points Won</th>
                            <th>Point Given?</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody">
                        <?php foreach($sales as $s): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary">
                                <a href="invoice.php?id=<?php echo $s['id']; ?>" target="_blank" class="text-decoration-none">
                                    <?php echo $s['invoice_no']; ?>
                                </a>
                            </td>
                            <td class="small text-secondary"><?php echo date('d M Y, h:i A', strtotime($s['created_at'])); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo ucfirst($s['cashier_name'] ?? 'Unknown'); ?></span></td>
                            <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                            <td>
                                 <?php if(!empty($s['beetech_id'])): ?>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($s['beetech_id']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $s['item_count']; ?></td>
                            <td class="fw-bold"><?php echo format_money($s['total_amount']); ?></td>
                            <td class="text-secondary"><?php echo format_money($s['final_discount_amount']); ?></td>
                            <td class="fw-bold text-success"><?php echo number_format($s['points_earned'], 2); ?> Pts</td>
                            
                             <!-- Point Given Logic -->
                            <td class="text-center">
                                <?php if($s['points_given']): ?>
                                    <i class="fas fa-check-circle text-success fs-5" title="Given"></i>
                                <?php else: ?>
                                    <div class="form-check d-flex justify-content-center">
                                        <input class="form-check-input point-checkbox" type="checkbox" name="sale_ids[]" value="<?php echo $s['id']; ?>">
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <a href="invoice.php?id=<?php echo $s['id']; ?>" target="_blank" class="btn btn-sm btn-light text-dark" title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3 p-2 bg-light rounded container-fluid">
                <button type="submit" class="btn btn-success" id="submitPointsBtn" disabled>
                    <i class="fas fa-check-double me-2"></i> Mark Selected as Given
                </button>
            </div>
        </form>

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


<script>
$(document).ready(function() {
    let debounceTimer;
    
    // Existing checkbox logic
    $(document).on('change', '.point-checkbox', function() {
        const anyChecked = $('.point-checkbox:checked').length > 0;
        $('#submitPointsBtn').prop('disabled', !anyChecked);
    });

    $('#searchSalesInput').on('input', function() {
        clearTimeout(debounceTimer);
        let term = $(this).val();

        if(term.length === 0) {
           // Return to default view without reload
           $('#paginationNav').show();
        } else {
           $('#paginationNav').hide();
        }

        debounceTimer = setTimeout(function() {
            $.get('api.php', { action: 'search_sales', term: term }, function(res) {
                if(res.success) {
                    let rows = '';
                    if(res.data.length === 0) {
                         rows = '<tr><td colspan="11" class="text-center text-muted">No sales found</td></tr>';
                    } else {
                        res.data.forEach(s => {
                            let date = new Date(s.created_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                            let beetech = s.beetech_id ? `<span class="badge bg-warning text-dark">${s.beetech_id}</span>` : '-';
                            let pointAction = s.points_given == 1 ? 
                                `<i class="fas fa-check-circle text-success fs-5" title="Given"></i>` :
                                `<div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input point-checkbox" type="checkbox" name="sale_ids[]" value="${s.id}">
                                </div>`;

                            rows += `<tr>
                                <td class="font-monospace fw-bold text-primary">
                                    <a href="invoice.php?id=${s.id}" target="_blank" class="text-decoration-none">${s.invoice_no}</a>
                                </td>
                                <td class="small text-secondary">${date}</td>
                                <td><span class="badge bg-light text-dark border">${s.cashier_name || 'Unknown'}</span></td>
                                <td>${s.customer_name || ''}</td>
                                <td>${beetech}</td>
                                <td>${s.item_count}</td>
                                <td class="fw-bold">৳${parseFloat(s.total_amount).toFixed(2)}</td>
                                <td class="text-secondary">৳${parseFloat(s.final_discount_amount).toFixed(2)}</td>
                                <td class="fw-bold text-success">${parseFloat(s.points_earned).toFixed(2)} Pts</td>
                                <td class="text-center">${pointAction}</td>
                                <td>
                                    <a href="invoice.php?id=${s.id}" target="_blank" class="btn btn-sm btn-light text-dark" title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>`;
                        });
                    }
                    $('#salesTableBody').html(rows);
                    $('#paginationNav').hide();
                }
            });
        }, 300);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
