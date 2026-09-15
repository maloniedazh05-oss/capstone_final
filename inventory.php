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

// Search + date filters (both cards use GET so they can combine in the URL)
$searchInv = trim($_GET['search-inv'] ?? '');
$searchHistory = trim($_GET['search-history'] ?? '');
$historyDate = $_GET['history-date'] ?? 'all';
$currentDate = $_GET['current-date'] ?? 'all';
if ($historyDate !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyDate)) {
    $historyDate = 'all';
}
if ($currentDate !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentDate)) {
    $currentDate = 'all';
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
            <h1 class="page-header">Inventory Management</h1>
        </div>

        <!-- Card 1: Current Stock Levels -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-list-ul"></i> Current Stock Levels</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="inventory.php" id="current-filter-form">
                        <?php if ($historyDate !== 'all'): ?>
                        <input type="hidden" name="history-date" value="<?= htmlspecialchars($historyDate) ?>"> 
                        <?php endif; ?>
                        <input type="text" id="search-box-inv" placeholder="Search..." name="search-inv" value="<?= htmlspecialchars($searchInv) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-inv"><i class="fa-solid fa-magnifying-glass"></i></button>

                        <?php
                        require_once "php_backend/db.php";
                        // Fetch the search var value. 
                        $searchInv = trim($_GET['search-inv'] ?? '');
                        $searchInv = "%{$searchInv}%";
                        $dateOpts = $pdo->prepare("SELECT DISTINCT DATE(created_at) AS d FROM inventory WHERE status != 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search) ORDER BY d DESC");
                        $dateOpts->bindValue(':search', $searchInv);
                        $dateOpts->execute();
                        $currentDates = $dateOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="current-date-filter">Filter by Date:</label>
                        <select id="current-date-filter" name="current-date" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="all" <?= $currentDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($currentDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $currentDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($searchInv !== '' || $currentDate !== 'all'): ?>
                        <a href="inventory.php<?= ($searchHistory !== '' || $historyDate !== 'all') ? '?search-history=' . urlencode($searchHistory) . '&history-date=' . urlencode($historyDate) : '' ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
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
                $currentSql = "SELECT * FROM inventory WHERE status != 'Completed'";
                $currentParams = [];
                if ($searchInv !== '') {
                    $currentSql .= " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search OR status LIKE :search)";
                    $currentParams[':search'] = "%" . $searchInv . "%";
                }
                if ($currentDate !== 'all') {
                    $currentSql .= " AND DATE(created_at) = :cdate";
                    $currentParams[':cdate'] = $currentDate;
                }
                $currentSql .= " ORDER BY created_at DESC";
                $stmt = $pdo->prepare($currentSql);
                foreach ($currentParams as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
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
                                <?php
                                $invRows = [];
                                $invError = false;
                                try {
                                    $stmt->execute();
                                    $invRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) {
                                    $invError = true;
                                    echo "<tr><td colspan='6'>Search query not found!</td></tr>";
                                }
                                if (!$invError && empty($invRows)):
                                    echo "<tr><td colspan='6'>" . ($searchInv !== '' ? "No items match '" . htmlspecialchars($searchInv) . "'." : "No current supplies found.") . "</td></tr>";
                                endif;
                                foreach ($invRows as $row):
                                ?>
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
                                            Details <!--Detail button in the actions, Action Details button-->
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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

        <!-- Card 3: History START -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> History</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="inventory.php" id="history-filter-form">
                        <?php if (trim($_GET['search-inv'] ?? '') !== ''): ?>
                        <input type="hidden" name="search-inv" value="<?= htmlspecialchars(trim($_GET['search-inv'] ?? '')) ?>">
                        <?php endif; ?>
                        <?php if ($currentDate !== 'all'): ?>
                        <input type="hidden" name="current-date" value="<?= htmlspecialchars($currentDate) ?>">
                        <?php endif; ?>
                        <input type="text" id="search-box-history" placeholder="Search..." name="search-history" value="<?= htmlspecialchars($searchHistory) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-history"><i class="fa-solid fa-magnifying-glass"></i></button>

                        <?php
                        require_once "php_backend/db.php";
                        // Fetch the search var value.
                        $historyLike = "%{$searchHistory}%";
                        $dateOpts = $pdo->prepare("SELECT DISTINCT DATE(created_at) AS d FROM inventory WHERE status = 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search OR status LIKE :search) ORDER BY d DESC");
                        $dateOpts->bindValue(':search', $historyLike);
                        $dateOpts->execute();
                        $historyDates = $dateOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="history-date-filter">Filter by Date:</label>
                        <select id="history-date-filter" name="history-date" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $historyDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($historyDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $historyDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($searchHistory !== '' || $historyDate !== 'all'): ?>
                        <a href="inventory.php<?= (trim($_GET['search-inv'] ?? '') !== '' || $currentDate !== 'all') ? '?search-inv=' . urlencode(trim($_GET['search-inv'] ?? '')) . '&current-date=' . urlencode($currentDate) : '' ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div> <!--Content Card End, History END-->
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";

                if(!$prod):
                ?>
                <p class="section-desc">No completed items yet.</p>
                <?php else: ?>
                <?php
                $historySql = "SELECT * FROM inventory WHERE status = 'Completed'";
                $historyParams = [];
                if ($searchHistory !== '') {
                    $historySql .= " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search OR status LIKE :search)";
                    $historyParams[':search'] = "%" . $searchHistory . "%";
                }
                if ($historyDate !== 'all') {
                    $historySql .= " AND DATE(created_at) = :hdate";
                    $historyParams[':hdate'] = $historyDate;
                }
                $historySql .= " ORDER BY created_at DESC";
                $stmt = $pdo->prepare($historySql);
                foreach ($historyParams as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
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
                            <?php
                            $historyRows = [];
                            $historyError = false;
                            try {
                                $stmt->execute();
                                $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $historyError = true;
                                echo "<tr><td colspan='6'>Search query not found!</td></tr>";
                            }
                            if (!$historyError && empty($historyRows)):
                            ?>
                            <tr><td colspan="6"><?= ($searchHistory !== '' || $historyDate !== 'all') ? "No history matches your search/filter." : "No completed items yet." ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($historyRows as $h_row): ?>
                            <tr>
                                <td><?= htmlspecialchars($h_row['product']) ?></td>
                                <td><strong><?= htmlspecialchars($h_row['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($h_row['unit']) ?></td>
                                <td><span class="badge-status badge-completed"><?= htmlspecialchars($h_row['status']) ?></span></td>
                                <td><?= htmlspecialchars($h_row['description'] ?: 'None') ?></td>
                                <td><?= htmlspecialchars($h_row['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div> <!-- Card 3 END -->
    </div> <!-- Inventorypage END -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
                //console.log(company);
                if(detail)
                document.getElementById('message').innerHTML = "<h3>Info: </h3>" + "<p>" + detail + "</p>";
                else
                document.getElementById('message').textContent = "No description";

                document.getElementById('feedback-diag').showModal();
            });
        });
        }); // DOMContentLoaded - date filter is server-side (history-date GET param), no JS filtering needed
    </script>
</body>
</html>