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
    <title>Inventory</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>

    <!-- Insert/Add Item Dialog START -->
    <dialog id="item-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-circle-plus"></i> Add Item</h3>
        </div>
        <div class="dialog-body">
            <form method="POST" action="php_backend/insertItem.php">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Name</label>
                    <input type="text" name="product_name" value="Vermicast" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Metric</label>
<select id="metrics" name="metrics" required>
                <option value="">Type</option>
                <option value="Sac">Sac</option>
                <option value="KG">KG</option>
            </select>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Status</label>
                    <select name="status" id="stat">
                        <option value="Recent">Recent</option>
                        <option value="Processing">Processing</option>
                        <option value="Sorted">Sorted</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <details class="dialog-details">
                    <summary class="summaries">Info - Optional</summary>
                    <div class="form-group" style="margin-top: 8px;">
                        <label>Description / Notes</label>
                        <textarea placeholder="Notes.. OPTIONAL" name="description" class="desc"></textarea>
                    </div>
                </details>
                <div class="dialog-actions">
                    <button type="button" class="btn-secondary" command="close" commandfor="item-diag">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Insert Item</button>
                </div>
            </form>
        </div>
    </dialog> <!-- Insert/Add Item Dialog END -->

    <!-- Edit/Update Dialog START -->
    <dialog id="edit-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-pen-to-square"></i> Adjust Item</h3>
        </div>
        <div class="dialog-body">
            <form method="POST" id="updateForm" action="php_backend/updateItem.php">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Name</label>
                    <input type="text" name="product_name" id="edit_name" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Metric</label>
