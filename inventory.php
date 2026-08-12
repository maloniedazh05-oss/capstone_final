<?php
require_once "php_backend/session.php";

requireRole(['admin', 'manager']);

// Handle success/error messages from redirects
$feedbackMessage = '';
if (isset($_GET['success'])) {
    $feedbackMessage = 'Operation completed successfully!';
} elseif (isset($_GET['error'])) {
    $feedbackMessage = 'An error occurred. Please try again.';
}
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
             
            <!--Insert/Add Item Dialog START-->
<dialog id="item-diag">
        <form method="POST" action="php_backend/insertItem.php">
            Name: <input type="text" name="product_name" required>
            Quantity: <input type="number" name="quantity" min="0" required>
            <select id="metrics" name="metrics" required>
                <option value="">Type</option>
                <option value="Sacks">Sack</option>
                <option value="KG">KG</option>
            </select>
            <div class="status-container">
                Status:
                <select name="status" id="stat-insert">
                    <option value="Recent">Recent</option>
                    <option value="Processing">Processing</option>
                    <option value="custom">Custom</option>
                </select>
                <input type="text" name="status-custom" id="status-custom-insert" placeholder="Custom status" style="display:none;">
            </div>
            <button type='button' command="close" commandfor="item-diag">Cancel</button>
            <button type="submit">Insert Item</button>
        </form>
    </dialog> <!--Insert/Add Item Dialog END-->

            <!-- Edit/Update Dialog START-->
    <dialog id="edit-diag">
        <form method="POST" id="updateForm" action="php_backend/updateItem.php">
            <input type="hidden" name="edit_id" id="edit_id">
            Name: <input type="text" name="product_name" id="edit_name" required>
            Quantity: <input type="number" name="quantity" id="edit_quantity" min="0" required>
            <select name="metrics" id="edit_metric" required>
                <option value="Sacks">Sack</option>
                <option value="KG">KG</option>
            </select>
            <div class="status-container">
                Status:
                <select name="status" id="stat">
                    <option value="Recent">Recent</option>
                    <option value="Processing">Processing</option>
                    <option value="custom">Custom</option>
                </select>
                <input type="text" name="status-custom" id="status-custom" placeholder="Custom status" style="display:none;">
            </div>
            Description:<br><textarea placeholder="Notes.. OPTIONAL" name="description" id="desc"></textarea>
            <button type='button' command="close" commandfor="edit-diag">Cancel</button>
            <button type="submit">Save Changes</button>
        </form>
    </dialog><!-- Edit/Update Dialog END-->

    <!--Feedback dialog-->
    <dialog id="feedback-diag">
        <p id="message">Nothing to see here..</p>
        <button type='button' command="close" commandfor="feedback-diag">Close</button>
    </dialog>
    <div class="inventorypage">
        <div class="inventorystocks">
            <h1>Current Stocks</h1>

            <!--Check if there's an item-->
            <?php
            require_once "php_backend/db.php";
            $stmt = $pdo->prepare("SELECT prod_id FROM inventory");
            $stmt->execute();
            $prod = $stmt->fetch();
            
            # If fetch returns none.
            if (!$prod): ?>
                <h2>No Supplies</h2>
                <button command="show-modal" commandfor="item-diag">Add Item</button>
            <?php exit; endif; ?>

            <!--Fetch and display the items-->
            <?php 
            require_once "php_backend/db.php";
            $stmt = $pdo->prepare("SELECT * FROM inventory");
            $stmt->execute();            
            ?>

<!--Table and Data STATRT-->
            <table border="1" style="border-collapse: collapse; border: 2px solid black; padding: 5px;">
                <tr>
                <th>Item ID</th>
                <th>Name</th>
                <th>Stock Level</th>
                <th>Status</th>
                <th>Actions</th>
                </tr>  
                <?php 
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $id = $row['prod_id'];
                    $name =$row['product'];
                    $qty = $row['quantity'];
                    $metric = $row['metric'];
                    $status = $row['status'];
                    $desc = $row['description'];
                    echo "
                    <tr>
                        <td>{$id}</td>
                        <td>{$name}</td>
                        <td>{$qty} {$metric}</td>
                        <td>{$status}</td>
                        <td><button type='button' class='edit-btn' data-id='{$id}' data-name='{$name}' data-qty='{$qty}' data-metric='{$metric}' data-status='{$status}' data-description='{$desc}'>{$status}</button><button type='button' class='details' data-detail='{$desc}'>Details</button></td>
                    </tr>
                ";
                }
                ?>
            </table>
        </div> <!-- Inventory Stocks END-->

        <?php if ($prod): ?>
            <h2>Receive New Supplies</h2>
            <button command="show-modal" commandfor="item-diag">Add Item</button>
        <?php endif; ?>

</div> <!-- Inventory END-->
    <script>
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                const id = target.dataset.id;
                const name = target.dataset.name;
                const qty = target.dataset.qty;
                const metric = target.dataset.metric;
                const status = target.dataset.status;
                const desc = target.dataset.description;
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_quantity').value = qty;
                document.getElementById('edit_metric').value = metric;
                document.getElementById('desc').value = desc;
                
                // Check status if value or custom
                const statusSelect = document.getElementById('stat');
                const customInput = document.getElementById('status-custom');
                
                if (status === 'Recent' || status === 'Processing') {
                    statusSelect.value = status;
                    customInput.style.display = 'none';
                    customInput.value = '';
                } else {
                    // Custom status
                    statusSelect.value = 'custom';
                    customInput.style.display = 'inline-block';
                    customInput.value = status;
                }
                
                document.getElementById('edit-diag').showModal();
            });
        });
        
        // Dropdown change for edit dialog
        document.getElementById('stat').addEventListener('change', (e) => {
            const customInput = document.getElementById('status-custom');
            if (e.target.value === 'custom') {
                customInput.style.display = 'inline-block';
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.value = '';
            }
        });
        
        // Handle status dropdown change for insert dialog
        document.getElementById('stat-insert').addEventListener('change', (e) => {
            const customInput = document.getElementById('status-custom-insert');
            if (e.target.value === 'custom') {
                customInput.style.display = 'inline-block';
                // Set focus to the custom input field when the status dropdown if 'custom'
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.value = '';
            }
        });

        // Show feedback dialog on Detail hyperlink:
        const detailsLinks = document.querySelectorAll('.details');
        detailsLinks.forEach((link) => {
            link.addEventListener("click", (e) => {
                const detail = e.currentTarget.dataset.detail;
                if(detail)
                document.getElementById('message').textContent = detail;
                else
                document.getElementById('message').textContent = "No description";

                document.getElementById('feedback-diag').showModal();
            });
        });
        
        // Show feedback dialog if there's a message
        <?php if ($feedbackMessage): ?>
            document.getElementById('message').textContent = '<?=$feedbackMessage?>';
            document.getElementById('feedback-diag').showModal();
        <?php endif; ?>
    </script>
</body>
</html>