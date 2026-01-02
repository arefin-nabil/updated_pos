<?php
// invoice.php
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

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

// Calculate Subtotal (since total_amount is final, we might want to show subtotal before discount)
// Assuming total_amount = (subtotal - discount). 
// Currently DB stores item subtotal (unit * qty). Sum of these is standard subtotal.
$calc_subtotal = 0;
foreach($items as $i) {
    $calc_subtotal += $i['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $sale['invoice_no']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto Mono', monospace;
            font-size: 13px; /* Slightly larger for clear 80mm printing */
            background: #f0f0f0;
            color: #000;
        }

        /* Screen Wrapper */
        .wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Buttons for Screen */
        .screen-actions {
            margin-bottom: 20px;
            text-align: center;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            text-decoration: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            margin: 0 5px;
            display: inline-block;
            font-family: sans-serif;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #007bff;
            color: #007bff;
        }
        .btn:hover { opacity: 0.9; }

        /* Receipt Container */
        .receipt {
            background: white;
            width: 100%;
            max-width: 78mm; /* Optimized for 80mm Paper (XP80T). Will scale for 58mm if needed. */
            padding: 5px 0;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Receipt Elements */
        .header { text-align: center; margin-bottom: 10px; }
        .store-name { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .store-info { font-size: 10px; }
        
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        .divider-solid { border-top: 1px solid #000; margin: 5px 0; }

        .meta { font-size: 10px; margin-bottom: 5px; }
        .meta-row { display: flex; justify-content: space-between; }

        /* Items Table - Flex or Grid approach usually better for responsiveness than Table */
        .items-header { 
            display: flex; 
            font-weight: bold; 
            font-size: 10px; 
            border-bottom: 1px solid #000; 
            padding-bottom: 2px;
            margin-bottom: 5px;
        }
        .item-row { 
            margin-bottom: 4px; 
            font-size: 11px;
        }
        .item-name { 
            font-weight: 500; 
            margin-bottom: 1px;
        }
        .item-data { 
            display: flex; 
            justify-content: space-between; 
            font-size: 10px;
        }

        .totals { 
            margin-top: 10px; 
            text-align: right; 
            font-size: 11px;
        }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .grand-total { font-weight: bold; font-size: 14px; margin-top: 5px; }

        .beetech {
            margin-top: 10px;
            text-align: center;
            border: 1px solid #000;
            padding: 5px;
            font-size: 10px;
        }
        .beetech-pts { font-size: 14px; font-weight: bold; }

        .footer { text-align: center; margin-top: 15px; font-size: 10px; }

        /* Print Media Query */
        @media print {
            @page {
                size: auto; /* Auto height */
                margin: 0;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            .wrapper {
                display: block;
                padding: 0;
                margin: 0;
                min-height: auto;
            }
            .screen-actions, .no-print {
                display: none;
            }
            .receipt {
                width: 100%;
                max-width: 100%; /* Full width of paper */
                box-shadow: none;
                padding: 0 2px; /* Safety margin */
                margin: 0;
            }
            .header {
                margin-top: 5px; /* Minimal top margin */
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Screen Only Actions -->
    <div class="screen-actions no-print">
        <button onclick="window.print()" class="btn">Print Receipt</button>
        <a href="pos.php" class="btn btn-outline">New Sale</a>
    </div>

    <!-- Receipt Content -->
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="store-name"><?php echo APP_NAME; ?></div>
            <div class="store-info">
                Kawraid Bazar, Sreepur, Gazipur<br>
                01881196146 / 01915430867
            </div>
        </div>

        <div class="divider"></div>

        <!-- Meta -->
        <div class="meta">
            <div class="meta-row">
                <span>Inv: <?php echo $sale['invoice_no']; ?></span>
                <span><?php echo date('d-m-Y H:i', strtotime($sale['created_at'])); ?></span>
            </div>
            <div class="meta-row">
                <span>Cust: <?php echo htmlspecialchars(substr($sale['customer_name'], 0, 15)); ?></span> <!-- Truncate name -->
                <span>By: <?php echo htmlspecialchars($sale['cashier']); ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Items Head -->
        <div class="items-header" style="display: flex; width: 100%; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 5px;">
            <span style="width: 5%; text-align: center;">SL</span>
            <span style="flex: 1;">Item</span>
            <span style="width: 8%; text-align: center;">Qty</span>
            <span style="width: 15%; text-align: right;">Price</span>
            <span style="width: 18%; text-align: right;">Total</span>
        </div>

        <!-- Items Loop -->
        <?php $sl = 1; foreach($items as $item): ?>
        <div class="item-row" style="display: flex; width: 100%; margin-bottom: 5px;">
            <span style="width: 5%; text-align: center;"><?php echo $sl++; ?>.</span>
            <span style="flex: 1; padding-right: 5px; word-wrap: break-word;"><?php echo htmlspecialchars($item['name']); ?></span>
            <span style="width: 8%; text-align: center; white-space: nowrap;"><?php echo $item['quantity']; ?></span>
            <span style="width: 15%; text-align: right; white-space: nowrap;"><?php echo number_format($item['unit_sell_price'], 2); ?></span>
            <span style="width: 18%; text-align: right; white-space: nowrap; font-weight: bold;"><?php echo number_format($item['subtotal'], 2); ?></span>
        </div>
        <?php endforeach; ?>

        <div class="divider-solid"></div>

        <!-- Totals -->
            <!-- Grand Total -->
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
            
            <div class="divider-solid" style="margin: 5px 0; opacity: 0.5;"></div>

             <!-- Paid & Change -->
            <div class="total-row">
                <span>Paid:</span>
                <span><?php echo number_format($sale['paid_amount'] ?? 0, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Change:</span>
                <span><?php echo number_format($sale['change_amount'] ?? 0, 2); ?></span>
            </div>

        <!-- Beetech -->
        <?php if($sale['points_earned'] > 0 || !empty($sale['beetech_id'])): ?>
        <div class="beetech">
            <div>Beetech ID: <?php echo htmlspecialchars($sale['beetech_id'] ?? '-'); ?></div>
            <div style="margin-top: 2px;">Points Earned</div>
            <div class="beetech-pts"><?php echo number_format($sale['points_earned'], 2); ?></div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            Thanks for shopping!<br>
            Sold items are not returnable.
            <br>---
        </div>
        
    </div>
</div>

<script>
// Auto print logic
<?php if(isset($_GET['autoprint'])): ?>
window.onload = function() { 
    window.print(); 
    // Optional: Focus the window after print dialog closes (browser dependent)
    window.focus();
}
<?php endif; ?>

// Keyboard shortcut: 'N' for New Sale, 'P' for Print
document.addEventListener('keydown', function(event) {
    if(event.key === 'n' || event.key === 'N') {
        window.location.href = 'pos.php';
    }
    if(event.key === 'p' || event.key === 'P') {
        window.print();
    }
});
</script>

</body>
</html>
