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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php"; ;
    $feedbackMessage = '';
    if(isset($_GET['updated'])) {
    $feedbackMessage = "Updated Success!";
    } else if(isset($_GET['error'])) {
    $feedbackMessage = "Operation invalid!";
    } 
    ?>

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

        <div class="productionview">  <!--Production START-->
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
                <td><button id="statusButton" data-productionid="<?=$row['production_id']?>" data-editstatus="<?=$row['status'];?>">Status</button></td>
            </tr>
            <?php endwhile;?>

            </table>

            <div class="productionhistory"> <!-- Production history START-->
                <?php 
                require_once "php_backend/db.php";

                $history = $pdo->prepare("SELECT batch_id, production_date, item, quantity, unit, status FROM production WHERE status = :status");
                if($history->execute([':status' => 'Completed'])) {
                    echo "<h2>History</h2><table><tr>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Batch ID</th>
                        <th>Date Created</th>
                    </tr>";
                }
                while($h_row = $history->fetch(PDO::FETCH_ASSOC)):
                ?>
                    <tr>
                        <td><?php echo $h_row['item']; ?></td>
                        <td><?php echo $h_row['quantity'] . $h_row['unit']; ?></td>
                        <td><?php echo $h_row['batch_id']; ?></td>
                        <td><?php echo $h_row['production_date']; ?></td>
                    </tr>
                <?php endwhile;?>
                </table>
            </div> <!-- Productin history END-->
        </div><!--Production view END-->
            
    </div> <!--Production page END-->
    <dialog id="feedback-diag">
           <p id="message"></p> 
           <button command="close" commandFor="feedback-diag" onclick="window.location.href='production.php'">Close</button>   
    </dialog>

    <dialog id="status-diag">
<form method='POST' action='php_backend/updateBatch.php'>
    <select id='status_id'>
        <option value="Recent">Recent</option><option value="Ongoing">Ongoing</option><option value="Completed">Completed</option>
    </select>
    <input type="hidden" name="id">
    <input type="hidden" name="status">
    <button type="button" command="close" commandfor="status-diag">Cancel</button>&Tab;<button type="submit">Confirm</button>
</form>
    </dialog>
            <script>
                const historyButton = document.querySelectorAll('.productionview #receiverButton');
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
                const editButton = document.querySelectorAll('.productionview #statusButton');
                editButton.forEach((button) => {
                    button.addEventListener('click', (e) => {
                        const p_id = e.currentTarget.dataset.productionid;
                        const p_status = e.currentTarget.dataset.editstatus;
                        console.log(p_status);
                        document.getElementById("status_id").value = p_status;
                        document.getElementById('status-diag').showModal();

                        document.querySelector("input[type='hidden'][name='id']").value = p_id;
                        document.querySelector("input[type='hidden'][name='status']").value = p_status;
                    });
                });

                <?php 
                if ($feedbackMessage):
                ?>
                document.getElementById('feedback-diag').showModal();
                document.getElementById('message').textContent = '<?=$feedbackMessage?>';
                <?php endif; ?>

            </script>
</body>
</html>
