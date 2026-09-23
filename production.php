<?php 
require_once "php_backend/session.php";

requireRole(['admin', 'staff']);

// Active batches search + filters (GET so they combine in the URL).
$searchBatch = trim($_GET['search-batch'] ?? '');
$activeStatus = $_GET['active-status'] ?? 'all';
if (!in_array($activeStatus, ['all', 'Recent', 'Ongoing'], true)) {
    $activeStatus = 'all';
}
$activeDate = $_GET['active-date'] ?? 'all';
if ($activeDate !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeDate)) {
    $activeDate = 'all';
}
$activeMonth = $_GET['active-month'] ?? 'all';
$activeYear = $_GET['active-year'] ?? 'all';
$activeSort = $_GET['active-sort'] ?? 'newest';
if ($activeMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
    $activeMonth = 'all';
}
if ($activeYear !== 'all' && !preg_match('/^\d{4}$/', $activeYear)) {
    $activeYear = 'all';
}
if (!in_array($activeSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
    $activeSort = 'newest';
}
$searchProd = trim($_GET['search-prod'] ?? '');
$prodDate = $_GET['prod-date'] ?? 'all';
$prodMonth = $_GET['prod-month'] ?? 'all';
$prodYear = $_GET['prod-year'] ?? 'all';
$prodSort = $_GET['prod-sort'] ?? 'newest';
if ($prodDate !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prodDate)) {
    $prodDate = 'all';
}
if ($prodMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $prodMonth)) {
    $prodMonth = 'all';
}
if ($prodYear !== 'all' && !preg_match('/^\d{4}$/', $prodYear)) {
    $prodYear = 'all';
}
if (!in_array($prodSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
    $prodSort = 'newest';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production</title>
    <?php require_once "php_backend/head_assets.php"; ?>
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
                                    <option value="Sacks">Sacks</option>
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
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-spinner"></i> Active Production Batches</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="production.php" id="active-filter-form">
                        <input type="text" id="search-box-batch" placeholder="Search Batch ID..." name="search-batch" value="<?= htmlspecialchars($searchBatch) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-batch"><i class="fa-solid fa-magnifying-glass"></i></button>

                        <i class="fa-solid fa-filter"></i>
                        <label for="active-status-filter">Status:</label>
                        <select id="active-status-filter" name="active-status" onchange="document.getElementById('active-filter-form').submit()">
                            <option value="all" <?= $activeStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Recent" <?= $activeStatus === 'Recent' ? 'selected' : '' ?>>Recent</option>
                            <option value="Ongoing" <?= $activeStatus === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        </select>
                        <?php
                        require_once "php_backend/db.php";
                        // Fetch the search var value.
                        $batchLike = "%{$searchBatch}%";
                        $batchDateOpts = $pdo->prepare("SELECT DISTINCT DATE(production_date) AS d FROM production WHERE (status = 'Recent' OR status = 'Ongoing') AND (CAST(batch_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY d DESC");
                        $batchDateOpts->bindValue(':search', $batchLike);
                        $batchDateOpts->execute();
                        $activeDates = $batchDateOpts->fetchAll(PDO::FETCH_COLUMN);
                        $batchMonthOpts = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(production_date, '%Y-%m') AS m FROM production WHERE (status = 'Recent' OR status = 'Ongoing') AND (CAST(batch_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY m DESC");
                        $batchMonthOpts->bindValue(':search', $batchLike);
                        $batchMonthOpts->execute();
                        $activeMonths = $batchMonthOpts->fetchAll(PDO::FETCH_COLUMN);
                        $batchYearOpts = $pdo->prepare("SELECT DISTINCT YEAR(production_date) AS y FROM production WHERE (status = 'Recent' OR status = 'Ongoing') AND (CAST(batch_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY y DESC");
                        $batchYearOpts->bindValue(':search', $batchLike);
                        $batchYearOpts->execute();
                        $activeYears = $batchYearOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <label for="active-date-filter">Date:</label>
                        <select id="active-date-filter" name="active-date" onchange="document.getElementById('active-filter-form').submit()">
                            <option value="all" <?= $activeDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($activeDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $activeDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="active-month-filter">Month:</label>
                        <select id="active-month-filter" name="active-month" onchange="document.getElementById('active-filter-form').submit()">
                            <option value="all" <?= $activeMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php foreach ($activeMonths as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $activeMonth === $m ? 'selected' : '' ?>><?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="active-year-filter">Year:</label>
                        <select id="active-year-filter" name="active-year" onchange="document.getElementById('active-filter-form').submit()">
                            <option value="all" <?= $activeYear === 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php foreach ($activeYears as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= (string)$activeYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="active-sort">Sort by:</label>
                        <select id="active-sort" name="active-sort" onchange="document.getElementById('active-filter-form').submit()">
                            <option value="newest" <?= $activeSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $activeSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $activeSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $activeSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <button type="submit" class="btn-secondary" style="padding:6px 10px;">Filter</button>
                        <?php if ($searchBatch !== '' || $activeStatus !== 'all' || $activeDate !== 'all' || $activeMonth !== 'all' || $activeYear !== 'all' || $activeSort !== 'newest'): ?>
                        <a href="production.php" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
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
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        require_once "php_backend/db.php";
                        $activeSql = "SELECT * FROM production WHERE (status = 'Recent' OR status = 'Ongoing')";
                        $activeParams = [];
                        if ($searchBatch !== '') {
                            $activeSql .= " AND (CAST(batch_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search)";
                            $activeParams[':search'] = "%" . $searchBatch . "%";
                        }
                        if ($activeStatus !== 'all') {
                            $activeSql .= " AND status = :astatus";
                            $activeParams[':astatus'] = $activeStatus;
                        }
                        if ($activeDate !== 'all') {
                            $activeSql .= " AND DATE(production_date) = :adate";
                            $activeParams[':adate'] = $activeDate;
                        }
                        if ($activeMonth !== 'all') {
                            $activeSql .= " AND DATE_FORMAT(production_date, '%Y-%m') = :amonth";
                            $activeParams[':amonth'] = $activeMonth;
                        }
                        if ($activeYear !== 'all') {
                            $activeSql .= " AND YEAR(production_date) = :ayear";
                            $activeParams[':ayear'] = $activeYear;
                        }
                        $activeSortMap = ['newest' => 'production_date DESC', 'oldest' => 'production_date ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
                        $activeSql .= " ORDER BY " . $activeSortMap[$activeSort];
                        $stmt = $pdo->prepare($activeSql);
                        foreach ($activeParams as $key => $val) {
                            $stmt->bindValue($key, $val);
                        }
                        $activeRows = [];
                        $activeError = false;
                        try {
                            $stmt->execute();
                            $activeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $activeError = true;
                            echo "<tr><td colspan='6'>Search query not found!</td></tr>";
                        }
                        if (!$activeError && empty($activeRows)):
                            echo "<tr><td colspan='6'>" . ($searchBatch !== '' ? "No batches match '" . htmlspecialchars($searchBatch) . "'." : "No active batches found.") . "</td></tr>";
                        endif;
                        foreach ($activeRows as $row):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['batch_id']); ?></td>
                            <td><?= htmlspecialchars($row['item']); ?></td>
                            <td><strong><?= htmlspecialchars($row['quantity'] . ' ' . $row['unit']); ?></strong></td>
                            <td><?= htmlspecialchars($row['production_date']); ?></td>
                            <td><?= htmlspecialchars($row['updated_at'] ?? ''); ?></td>
                            <td class="action-cell">
                                <button type="button" class="btn-table-action" id="receiverButton" data-company="<?=htmlspecialchars($row['receiver'] ?? '');?>" data-viewstatus="<?=htmlspecialchars($row['status'] ?? '');?>">Details</button>
                                <button type="button" class="btn-table-action" id="statusButton" data-productionid="<?=$row['production_id']?>" data-quantitystockin="<?=$row['quantity']?>" data-editstatus="<?=htmlspecialchars($row['status'] ?? '');?>"><?=$row['status']?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                    <div class="search-bar">
                        <form method="GET" action="production.php" id="prod-history-filter-form">
                        <input type="text" id="search-box-prod" placeholder="Search..." name="search-prod" value="<?= htmlspecialchars($searchProd) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-prod"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php
                        require_once "php_backend/db.php";
                        // Fetch the search var value.
                        $prodLike = "%{$searchProd}%";
                        $prodDateOpts = $pdo->prepare("SELECT DISTINCT DATE(production_date) AS d FROM production WHERE status = 'Completed' AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY d DESC");
                        $prodDateOpts->bindValue(':search', $prodLike);
                        $prodDateOpts->execute();
                        $prodDates = $prodDateOpts->fetchAll(PDO::FETCH_COLUMN);
                        $prodMonthOpts = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(production_date, '%Y-%m') AS m FROM production WHERE status = 'Completed' AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY m DESC");
                        $prodMonthOpts->bindValue(':search', $prodLike);
                        $prodMonthOpts->execute();
                        $prodMonths = $prodMonthOpts->fetchAll(PDO::FETCH_COLUMN);
                        $prodYearOpts = $pdo->prepare("SELECT DISTINCT YEAR(production_date) AS y FROM production WHERE status = 'Completed' AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search) ORDER BY y DESC");
                        $prodYearOpts->bindValue(':search', $prodLike);
                        $prodYearOpts->execute();
                        $prodYears = $prodYearOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="prod-history-date-filter">Filter by Date:</label>
                        <select id="prod-history-date-filter" name="prod-date" onchange="document.getElementById('prod-history-filter-form').submit()">
                            <option value="all" <?= $prodDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($prodDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $prodDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="prod-history-month-filter">Month:</label>
                        <select id="prod-history-month-filter" name="prod-month" onchange="document.getElementById('prod-history-filter-form').submit()">
                            <option value="all" <?= $prodMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php foreach ($prodMonths as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $prodMonth === $m ? 'selected' : '' ?>><?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="prod-history-year-filter">Year:</label>
                        <select id="prod-history-year-filter" name="prod-year" onchange="document.getElementById('prod-history-filter-form').submit()">
                            <option value="all" <?= $prodYear === 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php foreach ($prodYears as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= (string)$prodYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="prod-history-sort">Sort by:</label>
                        <select id="prod-history-sort" name="prod-sort" onchange="document.getElementById('prod-history-filter-form').submit()">
                            <option value="newest" <?= $prodSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $prodSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $prodSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $prodSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchProd !== '' || $prodDate !== 'all' || $prodMonth !== 'all' || $prodYear !== 'all' || $prodSort !== 'newest'): ?>
                        <a href="production.php" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";
                $prodHistSql = "SELECT batch_id, production_date, item, quantity, unit, status, updated_at FROM production WHERE status = 'Completed'";
                $prodHistParams = [];
                if ($searchProd !== '') {
                    $prodHistSql .= " AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search OR unit LIKE :search)";
                    $prodHistParams[':search'] = "%" . $searchProd . "%";
                }
                if ($prodDate !== 'all') {
                    $prodHistSql .= " AND DATE(production_date) = :pdate";
                    $prodHistParams[':pdate'] = $prodDate;
                }
                if ($prodMonth !== 'all') {
                    $prodHistSql .= " AND DATE_FORMAT(production_date, '%Y-%m') = :pmonth";
                    $prodHistParams[':pmonth'] = $prodMonth;
                }
                if ($prodYear !== 'all') {
                    $prodHistSql .= " AND YEAR(production_date) = :pyear";
                    $prodHistParams[':pyear'] = $prodYear;
                }
                $prodHistSortMap = ['newest' => 'production_date DESC', 'oldest' => 'production_date ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
                $prodHistSql .= " ORDER BY " . $prodHistSortMap[$prodSort];
                $history = $pdo->prepare($prodHistSql);
                foreach ($prodHistParams as $key => $val) {
                    $history->bindValue($key, $val);
                }
                $prodHistRows = [];
                $prodHistError = false;
                try {
                    $history->execute();
                    $prodHistRows = $history->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $prodHistError = true;
                    echo "<tr><td colspan='5'>Search query not found!</td></tr>";
                }
                if (!$prodHistError && empty($prodHistRows)):
                    echo "<tr><td colspan='5'>" . ($searchProd !== '' ? "No batches match '" . htmlspecialchars($searchProd) . "'." : "No completed batches yet.") . "</td></tr>";
                endif;
                ?>
                <div class="table-responsive">
                    <table class="data-table" id="prodHistoryTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Batch ID</th>
                                <th>Quantity</th>
                                <th>Date Created</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prodHistRows as $h_row): ?>
                            <tr>
                                <td><?= htmlspecialchars($h_row['item']); ?></td>
                                <td><?= htmlspecialchars($h_row['batch_id']); ?></td>
                                <td><strong><?= htmlspecialchars($h_row['quantity'] . ' ' . $h_row['unit']); ?></strong></td>
                                <td><?= htmlspecialchars($h_row['production_date']); ?></td>
                                <td><?= htmlspecialchars($h_row['updated_at'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
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
                    <select id="status_id" name="status">
                        <option value="Recent">Recent</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <input type="hidden" name="id">
                <input type="hidden" name="quantityIn">
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
                        const p_quantity = e.currentTarget.dataset.quantitystockin;
                        console.log(p_status);
                        document.getElementById("status_id").value = p_status;
                        document.getElementById('status-diag').showModal();

                        document.querySelector("input[type='hidden'][name='id']").value = p_id;
                        document.querySelector("input[type='hidden'][name='quantityIn']").value = p_quantity;
                       
                    });
                });

                <?php
                if ($feedbackMessage):
                ?>
                document.getElementById('feedback-diag').showModal();
                document.getElementById('message').textContent = '<?=$feedbackMessage?>';
                <?php endif; ?>
            </script><!-- production history filter is server-side (prod-date GET param), no JS filtering needed -->
</body>
</html>
