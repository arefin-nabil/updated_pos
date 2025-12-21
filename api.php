<?php
// api.php
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Ensure JSON response
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'search_products') {
        $term = clean_input($_GET['term'] ?? '');
        // Search by Name or Barcode
        $stmt = $pdo->prepare("SELECT * FROM products WHERE (name LIKE :s OR barcode LIKE :s) AND stock_qty > 0 LIMIT 20");
        $stmt->execute(['s' => "%$term%"]);
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $products]);

    } elseif ($action === 'get_product_by_barcode') {
        $barcode = clean_input($_GET['barcode'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = :b");
        $stmt->execute(['b' => $barcode]);
        $product = $stmt->fetch();
        if ($product) {
            echo json_encode(['success' => true, 'found' => true, 'data' => $product]);
        } else {
            echo json_encode(['success' => true, 'found' => false]);
        }

    } elseif ($action === 'search_customers') {
        $term = clean_input($_GET['term'] ?? '');
        // Search by Mobile, Name, or BeetechID
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE mobile LIKE :s OR name LIKE :s OR beetech_id LIKE :s LIMIT 10");
        $stmt->execute(['s' => "%$term%"]);
        $customers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $customers]);

    } elseif ($action === 'checkout') {
        // Read JSON Input
        $input = json_decode(file_get_contents('php://input'), true);
        
        $customer_id = $input['customer_id'] ?? null;
        $cart = $input['cart'] ?? [];
        
        if (empty($customer_id)) {
            throw new Exception("Customer is required.");
        }
        if (empty($cart)) {
            throw new Exception("Cart is empty.");
        }

        $pdo->beginTransaction();

        // 1. Calculate Totals & Validate Stock
        $total_amount = 0;
        $total_profit = 0;
        
        // Prepare IDs for fetching fresh data to ensure valid prices/stock
        // But for simplicity/performance in this scope, we can iterate and query.
        
        $sale_items_data = [];

        foreach ($cart as $item) {
            $p_id = $item['id'];
            $qty = $item['qty'];

            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $p_id]);
            $product_db = $stmt->fetch();

            if (!$product_db) {
                throw new Exception("Product ID {$p_id} not found.");
            }
            if ($product_db['stock_qty'] < $qty) {
                throw new Exception("Insufficient stock for {$product_db['name']}. Available: {$product_db['stock_qty']}");
            }

            // Calculations
            $buy = (float)$product_db['buy_price'];
            $sell = (float)$product_db['sell_price'];
            
            $subtotal = $sell * $qty;
            $total_amount += $subtotal;
            
            // Profit for this item
            $item_profit = ($sell - $buy) * $qty;
            $total_profit += $item_profit;

            // Prepare item data for insertion
            $sale_items_data[] = [
                'product_id' => $p_id,
                'qty' => $qty,
                'buy' => $buy,
                'sell' => $sell,
                'subtotal' => $subtotal
            ];

            // Deduct Stock
            $new_stock = $product_db['stock_qty'] - $qty;
            $upd = $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
            $upd->execute([$new_stock, $p_id]);
        }

        // 2. Beetech Rules
        // Profit = Sell - Buy (Already calculated as $total_profit)
        // 50% of Profit = Beetech Discount
        $beetech_discount = $total_profit * 0.50;
        
        // Point Conversion: 6 TK = 1 Point (from discount amount)
        // "6 TK = 1 Beetech Point" -> Is it 6tk of SALE or 6tk of DISCOUNT?
        // "50% = 6 TK -> Customer earns 1 point" (Example says Profit 12, 50% is 6, Points is 1. So 6tk Discount = 1 Point)
        $points_earned = floor($beetech_discount / 6);

        // 3. Create Invoice
        $invoice_no = 'INV-' . strtoupper(generate_random_string(6)); // Or sequential
        // Sequential is better for POS usually, let's try MAX id + 1 technique or just timestamp + random
        $invoice_no = date('YmdHis') . rand(10,99);

        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, user_id, total_amount, final_discount_amount, points_earned, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $invoice_no,
            $customer_id,
            $_SESSION['user_id'],
            $total_amount,
            $beetech_discount,
            $points_earned
        ]);
        $sale_id = $pdo->lastInsertId();

        // 4. Insert Items
        $stmt_item = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_buy_price, unit_sell_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($sale_items_data as $si) {
            $stmt_item->execute([
                $sale_id,
                $si['product_id'],
                $si['qty'],
                $si['buy'],
                $si['sell'],
                $si['subtotal']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'invoice_no' => $invoice_no]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
