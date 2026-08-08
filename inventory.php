<?php
require_once "php_backend/session.php";

requireRole(['admin', 'manager']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <div class="main-sidebar">
        <ul>
            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
                <li><a href='dashboard.php'>Dashboard</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                <li><a href='inventory.php'>Inventory</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
                <li><a href='production.php'>Production</a></li>
            <?php endif; ?>
        </ul>
    </div>
            <!-- Dialogs -->	
    <dialog id="item-diag">
        <form method="POST" action="php_backend/insertItem.php">
            Name: <input type="text" name="product_name" required>
            Quantity: <input type="number" name="quantity" min="0" required>
            <select id="metrics" name="metrics" required>
                <option value="">Type</option>
                <option value="Sacks">Sack</option>
                <option value="KG">KG</option>
            </select>
            <button type='button' command="close" commandfor="item-diag">Cancel</button>
            <button type="submit">Insert Item</button>
        </form>
    </dialog>
    <div class="inventorypage">
        <div class="inventorystocks">
            <h1>Current Stocks</h1>
            <?php
            $stmt = null;
            require_once "php_backend/db.php";
            $stmt = $pdo->prepare("SELECT prod_id FROM inventory");
            $stmt->execute();
            $prod = $stmt->fetch();

            if (!$prod): ?>
                <h2>No Supplies</h2>
                <button command="show-modal" commandfor="item-diag">Add Item</button>
            <?php exit; endif; ?>
            <?php 
            require_once "php_backend/db.php";
            $stmt = $pdo->prepare("SELECT prod_id, product, quantity, metric, status, order_date, created_at, updated_at FROM inventory");
            $stmt->execute();
            $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <table border="1" style="border-collapse: collapse; border: 2px solid black; padding: 5px;">
                <tr>
                <th>Item ID</th>
                <th>Name</th>
                <th>Stock Level</th>
                <th>Status</th>
                </tr>
                <?php foreach ($row as $item): ?>
                <tr>
                    <td><?php echo $item['prod_id']; ?></td>
                    <td><?php echo $item['product']; ?></td>
                    <td><?php echo $item['quantity'] . ' ' . $item['metric']; ?></td>
                    <td><?php echo $item['status']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div> <!-- Inventory Stocks END-->

        <?php if ($prod): ?>
            <h2>Receive New Supplies</h2>
            <button command="show-modal" commandfor="item-diag">Add Item</button>
        <?php endif; ?>

    </div> <!-- Inventory END-->

</body>
</html>