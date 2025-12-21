<?php
// login.php
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            // Login Success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-container">
    <div class="card login-card glass-panel border-0">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary"><?php echo APP_NAME; ?></h3>
            <p class="text-secondary">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label text-secondary small text-uppercase fw-bold">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            
            <div class="mb-4">
                <label class="form-label text-secondary small text-uppercase fw-bold">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary py-2">Sign In</button>
            </div>
        </form>
        
        <div class="mt-4 text-center text-secondary small">
            <p>Protected by secure session authentication.</p>
        </div>
    </div>
</div>

</body>
</html>
