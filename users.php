<?php
// users.php
require_once 'config.php';
require_once 'includes/db_connect.php';

$current_page = 'users';
require_once 'includes/header.php';

// Only Admin can access
require_admin();

$success_msg = '';
$error_msg = '';

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $username = clean_input($_POST['username'] ?? '');
    $full_name = clean_input($_POST['full_name'] ?? '');
    $role = clean_input($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $id = $_POST['user_id'] ?? null;

    if ($action === 'add') {
        if (empty($password)) {
            $error_msg = "Password is required for new users.";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error_msg = "Username already exists.";
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name, $role]);
                    set_flash_message('success', 'User added successfully!');
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $error_msg = "Error adding user: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit' && $id) {
        try {
            if (!empty($password)) {
                // Update with password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, full_name=?, role=? WHERE id=?");
                $stmt->execute([$username, $hashed_password, $full_name, $role, $id]);
            } else {
                // Update without password
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
                $stmt->execute([$username, $full_name, $role, $id]);
            }
            set_flash_message('success', 'User updated successfully!');
            header("Location: users.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error updating user: " . $e->getMessage();
        }
    } elseif ($action === 'delete' && $id) {
         try {
            // Prevent deleting self (simple check)
            if ($id == $_SESSION['user_id']) {
                set_flash_message('error', 'You cannot delete yourself!');
                header("Location: users.php");
                exit;
            }
            // Soft Delete
            $stmt = $pdo->prepare("UPDATE users SET is_deleted=1 WHERE id=?");
            $stmt->execute([$id]);
            set_flash_message('success', 'User deleted successfully!');
            header("Location: users.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Pagination / Search
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT id, username, full_name, role, created_at FROM users WHERE is_deleted=0 AND (username LIKE :s OR full_name LIKE :s) ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':s', "%$search%");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND (username LIKE :s OR full_name LIKE :s)");
$count_stmt->execute(['s' => "%$search%"]);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary">User Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
        <i class="fas fa-plus me-2"></i> Add New User
    </button>
</div>

<?php 
if ($error_msg) echo "<div class='alert alert-danger'>$error_msg</div>";
display_flash_message(); 
?>

<div class="card glass-panel border-0">
    <div class="card-body">
        <!-- Search -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name or username..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="fw-medium"><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <?php if($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Salesman</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light text-primary me-2" 
                                onclick='editUser(<?php echo json_encode($u); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="uFullName" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="uUsername" class="form-control" required>
                    </div>

                     <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="uRole" class="form-select">
                            <option value="salesman">Salesman</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="passLabel">Password</label>
                        <input type="password" name="password" id="uPassword" class="form-control" placeholder="Leave blank to keep current password (Edit mode)">
                        <div class="form-text small text-muted">Required for new users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('userForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('modalTitle').innerText = 'Add User';
    document.getElementById('uUsername').readOnly = false;
    document.getElementById('uPassword').required = true;
    document.getElementById('passLabel').innerHTML = 'Password <span class="text-danger">*</span>';
}

function editUser(user) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = user.id;
    document.getElementById('uFullName').value = user.full_name;
    document.getElementById('uUsername').value = user.username;
    // document.getElementById('uUsername').readOnly = true; // Allow username edit? Usually yes
    document.getElementById('uRole').value = user.role;
    document.getElementById('modalTitle').innerText = 'Edit User';
    
    // Password not required for edit
    document.getElementById('uPassword').required = false;
    document.getElementById('passLabel').innerHTML = 'Password (Optional update)';
    
    var myModal = new bootstrap.Modal(document.getElementById('userModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
