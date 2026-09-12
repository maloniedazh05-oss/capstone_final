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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php";?>
    <div class="userspage"> 
        <div class="addUser">
            <h2>Add User</h2>
            <?php if ($feedbackMessage): ?>
                <div class="feedback-message" style="padding: 10px; margin-bottom: 10px; background: <?= strpos($feedbackMessage, 'success') !== false || strpos($feedbackMessage, 'added') !== false ? '#d4edda' : '#f8d7da' ?>; color: <?= strpos($feedbackMessage, 'success') !== false || strpos($feedbackMessage, 'added') !== false ? '#155724' : '#721c24' ?>; border-radius: 4px;">
                    <?= $feedbackMessage ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="php_backend/addUser.php">
                <label for="username">Username</label>
                <input type="text" name="username" required>
                <br>
                <label for="password">Password</label>
                <input type="password" name="password" required>
                <br>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="staff">Production Staff</option>
                    <option value="manager">Inventory Manager</option>
                </select>
                <br>
                <button type="submit">Add User</button>
            </form>
        </div> <!--Add user END-->
        <div class="systemUsers">
            <h2>System Users</h2>     
            <?php 
            require_once "php_backend/db.php";

            $userdb = $pdo->prepare("SELECT id, role, user, admin, status FROM accounts");
            $userdb->execute();

            echo "<table>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            ";

            $hasUsers = false;
            while($user = $userdb->fetch(PDO::FETCH_ASSOC)) {
                if($user['admin'] != 1) {
                    $hasUsers = true;
            ?>
            <tr>
                <td><?=$user['user']?></td>
                <td><?=$user['role']?></td>
                <td><?=$user['status']?></td>
                <td>
                    <?php if($user['status'] == 'active'): ?>
                    <form method="POST" action="php_backend/editUsers.php">
                        <input type="hidden" name="disable_id" value="<?=$user['id']?>">
                        <button>Deactivate</button>
                    </form>
                    <?php endif; ?>
                    <?php if($user['status'] == 'disabled'): ?>
                    <form method="POST" action="php_backend/editUsers.php">
                        <input type="hidden" name="enable_id" value="<?=$user['id']?>">
                        <button>Activate</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                }
            }
            if (!$hasUsers) {
                echo "<tr><td colspan='4'>No users found</td></tr>";
            }
            echo "</table>";
            ?>
        </div> <!-- System users END-->
    </div> <!-- Userspaage END-->
</body>
</html>