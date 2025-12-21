<?php
// invoice.php
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php'; // ensure login or at least session

require_login();

$id = $_GET['id'] ?? 0;

// Fetch Sale
$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.mobile, c.address, c.beetech_id, u.username as cashier 
                       FROM sales s 
                       JOIN customers c ON s.customer_id = c.id
                       JOIN users u ON s.user_id = u.id
                       WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Invoice not found.");
}

// Fetch Items
$stmt = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $sale['invoice_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #e0e0e0; font-family: 'Courier New', monospace; }
        .invoice-box {
            background: white;
            width: 148mm; /* A5 Width */
            min-height: 210mm; /* A5 Height */
            margin: 20px auto;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header-title { font-size: 1.5rem; font-weight: bold; text-align: center; margin-bottom: 5px; }
        .header-sub { text-align: center; margin-bottom: 20px; font-size: 0.9rem; }
        
        .meta-table td { padding: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .items-table th { border-bottom: 1px dashed black; text-align: left; padding: 5px 0; }
        .items-table td { padding: 5px 0; }
        .total-row td { border-top: 1px dashed black; padding-top: 10px; font-weight: bold; }
        
        .beetech-box {
            border: 2px solid #000;
            padding: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        @media print {
            body { background: white; }
            .invoice-box { width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
            .no-print { display: none !important; }
            @page { size: A5; margin: 10mm; }
        }
    </style>
</head>
<body>

<div class="container mt-4 mb-4 no-print text-center">
    <div class="card shadow-sm border-0 d-inline-block p-3">
        <h5 class="text-success mb-3"><i class="fas fa-check-circle me-2"></i>Sale Completed Successfully!</h5>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary btn-lg">
                <i class="fas fa-print me-2"></i>Print Invoice
            </button>
            <a href="pos.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-cash-register me-2"></i>New Sale
            </a>
            <a href="sales.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-list me-2"></i>Sales History
            </a>
        </div>
    </div>
</div>

<div class="invoice-box">
    <div class="header-title"><?php echo APP_NAME; ?></div>
    <div class="header-sub">Dhaka, Bangladesh</div>
    
    <hr style="border-top: 1px dashed #000;">
    
    <table class="w-100 meta-table" style="font-size: 0.9rem;">
        <tr>
            <td><strong>Invoice:</strong> <?php echo $sale['invoice_no']; ?></td>
            <td class="text-end"><strong>Date:</strong> <?php echo date('d-m-Y h:i A', strtotime($sale['created_at'])); ?></td>
        </tr>
        <tr>
            <td><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?></td>
             <td class="text-end"><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['cashier']); ?></td>
        </tr>
        <tr>
             <td colspan="2"><strong>Mobile:</strong> <?php echo htmlspecialchars($sale['mobile']); ?></td>
        </tr>
        <?php if(!empty($sale['beetech_id'])): ?>
        <tr style="font-size: 1.1rem;">
             <td colspan="2" class="pt-2"><strong>BeetechID:</strong> <span style="background: #000; color: #fff; padding: 0 5px;"><?php echo htmlspecialchars($sale['beetech_id']); ?></span></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <table class="items-table">
        <thead>
            <tr>
                <th width="50%">Item</th>
                <th width="15%" class="text-center">Qty</th>
                <th width="15%" class="text-end">Price</th>
                <th width="20%" class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td class="text-center"><?php echo $item['quantity']; ?></td>
                <td class="text-end"><?php echo number_format($item['unit_sell_price'], 2); ?></td>
                <td class="text-end"><?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="2"></td>
                <td class="text-end">Total:</td>
                <td class="text-end"><?php echo CURRENCY . ' ' . number_format($sale['total_amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>
    
    <!-- Beetech Rewards Section -->
    <div class="beetech-box">
        <div style="font-size: 0.8rem; text-transform: uppercase;">You Earned Beetech Points</div>
        <div style="font-size: 2rem; font-weight: bold;"><?php echo $sale['points_earned']; ?></div>
        <div style="font-size: 0.8rem;">(Based on your purchase)</div>
    </div>
    
    <div class="text-center mt-4" style="font-size: 0.8rem;">
        <p>Thank you for shopping with us!</p>
        <p>Software Developed by <strong>Antigravity</strong></p>
    </div>
</div>

</body>
</html>
