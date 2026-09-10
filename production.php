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
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php 
    require_once "main-sidebar.php";
    $feedbackMessage = '';
    if(isset($_GET['updated'])) {
        $feedbackMessage = "Updated Success!";
    } else if(isset($_GET['error'])) {
        $feedbackMessage = "Operation invalid!";
    } 
    ?>

    <div class="productionpage"> <!-- Production page START -->
        <div class="page-header">
            <h1>Production Tracking</h1>
        </div>

        <!-- Card 1: Start New Production Batch -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-circle-plus"></i> Start New Production Batch</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="php_backend/insertBatch.php" class="batch-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="item">Item</label>
                            <input type="text" id="item" name="item" value="Vermicast" required>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <div class="input-with-select">
                                <input type="number" id="quantity" name="quantity" min="0" placeholder="Quantity" required>
                                <select name="unit" id="unit">
                                    <option value="Sac">Sac</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <details class="dialog-details">
                        <summary class="summaries">Optional</summary>
                        <div class="form-group" style="margin-top: 8px;">
                            <label for="company">Receiver</label>
                            <input type="text" id="company" name="company" placeholder="Company / Receiver Name">
                        </div>
                    </details>
                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn-primary">Create Production Batch</button>
                    </div>
                </form>
            </div>
        </div> <!-- Card 1 END -->

        <!-- Card 2: Current Active Batches -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-spinner"></i> Active Production Batches</h2>
            </div>
            <div class="card-body productionview">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Batch ID</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        require_once "php_backend/db.php";
                        $stmt = $pdo->prepare("SELECT * FROM production WHERE status = :recent OR status = :ongoing");
                        $stmt->execute([':recent' => 'Recent', ':ongoing' => 'Ongoing']);
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['batch_id']); ?></td>
                            <td><?= htmlspecialchars($row['item']); ?></td>
                            <td><strong><?= htmlspecialchars($row['quantity'] . ' ' . $row['unit']); ?></strong></td>
                            <td><?= htmlspecialchars($row['production_date']); ?></td>
                            <td class="action-cell">
                                <button type="button" class="btn-table-action" id="receiverButton" data-company="<?=htmlspecialchars($row['receiver'] ?? '');?>" data-viewstatus="<?=htmlspecialchars($row['status'] ?? '');?>">Details</button>
                                <button type="button" class="btn-table-action" id="statusButton" data-productionid="<?=$row['production_id']?>" data-editstatus="<?=htmlspecialchars($row['status'] ?? '');?>">Status</button>
                            </td>
                        </tr>
                        <?php endwhile;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- Card 2 END -->

        <!-- Card 3: Production History -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-list-ul"></i> Production History</h2>
                <div class="card-filter">
                    <i class="fa-solid fa-filter"></i>
                    <label for="prod-history-date-filter">Filter by Date:</label>
                    <select id="prod-history-date-filter">
                        <option value="all">-- All Dates --</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <?php 
                require_once "php_backend/db.php";
                $history = $pdo->prepare("SELECT batch_id, production_date, item, quantity, unit, status FROM production WHERE status = :status ORDER BY production_date DESC");
                $history->execute([':status' => 'Completed']);
                ?>
                <div class="table-responsive">
                    <table class="data-table" id="prodHistoryTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Batch ID</th>
                                <th>Quantity</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($h_row = $history->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($h_row['item']); ?></td>
                                <td><?= htmlspecialchars($h_row['batch_id']); ?></td>
                                <td><strong><?= htmlspecialchars($h_row['quantity'] . ' ' . $h_row['unit']); ?></strong></td>
                                <td><?= htmlspecialchars($h_row['production_date']); ?></td>
                            </tr>
                        <?php endwhile;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- Card 3 END -->
            
    </div> <!-- Production page END -->

    <!-- Feedback dialog -->
    <dialog id="feedback-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-circle-info"></i> Notice</h3>
        </div>
        <div class="dialog-body">
            <div id="message" style="margin-bottom: 16px; color: var(--color-text-main);"></div> 
            <div class="dialog-actions" style="justify-content: center;">
                <button type="button" class="btn-primary" command="close" commandfor="feedback-diag" onclick="window.location.href='production.php'">Close</button>   
            </div>
        </div>
    </dialog>

    <!-- Status dialog -->
    <dialog id="status-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-pen-to-square"></i> Update Batch Status</h3>
        </div>
        <div class="dialog-body">
            <form method="POST" action="php_backend/updateBatch.php">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="status_id">Status</label>
                    <select id="status_id">
                        <option value="Recent">Recent</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <input type="hidden" name="id">
                <input type="hidden" name="status">
                <div class="dialog-actions">
                    <button type="button" class="btn-secondary" command="close" commandfor="status-diag">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm</button>
                </div>
            </form>
        </div>
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

                // Production History Date Filter
                const prodHistFilter = document.getElementById('prod-history-date-filter');
                if (prodHistFilter) {
                    const prodDateSet = new Set();
                    document.querySelectorAll('#prodHistoryTable tbody tr').forEach(row => {
                        const dateCell = row.children[3];
                        if (dateCell) {
                            const rawDate = dateCell.textContent.trim().split(' ')[0];
                            if (rawDate) prodDateSet.add(rawDate);
                        }
                    });
                    prodDateSet.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d;
                        opt.textContent = d;
                        prodHistFilter.appendChild(opt);
                    });
                    prodHistFilter.addEventListener('change', (e) => {
                        const val = e.target.value;
                        document.querySelectorAll('#prodHistoryTable tbody tr').forEach(row => {
                            const dateCell = row.children[3];
                            if (!dateCell) return;
                            if (val === 'all' || dateCell.textContent.includes(val)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
            </script>
</body>
</html>