<select name="metrics" id="edit_metric" required>
                <option value="Sac">Sac</option>
                <option value="KG">KG</option>
            </select>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Status</label>
                    <select name="status" id="status_edit">
                        <option value="Recent">Recent</option>
                        <option value="Processing">Processing</option>
                        <option value="Sorted">Sorted</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <details class="dialog-details">
                    <summary class="summaries">Info - Optional</summary>
                    <input type="text" name="status-custom" id="status-custom" placeholder="Custom status" style="display:none;">
                    <div class="form-group" style="margin-top: 8px;">
                        <label>Description / Notes</label>
                        <textarea placeholder="Notes.. OPTIONAL" name="description" class="desc"></textarea>
                    </div>
                </details>
                <div class="dialog-actions">
                    <button type="button" class="btn-secondary" command="close" commandfor="edit-diag">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </dialog> <!-- Edit/Update Dialog END -->

    <!-- Feedback dialog -->
    <dialog id="feedback-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-circle-info"></i> Notice</h3>
        </div>
        <div class="dialog-body">
            <div id="message" style="margin-bottom: 16px; color: var(--color-text-main);">Nothing to see here..</div>
            <div class="dialog-actions" style="justify-content: center;">
                <button type="button" class="btn-primary" command="close" commandfor="feedback-diag" onclick="window.location.href='inventory.php'">Close</button>
            </div>
        </div>
    </dialog>

    <div class="inventorypage">
        <div class="page-header">
            <h1>Inventory Management</h1>
        </div>

        <!-- Card 1: Current Stock Levels -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-list-ul"></i> Current Stock Levels</h2>
            </div>
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";
                $stmt = $pdo->prepare("SELECT prod_id FROM inventory");
                $stmt->execute();
                $prod = $stmt->fetch();
                
                if (!$prod): ?>
                    <p class="section-desc">No current supplies found in the inventory.</p>
                    <button type="button" class="btn-primary" command="show-modal" commandfor="item-diag">
                        <i class="fa-solid fa-plus"></i> Add Item
                    </button>
                <?php else: ?>
                    <?php 
                    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE status != 'Completed'");
                    $stmt->execute();            
                    ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item ID</th>
                                    <th>Name</th>
                                    <th>Stock Level</th>
                                    <th>Unit</th>
                                    <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['prod_id']) ?></td>
                                    <td><?= htmlspecialchars($row['product']) ?></td>
                                    <td class="stock-level-cell"><strong><?= htmlspecialchars($row['quantity']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                    <td><?= htmlspecialchars($row['created_at'])?></td>
                                    <td class="action-cell">
                                        <button type="button" class="btn-table-action edit-btn" 
                                            data-id="<?=htmlspecialchars($row['prod_id'])?>"
                                            data-name="<?=htmlspecialchars($row['product'])?>"
                                            data-qty="<?=htmlspecialchars($row['quantity'])?>"
                                            data-metric="<?=htmlspecialchars($row['unit'])?>"
                                            data-status="<?=htmlspecialchars($row['status'])?>"
                                            data-description="<?=htmlspecialchars($row['description'] ?? '')?>">
                                            <?=$row['status']?>
                                        </button>
                                        <button type="button" class="btn-table-details details"
                                            data-detail="<?= htmlspecialchars($row['description'] ?? '') ?>"
                                            data-type="<?=htmlspecialchars($row['unit'])?>">
                                            Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div> <!-- Card 1 END -->

        <!-- Card 2: Receive New Supplies -->
        <?php if ($prod): ?>
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-circle-plus"></i> Receive New Supplies</h2>
            </div>
            <div class="card-body">
                <p class="section-desc">Record incoming stock batches into the inventory system.</p>
                <button type="button" class="btn-primary" command="show-modal" commandfor="item-diag">
                    <i class="fa-solid fa-plus"></i> Add Item
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 3: History -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> History</h2>
                <div class="card-filter">
                    <i class="fa-solid fa-filter"></i>
                    <label for="history-date-filter">Filter by Date:</label>
                    <select id="history-date-filter">
                        <option value="all">All Dates</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";
                $i_history = $pdo->prepare("SELECT product, quantity, unit, status, description, created_at FROM inventory WHERE status = :status ORDER BY created_at DESC");
                $i_history->execute([':status' => 'Completed']);
                ?>
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($h_row = $i_history->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($h_row['product']) ?></td>
                                <td><strong><?= htmlspecialchars($h_row['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($h_row['unit']) ?></td>
                                <td><span class="badge-status badge-completed"><?= htmlspecialchars($h_row['status']) ?></span></td>
                                <td><?= htmlspecialchars($h_row['description'] ?: 'None') ?></td>
                                <td><?= htmlspecialchars($h_row['created_at']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- Card 3 END -->
    </div> <!-- Inventorypage END -->
    <script>
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const target = e.currentTarget;
                    const id = target.dataset.id;
                    const name = target.dataset.name;
                    const qty = target.dataset.qty;
                    const metric = target.dataset.metric;
                    const status = target.dataset.status;
                    const description = target.dataset.description;
                    console.log(status);
                    
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_quantity').value = qty;
                    document.getElementById('edit_metric').value = metric;
                    document.getElementById('status_edit').value = status;

                    /*
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
                    }*/
                    
                    document.getElementById('edit-diag').showModal();
                    document.querySelector('#edit-diag .desc').value = description;                
                });
            });

            /* Dropdown change for edit dialog - Custom Value removed
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
            }*/
        
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
        
        // History Date Filter
        const histFilter = document.getElementById('history-date-filter');
        if (histFilter) {
            const dateSet = new Set();
            document.querySelectorAll('#historyTable tbody tr').forEach(row => {
                const dateCell = row.children[5];
                if (dateCell) {
                    const rawDate = dateCell.textContent.trim().split(' ')[0];
                    if (rawDate) dateSet.add(rawDate);
                }
            });
            dateSet.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                histFilter.appendChild(opt);
            });
            histFilter.addEventListener('change', (e) => {
                const val = e.target.value;
                document.querySelectorAll('#historyTable tbody tr').forEach(row => {
                    const dateCell = row.children[5];
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