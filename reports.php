<?php
// reports.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'reports';
require_once 'includes/header.php';

// Parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'daily'; // daily, monthly
$user_id = $_GET['user_id'] ?? '';

// Fetch Users for Filter
$users_stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
$users_list = $users_stmt->fetchAll();

// Validate dates
if ($start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// 1. Sales Data
$where_sql = "DATE(s.created_at) BETWEEN :start AND :end";
$params = ['start' => $start_date, 'end' => $end_date];

if (!empty($user_id)) {
    $where_sql .= " AND s.user_id = :uid";
    $params['uid'] = $user_id;
}

// Fixed Query for Daily Aggregates
$sql_fixed = "
    SELECT 
        date_group,
        COUNT(id) as total_invoices,
        SUM(total_amount) as revenue,
        SUM(final_discount_amount) as discount_given,
        SUM(sale_profit) as gross_profit
    FROM (
        SELECT 
            s.id,
            DATE_FORMAT(s.created_at, '" . ($report_type == 'monthly' ? '%Y-%m-01' : '%Y-%m-%d') . "') as date_group,
            s.total_amount,
            s.final_discount_amount,
            (SELECT SUM((si.unit_sell_price - si.unit_buy_price) * si.quantity) FROM sale_items si WHERE si.sale_id = s.id) as sale_profit
        FROM sales s
        WHERE $where_sql
    ) as daily_sales
    GROUP BY date_group
    ORDER BY date_group DESC
";

$stmt = $pdo->prepare($sql_fixed);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// 2. Fetch All Invoices for these dates (for expansion)
// To verify "No missing invoices", we fetch all sales in this range ordered by date
$inv_sql = "SELECT s.*, DATE_FORMAT(s.created_at, '" . ($report_type == 'monthly' ? '%Y-%m-01' : '%Y-%m-%d') . "') as date_group FROM sales s WHERE $where_sql ORDER BY s.created_at DESC";
$inv_stmt = $pdo->prepare($inv_sql);
$inv_stmt->execute($params);
$all_invoices = $inv_stmt->fetchAll(PDO::FETCH_GROUP); // Group by date_group PHP side

// 3. Expenses Data
$ex_params = ['start' => $start_date, 'end' => $end_date];
$ex_sql = "SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN :start AND :end";
$ex_stmt = $pdo->prepare($ex_sql);
$ex_stmt->execute($ex_params);
$total_period_expenses = $ex_stmt->fetchColumn() ?: 0;

// Recalculate Totals
$grand_total_revenue = 0;
$grand_total_profit = 0;
$grand_total_invoices = 0;
$grand_total_beetech = 0;

foreach ($reports as $r) {
    $grand_total_revenue += $r['revenue'];
    $grand_total_profit += $r['gross_profit'];
    $grand_total_invoices += $r['total_invoices'];
    $grand_total_beetech += $r['discount_given'];
}

// Net Profit
$grand_net_profit = $grand_total_profit - $grand_total_beetech - $total_period_expenses;

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary">Sales Reports</h2>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card glass-panel border-0">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-secondary">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-secondary">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-secondary">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="col-12 mb-4">
        <div class="row row-cols-1 row-cols-md-5 g-3">
             <!-- Revenue -->
            <div class="col">
                <div class="card bg-primary text-white border-0 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="small text-white-50 text-uppercase fw-bold">Total Revenue</div>
                        <div class="fs-4 fw-bold"><?php echo format_money($grand_total_revenue); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Invoices -->
            <div class="col">
                <div class="card bg-info text-dark border-0 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-bold">Total Invoices</div>
                        <div class="fs-4 fw-bold"><?php echo number_format($grand_total_invoices); ?></div>
                    </div>
                </div>
            </div>

            <!-- Gross Profit -->
            <div class="col">
                <div class="card bg-success text-white border-0 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="small text-white-50 text-uppercase fw-bold">Gross Profit (100%)</div>
                        <div class="fs-4 fw-bold"><?php echo format_money($grand_total_profit); ?></div>
                    </div>
                </div>
            </div>

            <!-- Beetech Given -->
            <div class="col">
                <div class="card bg-warning text-dark border-0 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="small text-muted text-uppercase fw-bold">Beetech Given</div>
                        <div class="fs-4 fw-bold"><?php echo format_money($grand_total_beetech); ?></div>
                    </div>
                </div>
            </div>

            <!-- Net Profit -->
            <div class="col">
                <div class="card bg-dark text-white border-0 h-100 shadow-sm">
                    <div class="card-body">
                        <div class="small text-white-50 text-uppercase fw-bold">Net Profit</div>
                        <div class="fs-4 fw-bold <?php echo ($grand_net_profit < 0) ? 'text-danger' : 'text-success'; ?>"><?php echo format_money($grand_net_profit); ?></div>
                        <div class="small text-white-50" style="font-size: 0.7rem;">(After Exp: <?php echo format_money($total_period_expenses); ?>)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="col-12">
        <div class="card glass-panel border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 50px;"></th> <!-- Expand Icon -->
                                <th>Date / Period</th>
                                <th class="text-center">Invoices</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Gross Profit</th>
                                <th class="text-end">Beetech Given</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reports)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No data found for selected period</td></tr>
                            <?php else: ?>
                                <?php foreach($reports as $index => $row): 
                                    $date_key = $row['date_group'];
                                    $day_invoices = $all_invoices[$date_key] ?? [];
                                ?>
                                <tr class="cursor-pointer" onclick="toggleDetails('details-<?php echo $index; ?>')">
                                    <td class="text-center text-primary"><i class="fas fa-chevron-right transition-transform" id="icon-details-<?php echo $index; ?>"></i></td>
                                    <td class="fw-bold text-primary">
                                        <?php echo date(($report_type == 'monthly' ? 'M Y' : 'd M Y'), strtotime($row['date_group'])); ?>
                                    </td>
                                    <td class="text-center"><?php echo $row['total_invoices']; ?></td>
                                    <td class="text-end fw-bold"><?php echo format_money($row['revenue']); ?></td>
                                    <td class="text-end text-success"><?php echo format_money($row['gross_profit']); ?></td>
                                    <td class="text-end text-warning"><?php echo format_money($row['discount_given']); ?></td>
                                </tr>
                                <!-- Expanded Row -->
                                <tr id="details-<?php echo $index; ?>" class="d-none bg-light">
                                    <td colspan="6" class="p-3">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body p-0">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th class="ps-3">Invoice #</th>
                                                            <th>Time</th>
                                                            <th class="text-end">Purchase Amount</th>
                                                            <th class="text-end">Discount/Points</th>
                                                            <th class="text-end pe-3">Net Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if(empty($day_invoices)): ?>
                                                            <tr><td colspan="5" class="text-center">No details available</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach($day_invoices as $inv): 
                                                                $net = $inv['total_amount'] - $inv['final_discount_amount']; // Net cash received technically? Or user meant something else. Usually Net = Total - Discount.
                                                            ?>
                                                            <tr>
                                                                <td class="ps-3 font-monospace">
                                                                    <a href="invoice.php?id=<?php echo $inv['id']; ?>" target="_blank"><?php echo $inv['invoice_no']; ?></a>
                                                                </td>
                                                                <td class="small text-secondary"><?php echo date('h:i A', strtotime($inv['created_at'])); ?></td>
                                                                <td class="text-end"><?php echo format_money($inv['total_amount']); ?></td>
                                                                <td class="text-end text-danger">-<?php echo format_money($inv['final_discount_amount']); ?></td>
                                                                <td class="text-end pe-3 fw-bold"><?php echo format_money($net); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($reports)): ?>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td></td>
                                <td>TOTAL</td>
                                <td class="text-center"><?php echo number_format($grand_total_invoices); ?></td>
                                <td class="text-end"><?php echo format_money($grand_total_revenue); ?></td>
                                <td class="text-end text-success"><?php echo format_money($grand_total_profit); ?></td>
                                <td class="text-end text-warning"><?php echo format_money($grand_total_beetech); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    
    if (row.classList.contains('d-none')) {
        row.classList.remove('d-none');
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
    } else {
        row.classList.add('d-none');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
