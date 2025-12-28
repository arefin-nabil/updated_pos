<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Redirect to login if not logged in
require_login();

$user_role = $_SESSION['role'] ?? 'salesman';
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar glass-panel border-0 m-0 rounded-0">
        <div class="mb-5 px-2">
            <a href="dashboard.php" class="text-decoration-none">
                <h4 class="fw-bold text-primary"><i class="fas fa-shopping-bag me-2"></i><?php echo APP_NAME; ?></h4>
            </a>
        </div>

        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link <?php echo (($current_page ?? '') == 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <a href="pos.php" class="nav-link <?php echo (($current_page ?? '') == 'pos') ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i> POS Terminal
            </a>

            <a href="customers.php" class="nav-link <?php echo (($current_page ?? '') == 'customers') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Customers
            </a>

            <a href="sales.php" class="nav-link <?php echo (($current_page ?? '') == 'sales') ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Sales History
            </a>

            <a href="reports.php" class="nav-link <?php echo (($current_page ?? '') == 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Reports
            </a>

            <?php if(is_admin()): ?>
            <div class="my-3 px-3 text-uppercase text-xs text-secondary fw-bold" style="font-size: 0.75rem;">Admin</div>
            
            <a href="expenses.php" class="nav-link <?php echo (($current_page ?? '') == 'expenses') ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> Expenses
            </a>

            <a href="products.php" class="nav-link <?php echo (($current_page ?? '') == 'products') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i> Products
            </a>
            
             <a href="users.php" class="nav-link <?php echo (($current_page ?? '') == 'users') ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Users
            </a>

            <a href="barcode_print.php" class="nav-link <?php echo (($current_page ?? '') == 'barcode_print') ? 'active' : ''; ?>">
                <i class="fas fa-barcode"></i> Barcode Print
            </a>

            <!-- Future: Settings -->
            <?php endif; ?>

            <a href="logout.php" class="nav-link text-danger mt-5">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <div class="mt-auto p-3 bg-light rounded-3">
            <div class="d-flex align-items-center">
                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div style="font-size: 0.85rem;">
                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div class="text-secondary" style="font-size: 0.75rem;"><?php echo ucfirst($user_role); ?></div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="main-content flex-grow-1">
