<?php
// dashboard.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'dashboard';
require_once 'includes/header.php';

// Fetch stats
$stats = [
    'sales_today' => 0,
    'sales_month' => 0,
    'low_stock' => 0,
    'total_products' => 0,
    'total_customers' => 0
];

// Total Products
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$stats['total_products'] = $stmt->fetchColumn();

// Low Stock
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= alert_threshold");
$stats['low_stock'] = $stmt->fetchColumn();

// Total Customers
$stmt = $pdo->query("SELECT COUNT(*) FROM customers");
$stats['total_customers'] = $stmt->fetchColumn();

// Sales Today (Points and Discounts are separate, we track raw total here)
$stmt = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE()");
$stats['sales_today'] = $stmt->fetchColumn() ?: 0;

// Sales Month
$stmt = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['sales_month'] = $stmt->fetchColumn() ?: 0;

// Today's Profit (50% Share)
$stmt = $pdo->query("
    SELECT SUM((si.unit_sell_price - si.unit_buy_price) * si.quantity) 
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.created_at) = CURDATE()
");
$total_profit_today = $stmt->fetchColumn() ?: 0;
$stats['profit_share'] = $total_profit_today * 0.50;

// Recent Sales
$stmt = $pdo->query("
    SELECT s.*, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$recent_sales = $stmt->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-primary">Dashboard</h2>
        <p class="text-secondary">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
    </div>
    <div class="text-end">
        <a href="pos.php" class="btn btn-primary btn-lg shadow-sm">
            <i class="fas fa-cash-register me-2"></i> Open POS
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="row g-4 mb-4">
    <!-- Sales Today -->
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-purple-light">
                <i class="fas fa-coins"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Sales Today</h6>
            <h3 class="fw-bold mb-0"><?php echo format_money($stats['sales_today']); ?></h3>
        </div>
    </div>

    <!-- Sales Month -->
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-blue-light">
                <i class="fas fa-chart-line"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Sales This Month</h6>
            <h3 class="fw-bold mb-0"><?php echo format_money($stats['sales_month']); ?></h3>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-md-6 col-lg-3">
        <div class="stat-card border <?php echo ($stats['low_stock'] > 0) ? 'border-danger' : ''; ?>">
            <div class="stat-icon bg-orange-light">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Low Stock Alerts</h6>
            <h3 class="fw-bold mb-0 <?php echo ($stats['low_stock'] > 0) ? 'text-danger' : ''; ?>">
                <?php echo $stats['low_stock']; ?> <span class="fs-6 text-secondary fw-normal">items</span>
            </h3>
        </div>
    </div>

    <!-- Customers -->
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-green-light">
                <i class="fas fa-users"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Total Customers</h6>
            <h3 class="fw-bold mb-0"><?php echo $stats['total_customers']; ?></h3>
        </div>
    </div>

    <!-- Profit Share (50%) -->
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success text-white" style="opacity: 0.8;">
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Today's Profit (50%)</h6>
            <h3 class="fw-bold mb-0 text-success"><?php echo format_money($stats['profit_share']); ?></h3>
        </div>
    </div>
</div>

<!-- Recent Sales & Low Stock Row -->
<div class="row g-4">
    <!-- Recent Sales -->
    <div class="col-lg-8">
        <div class="card glass-panel border-0 mb-4 h-100">
            <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-history me-2"></i>Recent Sales</h5>
                <a href="sales.php" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_sales)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No sales yet</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_sales as $sale): ?>
                                <tr>
                                    <td class="ps-4 font-monospace"><?php echo $sale['invoice_no']; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                    <td class="fw-bold"><?php echo format_money($sale['total_amount']); ?></td>
                                    <td class="small text-secondary"><?php echo date('d M, h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td>
                                        <a href="invoice.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock (Moved here to share row) -->
    <div class="col-lg-4">
        <?php if($stats['low_stock'] > 0): ?>
        <div class="card glass-panel border-0 mb-4 h-100">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Low Stock</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM products WHERE stock_qty <= alert_threshold ORDER BY stock_qty ASC LIMIT 5");
                            while($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-medium text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="small text-secondary font-monospace"><?php echo htmlspecialchars($row['barcode']); ?></div>
                                </td>
                                <td><span class="badge bg-danger rounded-pill"><?php echo $row['stock_qty']; ?></span></td>
                                <td>
                                    <a href="products.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
