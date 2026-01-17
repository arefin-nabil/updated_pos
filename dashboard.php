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
$stmt = $pdo->query("SELECT SUM(total_amount - final_discount_amount) FROM sales WHERE DATE(created_at) = CURDATE()");
$stats['sales_today'] = $stmt->fetchColumn() ?: 0;

// Sales Month
$stmt = $pdo->query("SELECT SUM(total_amount - final_discount_amount) FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['sales_month'] = $stmt->fetchColumn() ?: 0;

// 1. Profit (Sell - Buy) - Discounts
$stmt = $pdo->query("
    SELECT SUM(
        (SELECT SUM((si.unit_sell_price - si.unit_buy_price) * si.quantity) FROM sale_items si WHERE si.sale_id = s.id) 
        - s.final_discount_amount
    )
    FROM sales s
    WHERE DATE(s.created_at) = CURDATE()
");
$gross_profit = $stmt->fetchColumn() ?: 0;

// 2. Beetech Given (Points Value Today)
$stmt = $pdo->query("SELECT SUM(points_earned) FROM sales WHERE DATE(created_at) = CURDATE()");
$total_points = $stmt->fetchColumn() ?: 0;
$beetech_given_today = $total_points * 6;

// 3. Expenses Today
$stmt = $pdo->query("SELECT SUM(amount) FROM expenses WHERE expense_date = CURDATE()");
$expenses_today = $stmt->fetchColumn() ?: 0;

// Net Profit
$net_profit = $gross_profit - $beetech_given_today - $expenses_today;
$stats['net_profit'] = $net_profit;
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

<!-- Stats Grid - Single Row -->
<div class="row g-3 mb-4 row-cols-1 row-cols-md-5">
    <!-- 1. Sales Today -->
    <div class="col">
        <div class="stat-card h-100">
            <div class="stat-icon bg-purple-light">
                <i class="fas fa-coins"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Sales Today</h6>
            <h3 class="fw-bold mb-0"><?php echo format_money($stats['sales_today']); ?></h3>
        </div>
    </div>

    <!-- 2. Gross Profit Today (100%) -->
    <div class="col">
        <div class="stat-card h-100">
            <div class="stat-icon bg-blue-light">
                <i class="fas fa-chart-line"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Gross Profit Today</h6>
            <h3 class="fw-bold mb-0 text-success"><?php echo format_money($gross_profit); ?></h3>
        </div>
    </div>

    <!-- 3. Net Profit Today -->
    <div class="col">
        <div class="stat-card h-100">
            <div class="stat-icon bg-success text-white" style="opacity: 0.8;">
                <i class="fas fa-wallet"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Net Profit Today</h6>
            <h3 class="fw-bold mb-0 <?php echo ($net_profit >= 0) ? 'text-success' : 'text-danger'; ?>">
                <?php echo format_money($net_profit); ?>
            </h3>
        </div>
    </div>

    <!-- 4. Beetech Given (Today) -->
    <div class="col">
        <div class="stat-card h-100">
            <div class="stat-icon bg-green-light">
                <i class="fas fa-gift"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Beetech Given Today</h6>
            <h3 class="fw-bold mb-0"><?php echo format_money($beetech_given_today); ?></h3>
        </div>
    </div>

    <!-- 5. Low Stock -->
    <div class="col">
        <div class="stat-card h-100 border <?php echo ($stats['low_stock'] > 0) ? 'border-danger' : ''; ?>">
            <div class="stat-icon bg-orange-light">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h6 class="text-secondary text-uppercase small fw-bold">Low Stock</h6>
            <h3 class="fw-bold mb-0 <?php echo ($stats['low_stock'] > 0) ? 'text-danger' : ''; ?>">
                <?php echo $stats['low_stock']; ?>
            </h3>
        </div>
    </div>
</div>

<?php
// Fetch Recent Sales (Fix: This was missing, causing "No sales yet")
$stmt = $pdo->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 5");
$recent_sales = $stmt->fetchAll();

// Recent Expenses
$stmt = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 5");
$recent_expenses = $stmt->fetchAll();
?>

<!-- Main Content Grid -->
<div class="row g-4 mb-4">
    <!-- Left Column: Recent Sales & Expenses -->
    <div class="col-lg-8">
        <!-- Recent Sales -->
        <div class="card glass-panel border-0 mb-4">
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
                                <th>Date</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Beetech Bal</th>
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
                                    <td class="small text-secondary"><?php echo date('d M, h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td class="fw-bold text-end"><?php echo format_money($sale['total_amount'] - $sale['final_discount_amount']); ?></td>
                                    <td class="text-end text-success">
                                        <?php 
                                            // Show Beetech Balance (Points * 6)
                                            echo format_money($sale['points_earned'] * 6);
                                        ?>
                                    </td>
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

        <!-- Recent Expenses -->
        <div class="card glass-panel border-0 mb-4">
            <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-danger"><i class="fas fa-file-invoice-dollar me-2"></i>Recent Expenses</h5>
                <a href="expenses.php" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Title</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_expenses)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No expenses recorded</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_expenses as $ex): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?php echo htmlspecialchars($ex['title']); ?></td>
                                    <td class="small text-secondary"><?php echo date('d M Y', strtotime($ex['expense_date'])); ?></td>
                                    <td class="fw-bold text-end text-danger"><?php echo format_money($ex['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Low Stock -->
    <div class="col-lg-4">
        <?php if($stats['low_stock'] > 0): ?>
        <div class="card glass-panel border-0 mb-4">
             <div class="card-header bg-transparent border-0 py-3">
                <h5 class="fw-bold mb-0 text-warning"><i class="fas fa-exclamation-circle me-2"></i>Low Stock Alert</h5>
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
                            $stmt = $pdo->query("SELECT * FROM products WHERE stock_qty <= alert_threshold AND is_deleted=0 ORDER BY stock_qty ASC LIMIT 10");
                            while($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-medium text-truncate" style="max-width: 140px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="small font-monospace text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['barcode']); ?></div>
                                </td>
                                <td><span class="badge bg-danger rounded-pill"><?php echo $row['stock_qty']; ?></span></td>
                                <td>
                                    <a href="products.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card glass-panel border-0 mb-4 bg-success text-white">
            <div class="card-body text-center py-5">
                <i class="fas fa-check-circle fa-3x mb-3 text-white-50"></i>
                <h5>All Stoked Up!</h5>
                <p class="small text-white-50 mb-0">No items are currently low on stock.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
