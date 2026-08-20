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

            $stmt = $pdo->prepare("SELECT * FROM production WHERE status = :recent OR status = :ongoing");
            $stmt->execute([':recent' => 'Recent', ':ongoing' => 'Ongoing']);
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
                <td><?php echo $row['batch_id']; ?></td>
                <td><?php echo $row['item']; ?></td>
                <td><?php echo $row['quantity'] . $row['unit']; ?></td>
                <td><?php echo $row['production_date']; ?></td>
                <td><button id="receiverButton" data-company="<?=$row['receiver'];?>" data-viewstatus="<?=$row['status'];?>">Details</button></td>
                <td><button id="statusButton" data-production_id="<?=$row['production_id']?>" data-batch="<?=$row['batch_id'];?>" data-date="<?=$row['batch_id'];?>" data-item="<?=$row['item']?>" data-quantity="<?=$row['quantity']?>"  data-unit="<?=$row['unit']?>" data-status="<?=$row['status'];?>" data-receiver="<?=$row['receiver']?>">Status</button></td>
            </tr>
            <?php endwhile;?>

            </table>


        </div><!--Production histopry END-->
            
    </div> <!--Production page END-->
            <script>
                const historyButton = document.querySelectorAll('.productionhistory #receiverButton');
                historyButton.forEach((button) => {
                    button.addEventListener('click', (e) => {
                        const receiver = e.currentTarget.dataset.company;
                        const status = e.currentTarget.dataset.viewstatus;
                        const company = receiver ? receiver : 'None';
                        console.log(company);
                        document.getElementById('feedback-diag').showModal();
                        document.getElementById('message').innerHTML = '<h3>Company: </h3>' + company + '<br>' + '<h3>Status: ' + status + '</h3>';
                    });
                });

                // Status Fetch
                document.querySelectorAll(".productionhistory #statusButton").forEach((button) => {
                    button.addEventListener('click', (e) => {
                        const target = e.currentTarget;
                        const p_id = target.dataset.production_id;
                        const p_batch = target.dataset.batch;
                        const p_date = target.dataset.date;
                        const p_item = target.dataset.item;
                        const p_quantity = target.dataset.quantity;
                        const p_unit = target.dataset.unit;                        
                        const p_status = target.dataset.status;
                        const p_receiver = target.dataset.receiver;
                        console.log(p_status);
                        console.log(p_batch);
                        document.getElementById('status-diag').showModal();
                        document.querySelector('input[type="hidden"][name="batch"]').value = p_batch;
                        document.querySelector('input[type="hidden"][name="id"]').value = p_id;
                        document.querySelector('input[type="hidden"][name="date"]').value = p_date;
                        document.querySelector('input[type="hidden"][name="item"]').value = p_item;
                        document.querySelector('input[type="hidden"][name="quantity"]').value = p_quantity;
                        document.querySelector('input[type="hidden"][name="unit"]').value = p_unit;
                        document.querySelector('input[type="hidden"][name="status"]').value = p_status;
                        document.querySelector('input[type="hidden"][name="receiver"]').value = p_receiver;

                    });
                });
            </script>
    <dialog id="feedback-diag">
           <p id="message"></p> 
           <button command="close" commandFor="feedback-diag">Close</button>   
    </dialog>

    <dialog id="status-diag">
<form method='GET' action='php_backend/insertBatch.php'>
    <select name='status_id'>
        <option value="Recent">Recent</option><option value="Ongoing">Ongoing</option><option value="Completed">Completed</option>
    </select>
    <input type="hidden" name="id">
    <input type="hidden" name="batch">
    <input type="hidden" name="date">
    <input type="hidden" name="item">
    <input type="hidden" name="quantity">
    <input type="hidden" name="unit">
    <input type="hidden" name="status">
    <input type="hidden" name="receiver">
    <button>Confirm</button>
</form>
    </dialog>
</body>
</html>
