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
        $stmt = $pdo->prepare("SELECT * FROM products WHERE (name LIKE :s OR barcode LIKE :s) AND stock_qty > 0 AND is_deleted = 0 LIMIT 20");
        $stmt->execute(['s' => "%$term%"]);
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $products]);

    } elseif ($action === 'get_product_by_barcode') {
        $barcode = clean_input($_GET['barcode'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = :b AND is_deleted = 0");
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
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE mobile LIKE :s OR name LIKE :s OR beetech_id LIKE :s LIMIT 20");
        $stmt->execute(['s' => "%$term%"]);
        $customers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $customers]);

    } elseif ($action === 'get_customer_by_id') {
        $id = clean_input($_GET['id'] ?? '');
        // Search by BeetechID or Database ID or Mobile
        // If it's a barcode scan, it might be the Beetech ID (string)
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE beetech_id = :id OR id = :id OR mobile = :id");
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            echo json_encode(['success' => true, 'found' => true, 'data' => $customer]);
        } else {
            echo json_encode(['success' => true, 'found' => false]);
        }

    } elseif ($action === 'get_customer_history') {
        $cid = clean_input($_GET['cid'] ?? '');
        $limit = 50;
        
        $stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count 
                               FROM sales s WHERE customer_id = ? ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([$cid]);
        $sales = $stmt->fetchAll();
        
        // Calculate totals
        $stmtPath = $pdo->prepare("SELECT SUM(total_amount) as total_spend, SUM(points_earned) as total_points FROM sales WHERE customer_id = ?");
        $stmtPath->execute([$cid]);
        $totals = $stmtPath->fetch();
        
        echo json_encode([
            'success' => true, 
            'data' => $sales,
            'summary' => $totals
        ]);

    } elseif ($action === 'search_sales') {
        $term = clean_input($_GET['term'] ?? '');
        $limit = 20;
        // Search by Invoice, Customer Name, Mobile (via join?), BeetechID.
        // Joining tables is necessary.
        $sql = "SELECT s.*, c.name as customer_name, c.mobile, c.beetech_id, u.username as cashier_name,
                (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.invoice_no LIKE :s OR c.name LIKE :s OR c.mobile LIKE :s OR c.beetech_id LIKE :s
                ORDER BY s.created_at DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':s', "%$term%");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $sales = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $sales]);

    } elseif ($action === 'create_customer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = clean_input($input['name'] ?? '');
        $mobile = clean_input($input['mobile'] ?? '');
        $address = clean_input($input['address'] ?? '');
        $beetech_id = clean_input($input['beetech_id'] ?? '');

        if (empty($name) || empty($mobile)) {
            echo json_encode(['success' => false, 'message' => 'Name and Mobile are required']);
            exit;
        }

        // Check duplicate mobile
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE mobile = ?");
        $stmt->execute([$mobile]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Customer with this mobile already exists']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO customers (name, mobile, address, beetech_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt->execute([$name, $mobile, $address, $beetech_id])) {
            $id = $pdo->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Customer created successfully',
                'customer' => [
                    'id' => $id,
                    'name' => $name,
                    'mobile' => $mobile,
                    'beetech_id' => $beetech_id
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }

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

        // 2. Beetech Rules (Updated Logic)
        // New Logic: 5% of Purchase Value (Total Amount)
        // Admin Override: If 'manual_discount' is sent, use it. Otherwise auto-calc.
        
        $final_discount_amount = 0;
        $manual_discount_input = $input['manual_discount'] ?? null;

        if ($manual_discount_input !== null && is_numeric($manual_discount_input)) {
            // Admin override
            $final_discount_amount = (float)$manual_discount_input;
        } else {
            // Auto Calculation: 5% of Total Amount
            $final_discount_amount = $total_amount * 0.05;
        }
        
        // Point Conversion: 6 TK = 1 Point (from discount amount)
        $points_earned = $final_discount_amount / 6;
        $points_earned = round($points_earned, 2); 
        
        // Payment Info
        // If not sent, default to total_amount (exact change)
        $paid_amount = isset($input['paid_amount']) ? (float)$input['paid_amount'] : $total_amount;
        $change_amount = isset($input['change_amount']) ? (float)$input['change_amount'] : 0.00;

        // 3. Create Invoice
        $invoice_no = date('YmdHis') . rand(10,99);

        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, user_id, total_amount, final_discount_amount, points_earned, paid_amount, change_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $invoice_no,
            $customer_id,
            $_SESSION['user_id'],
            $total_amount,
            $final_discount_amount,
            $points_earned,
            $paid_amount,
            $change_amount
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
