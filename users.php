<?php 
require_once "php_backend/session.php";

requireRole(['admin']);
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
            <form method="POST" action="php_backend/addUser.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
                <br>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
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

            if($userdb->execute()) {
                echo "<table>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>
                ";
            } else echo "No added users.";

            while($user = $userdb->fetch(PDO::FETCH_ASSOC)) if($user['admin'] != 1):
            ?>
            <tr>
                <td><?=$user['user']?></td>
                <td><?=$user['role']?></td>
                <td><?=$user['status']?></td>
                <?php if($user['status'] == 'active'): ?>
                <td><form method="POST" action="php_backend/editUsers.php"><input type="hidden" name="disable_id" value="<?=$user['id']?>"><button>Deactivate</button></form></td>
                <?php endif; ?>
                <?php if($user['status'] == 'disabled'): ?>
                <td><form method="POST" action="php_backend/editUsers.php"><input type="hidden" name="enable_id" value="<?=$user['id']?>"><button>Activate</button></form></td>
                <?php endif; ?>
            </tr>
            <?php endif; ?></table>
        </div> <!-- System users END-->
    </div> <!-- Userspaage END-->
</body>
</html>