<?php 
require_once "php_backend/session.php";

requireRole(['admin', 'staff']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production</title>
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

            <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                <li><a href='Reports.php'>Reports</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="productionpage"> <!--Production page START-->
        <div class="batchcontainer">
            <h2>Start production Batch</h2>
            <form method="POST" action="php_backend/insertBatch.php">
                Item: <input type="text" name="item" value="Vermicast" required>
                Quantity: <input type="number" name="quantity" min="0" required>
                <select name="unit">
                    <option value="Sac">Sac</option>
                    <option value="KG">KG</option>
                </select>
                <details>
                    <summary>Optional</summary>
                    Receiver: <input type="text" name="company">
                </details>
                <br>
                <button tpye="submit">Create Production Batch</button>
            </form>
        </div>  <!--Batch COntainer END-->

        <div class="productionhistory">  <!--Production histopry START-->
            <table>
                <tr>
                    <th>Batch ID</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Date</th>
                </tr>
            <?php 
            require_once "php_backend/db.php";

            $stmt = $pdo->prepare("SELECT * FROM production");
            $stmt->execute();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
                <td><?php echo $row['batch_id']; ?></td>
                <td><?php echo $row['item']; ?></td>
                <td><?php echo $row['quantity'] . $row['unit']; ?></td>
                <td><?php echo $row['production_date']; ?></td>
            </tr>
            <?php endwhile;?>

            </table>

        </div><!--Production histopry END-->
            
    </div> <!--Production page END-->
</body>
</html>
