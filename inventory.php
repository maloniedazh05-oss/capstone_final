<?php
require_once "php_backend/session.php";

requireRole(['admin', 'manager']);

// Success/error messages from redirects - pagerefresh cause js eprevent default is not working
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
    <?php require_once "main-sidebar.php"; ?>
            <!-- Dialogs -->
             
            <!--Insert/Add Item Dialog START-->
<dialog id="item-diag">
        <form method="POST" action="php_backend/insertItem.php">
            Name: <input type="text" name="product_name" value="Vermicast" required>
            Quantity: <input type="number" name="quantity" min="0" required>
            <select id="metrics" name="metrics" required>
                <option value="">Type</option>
                <option value="Sacks">Sack</option>
                <option value="KG">KG</option>
            </select>
            <details>
                <summary class="summaries">Satus/Info - Optional</summary>
            <div class="status-container">
Status:
                <select name="status" id="stat">
                    <option value="Recent">Recent</option>
                    <option value="Processing">Processing</option>
                    <option value="Sorted">Sorted</option>
                    <option value="Completed">Completed</option>
                    <option value="custom">Custom</option>
                </select>
                <input type="text" name="status-custom" id="status-custom-insert" placeholder="Custom status" style="display:none;">
                Description:<br><textarea placeholder="Notes.. OPTIONAL" name="description" class="desc"></textarea>
            </div>
            </details>
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
                <br>Status: 
                <select name="status" id="stat">
                    <option value="Recent">Recent</option>
                    <option value="Processing">Processing</option>
                    <option value="Sorted">Sorted</option>
                    <option value="Completed">Completed</option>
                    <option value="custom">Custom</option>
                </select>
            <details>
            <summary class="summaries">Satus/Info - Optional</summary>
            <div class="status-container">

                <input type="text" name="status-custom" id="status-custom" placeholder="Custom status" style="display:none;">
            </div>
            Description:<br><textarea placeholder="Notes.. OPTIONAL" name="description" class="desc"></textarea>
            </details>
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
            $stmt = $pdo->prepare("SELECT * FROM inventory WHERE status != 'Completed'");
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
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td><?= $row['prod_id'] ?></td>
                        <td><?= $row['product'] ?></td>
                        <td><?= $row['quantity'] ?> <?= $row['unit'] ?></td>
                        <td><?= $row['status'] ?></td>
                        <td><button type='button' class='edit-btn' data-id='<?=$row['prod_id']?>'
                            data-name='<?=$row['product']?>'
                            data-qty='<?=$row['quantity']?>'
                            data-metric='<?=$row['unit']?>'
                            data-status='<?=$row['status']?>'
                            data-description='<?=$row['description'] ?>'>
                            <?=$row['status'] ?></button>
                            <button type='button' class='details'
                            data-detail='<?= $row['description'] ?>'
                            data-type='<?=$row['unit']?>'>Details</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div> <!-- Inventory Stocks END-->

        <?php if ($prod): ?>
            <h2>Receive New Supplies</h2>
            <button command="show-modal" commandfor="item-diag">Add Item</button>
        <?php endif; ?>
<div> <!--Inventory history START-->
    <?php
    require_once "php_backend/db.php";

    $i_history = $pdo->prepare("SELECT product, quantity, unit, status, description, created_at FROM inventory WHERE status = :status");
    if ($i_history->execute([':status' => 'Completed'])) {
        echo "<h2>History</h2><table><tr>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Date Created</th>
                    </tr>";
    }
    while ($h_row = $i_history->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
            <td><?php echo $h_row['product']; ?></td>
            <td><?php echo $h_row['quantity']; ?></td>
            <td><?php echo $h_row['unit']; ?></td>
            <td><?php echo $h_row['status']; ?></td>
            <td><?php echo $h_row['description']; ?></td>
            <td><?php echo $h_row['created_at']; ?></td>
        </tr>
    <?php endwhile; ?>
    </table>
</div><!--Inventory history END-->
</div> <!-- Inventory END-->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const target = e.currentTarget;
                    const id = target.dataset.id;
                    const name = target.dataset.name;
                    const qty = target.dataset.qty;
                    const metric = target.dataset.metric;
                    const status = target.dataset.status;
                    const description = target.dataset.description;
                    console.log(description);
                    
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_quantity').value = qty;
                    document.getElementById('edit_metric').value = metric;
                    
                    // Check status if value or custom
                    const customInput = document.getElementById('status-custom');
                    const statusSelect = document.getElementById('stat');

                    if (status === 'Recent' || status === 'Processing' || status === 'Sorted' || status === 'Completed') {
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
                    document.querySelector('#edit-diag .desc').value = description;                
                });
            });

            // Dropdown change for edit dialog
            const stat = document.getElementById('stat');
            if (stat) {
                stat.addEventListener("change", (e) => {
                    const customInput = document.getElementById('status-custom');
                    if (e.target.value === 'custom') {
                        customInput.style.display = 'inline-block';
                        customInput.focus();
                    } else {
                        customInput.style.display = 'none';
                        customInput.value = '';
                    }
                });
            }
            
            // dropdown change for insert dialog
            const statInsert = document.getElementById('stat-insert');
            if (statInsert) {
                statInsert.addEventListener('change', (e) => {
                    const customInput = document.getElementById('status-custom-insert');
                    if (e.target.value === 'custom') {
                        customInput.style.display = 'inline-block';
                        customInput.focus();
                    } else {
                        customInput.style.display = 'none';
                        customInput.value = '';
                    }
});
            }
        
        // Show feedback dialog on Detail:
        const details = document.querySelectorAll('.details');
        details.forEach((link) => {
            link.addEventListener("click", (e) => {
                const detail = e.currentTarget.dataset.detail;
                const type = e.currentTarget.dataset.type;
                if(detail)
                document.getElementById('message').innerHTML = "<h3>Notes: </h3>" + "<p>" + detail + "</p>";
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
        });
    </script>
</body>
</html>