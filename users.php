<?php 
require_once "php_backend/session.php";

requireRole(['admin']);

// Handle success/error messages from redirects
$feedbackMessage = '';
if (isset($_GET['success'])) {
    $feedbackMessage = htmlspecialchars($_GET['success']);
} elseif (isset($_GET['error'])) {
    $feedbackMessage = htmlspecialchars($_GET['error']);
}

// User list search + filters (GET so they combine in the URL).
$searchUser = trim($_GET['search-user'] ?? '');
$filterRole = $_GET['filter-role'] ?? 'all';
$filterStatus = $_GET['filter-status'] ?? 'all';
$userSort = $_GET['user-sort'] ?? 'newest';
if (!in_array($filterRole, ['all', 'admin', 'staff', 'manager'], true)) {
    $filterRole = 'all';
}
if (!in_array($filterStatus, ['all', 'active', 'disabled'], true)) {
    $filterStatus = 'all';
}
if (!in_array($userSort, ['newest', 'oldest'], true)) {
    $userSort = 'newest';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <?php require_once "php_backend/head_assets.php"; ?>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
</head>
<body>
    <?php require_once "main-sidebar.php";?>
    <div class="userspage"> 
        <div class="page-header">
            <h1>User Management</h1>
        </div>

        <div class="content-card addUser">
            <div class="card-header">
                <h2><i class="fa-solid fa-user-plus"></i> Add New User</h2>
            </div>
            <div class="card-body">
                <?php if ($feedbackMessage): ?>
                    <div class="feedback-message" style="margin-bottom: 16px; padding: 12px 16px; border-radius: var(--radius-md); background: <?= (strpos($feedbackMessage, 'success') !== false || strpos($feedbackMessage, 'added') !== false) ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)' ?>; color: <?= (strpos($feedbackMessage, 'success') !== false || strpos($feedbackMessage, 'added') !== false) ? 'var(--color-success)' : 'var(--color-danger)' ?>; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid <?= (strpos($feedbackMessage, 'success') !== false || strpos($feedbackMessage, 'added') !== false) ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= $feedbackMessage ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="php_backend/addUser.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" placeholder="Enter username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">Name</label>
                            <input type="text" id="fullname" name="fullname" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="staff">Production Staff</option>
                                <option value="manager">Inventory Manager</option>
                            </select>
                        </div>
                        <div class="form-group" style="justify-content: flex-end;">
                            <button type="submit" class="btn-primary" style="align-self: flex-start; margin-top: 24px;">Add User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div> <!--Add user END-->

        <div class="content-card systemUsers">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-users"></i> System Users</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="users.php" id="user-filter-form">
                        <input type="text" id="search-box-user" placeholder="Search username..." name="search-user" value="<?= htmlspecialchars($searchUser) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-user"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <i class="fa-solid fa-filter"></i>
                        <label for="role-filter">Role:</label>
                        <select id="role-filter" name="filter-role" onchange="document.getElementById('user-filter-form').submit()">
                            <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="manager" <?= $filterRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                        </select>
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" name="filter-status" onchange="document.getElementById('user-filter-form').submit()">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="disabled" <?= $filterStatus === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <label for="user-sort">Sort by:</label>
                        <select id="user-sort" name="user-sort" onchange="document.getElementById('user-filter-form').submit()">
                            <option value="newest" <?= $userSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $userSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        </select>
                        <?php if ($searchUser !== '' || $filterRole !== 'all' || $filterStatus !== 'all' || $userSort !== 'newest'): ?>
                        <a href="users.php" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        require_once "php_backend/db.php";

                        // Admins are hidden by default; picking the Admin role shows them.
                        $userSql = "SELECT id, role, user, name, admin, status FROM accounts WHERE 1 = 1";
                        $userParams = [];
                        if ($filterRole !== 'admin') {
                            $userSql .= " AND (admin IS NULL OR admin != 1)";
                        }
                        if ($searchUser !== '') {
                            $userSql .= " AND (user LIKE :search OR name LIKE :search)";
                            $userParams[':search'] = "%" . $searchUser . "%";
                        }
                        if ($filterRole !== 'all') {
                            $userSql .= " AND role = :role";
                            $userParams[':role'] = $filterRole;
                        }
                        if ($filterStatus !== 'all') {
                            $userSql .= " AND status = :status";
                            $userParams[':status'] = $filterStatus;
                        }
                        // id is auto-increment: newest users have the highest id.
                        $userSql .= $userSort === 'oldest' ? " ORDER BY id ASC" : " ORDER BY id DESC";
                        $userdb = $pdo->prepare($userSql);
                        foreach ($userParams as $key => $val) {
                            $userdb->bindValue($key, $val);
                        }
                        $userdb->execute();

                        $hasUsers = false;
                        while($user = $userdb->fetch(PDO::FETCH_ASSOC)) {
                            $hasUsers = true;
                        ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($user['user'])?></strong></td>
                            <td><?=htmlspecialchars($user['name'] ?? '')?></td>
                            <td><span class="badge-type"><?=htmlspecialchars(ucfirst($user['role']))?></span></td>
                            <td>
                                <span class="badge-status <?=$user['status'] == 'active' ? 'badge-completed' : 'badge-disabled'?>">
                                    <?=htmlspecialchars(ucfirst($user['status']))?>
                                </span>
                            </td>
                            <td>
                                <?php if($user['status'] == 'active'): ?>
                                <form method="POST" action="php_backend/editUsers.php" style="display: inline;">
                                    <input type="hidden" name="disable_id" value="<?=$user['id']?>">
                                    <button type="submit" class="btn-table-action" style="background: var(--color-danger);">Deactivate</button>
                                </form>
                                <?php endif; ?>
                                <?php if($user['status'] == 'disabled'): ?>
                                <form method="POST" action="php_backend/editUsers.php" style="display: inline;">
                                    <input type="hidden" name="enable_id" value="<?=$user['id']?>">
                                    <button type="submit" class="btn-table-action" style="background: var(--color-success);">Activate</button>
                                </form>                                
                                <?php endif; ?>
                                <button type="button" class="btn-table-details user-details-btn"
                                    data-id="<?=$user['id']?>"
                                    data-username="<?=htmlspecialchars($user['user'])?>"
                                    data-name="<?=htmlspecialchars($user['name'] ?? '')?>"
                                    data-role="<?=htmlspecialchars(ucfirst($user['role']))?>"
                                    data-status="<?=htmlspecialchars(ucfirst($user['status']))?>">Details</button>
                            </td>
                                
                        </tr>
                        <?php
                        }
                        if (!$hasUsers) {
                            echo "<tr><td colspan='5' style='text-align: center; color: var(--color-text-muted); padding: 24px;'>No users found</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- System users END-->

        <!-- User Details + password reset dialog -->
        <dialog id="user-diag">
            <div class="dialog-header">
                <h3><i class="fa-solid fa-circle-user"></i> User Details</h3>
            </div>
            <div class="dialog-body">
                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Username</label>
                    <div><strong id="ud-username">—</strong></div>
                </div>
                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Name</label>
                    <div id="ud-name">—</div>
                </div>
                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Role</label>
                    <div id="ud-role">—</div>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Status</label>
                    <div id="ud-status">—</div>
                </div>
                <form method="POST" action="php_backend/editUsers.php">
                    <input type="hidden" name="reset_id" id="ud-id">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="ud-password">Change password</label>
                        <div class="password-wrap" style="display:flex;align-items:center;gap:8px;">
                            <input type="password" id="ud-password" name="new_password" placeholder="Enter new password" minlength="4" required style="flex:1;">
                            <button type="button" class="btn-secondary" id="ud-toggle" style="padding:8px 12px;" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="dialog-actions">
                        <button type="button" class="btn-secondary" command="close" commandfor="user-diag">Cancel</button>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-key"></i> Reset Password</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div> <!-- Userspage END-->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const diag = document.getElementById('user-diag');
        document.querySelectorAll('.user-details-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('ud-id').value = btn.dataset.id;
                document.getElementById('ud-username').textContent = btn.dataset.username;
                document.getElementById('ud-name').textContent = btn.dataset.name || '—';
                document.getElementById('ud-role').textContent = btn.dataset.role;
                document.getElementById('ud-status').textContent = btn.dataset.status;
                const pw = document.getElementById('ud-password');
                pw.value = '';
                pw.type = 'password';
                document.querySelector('#ud-toggle i').className = 'fa-solid fa-eye';
                diag.showModal();
            });
        });
        document.getElementById('ud-toggle').addEventListener('click', () => {
            const pw = document.getElementById('ud-password');
            const icon = document.querySelector('#ud-toggle i');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                pw.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        });
    });
    </script>
</body>
</html>