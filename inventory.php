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
$currentMonth = $_GET['current-month'] ?? 'all';
$currentYear = $_GET['current-year'] ?? 'all';
$currentStatus = $_GET['current-status'] ?? 'all';
$currentSort = $_GET['current-sort'] ?? 'newest';
if ($currentMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $currentMonth)) {
    $currentMonth = 'all';
}
if ($currentYear !== 'all' && !preg_match('/^\d{4}$/', $currentYear)) {
    $currentYear = 'all';
}
if (!in_array($currentStatus, ['all', 'Recent', 'Processing', 'Sorted'], true)) {
    $currentStatus = 'all';
}
if (!in_array($currentSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
    $currentSort = 'newest';
}
$historyMonth = $_GET['history-month'] ?? 'all';
$historyYear = $_GET['history-year'] ?? 'all';
$historySort = $_GET['history-sort'] ?? 'newest';
if ($historyMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $historyMonth)) {
    $historyMonth = 'all';
}
if ($historyYear !== 'all' && !preg_match('/^\d{4}$/', $historyYear)) {
    $historyYear = 'all';
}
if (!in_array($historySort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
    $historySort = 'newest';
}

// Detail chart params (inventory analysis card further down).
$chGran = $_GET['ch-gran'] ?? 'daily';
if (!in_array($chGran, ['daily', 'weekly', 'monthly', 'custom'], true)) {
    $chGran = 'daily';
}
$chN = isset($_GET['ch-n']) ? (int)$_GET['ch-n'] : 7;
if ($chN < 1) {
    $chN = 1;
}
$chToday = date('Y-m-d');
if ($chGran === 'weekly') {
    if ($chN > 26) {
        $chN = 26;
    }
    $chRangeStart = date('Y-m-d', strtotime('monday this week -' . ($chN - 1) . ' weeks'));
    $chRangeEnd = date('Y-m-d', strtotime('sunday this week'));
    if ($chRangeEnd > $chToday) {
        $chRangeEnd = $chToday;
    }
} elseif ($chGran === 'monthly') {
    if ($chN > 24) {
        $chN = 24;
    }
    $chRangeStart = date('Y-m-01', strtotime($chToday . ' -' . ($chN - 1) . ' months'));
    $chRangeEnd = date('Y-m-t', strtotime($chToday));
    if ($chRangeEnd > $chToday) {
        $chRangeEnd = $chToday;
    }
} elseif ($chGran === 'custom') {
    $chFrom = $_GET['ch-from'] ?? '';
    $chTo = $_GET['ch-to'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $chFrom)) {
        $chFrom = date('Y-m-d', strtotime($chToday . ' -6 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $chTo)) {
        $chTo = $chToday;
    }
    if ($chFrom > $chTo) {
        $chTmp = $chFrom;
        $chFrom = $chTo;
        $chTo = $chTmp;
    }
    if ($chTo > $chToday) {
        $chTo = $chToday;
    }
    $chRangeStart = $chFrom;
    $chRangeEnd = $chTo;
    if ((strtotime($chRangeEnd) - strtotime($chRangeStart)) / 86400 > 92) {
        $chRangeStart = date('Y-m-d', strtotime($chRangeEnd . ' -92 days'));
    }
} else {
    if ($chN > 93) {
        $chN = 93;
    }
    $chRangeStart = date('Y-m-d', strtotime($chToday . ' -' . ($chN - 1) . ' days'));
    $chRangeEnd = $chToday;
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
    <script src="assets/node_modules/chart.js/dist/chart.umd.js"></script>
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
                    <label>Unit</label>
<select id="metrics" name="metrics" required>
                <option value="">Type</option>
                <option value="Sacks">Sacks</option>
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
                <option value="Sacks">Sacks</option>
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
                <?php  // TOTAL CURRENT
                require_once "php_backend/db.php";

                $stmt_stock = $pdo->prepare("SELECT SUM(quantity) FROM inventory WHERE status != 'Completed'");
                $stmt_stock->execute();
                $total_current = $stmt_stock->fetchColumn();
                ?>
            <h3 class="notif">Status - None</h3>            
                <script>
                const notif = document.querySelector('.notif');
                    <?php if($total_current >= 100):?>notif.textContent = "Status: Good";<?php endif; ?>
                    <?php if($total_current >= 50 && $total_current < 100):?>notif.textContent = "Status: Sufficient";<?php endif; ?>
                    <?php if($total_current < 50 && $total_current >= 20):?>notif.textContent = "Status: Ok";<?php endif; ?>
                    <?php if($total_current < 20 && $total_current >= 8):?>notif.textContent = "Status: Low Stock"; notif.color = orange;<?php endif; ?>
                    <?php if($total_current < 8):?>notif.textContent = "Status: Critically Low Stock!"; notif.color = red;<?php endif; ?>
                    <?php if($total_current === 0):?>notif.textContent = "Status: No Stock!"; notif.color = red;<?php endif; ?>                
                </script>
<!--Inventory head Summary-->
        <div class="info-cards">
            <div class="stat-card">
                <h2>Current Stock</h2>
                <h3><?=htmlspecialchars($total_current) ?? 0?> Sacks</h3>
            </div>

            <div class="stat-card">
                <?php 
                $stmt_produced = $pdo->prepare("SELECT SUM(quantity) FROM production");
                $stmt_produced->execute();
                $total_produced = $stmt_produced->fetchColumn();  
                ?>
                <h2>Total Produced</h2>
                <h3><?=$total_produced ?? 0 ?> Sacks</h3>
            </div>

            <div class="stat-card">
                <?php 
                $stmt_stockout = $pdo->prepare("SELECT SUM(quantity) FROM inventory WHERE status = 'Completed'");
                $stmt_stockout->execute();
                $total_stockout = $stmt_stockout->fetchColumn();  
                ?>
                <h2>Total Stock-out</h2>
                <h3><?=$total_stockout?> Sacks</h3>
            </div>
        </div> <!--Inventory head Summary END-->

        <!-- Inventory Analysis Chart -->
        <?php
        // Detailed analysis: production inflow + stock-out + ending balance per bucket.
        // Daily maps first (same aggregates as Movements), then bucketed.
        // Balances reconstructed backward from current stock (approximation).
        $chStmtIn = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM production WHERE status != 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
        $chStmtIn->execute([':start' => $chRangeStart . ' 00:00:00', ':end' => $chToday . ' 23:59:59']);
        $chIn = [];
        while ($ch_row = $chStmtIn->fetch(PDO::FETCH_ASSOC)) {
            $chIn[$ch_row['day']] = (int)$ch_row['total_qty'];
        }
        $chStmtOut = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
        $chStmtOut->execute([':start' => $chRangeStart . ' 00:00:00', ':end' => $chToday . ' 23:59:59']);
        $chOut = [];
        while ($ch_row = $chStmtOut->fetch(PDO::FETCH_ASSOC)) {
            $chOut[$ch_row['day']] = (int)$ch_row['total_qty'];
        }
        $chBuckets = [];
        if ($chGran === 'weekly') {
            $chWk = $chRangeStart;
            while ($chWk <= $chRangeEnd) {
                $chWkEnd = date('Y-m-d', strtotime($chWk . ' +6 days'));
                if ($chWkEnd > $chRangeEnd) {
                    $chWkEnd = $chRangeEnd;
                }
                $chBuckets[] = ['label' => 'Wk ' . date('M d', strtotime($chWk)), 'start' => $chWk, 'end' => $chWkEnd];
                $chWk = date('Y-m-d', strtotime($chWk . ' +7 days'));
            }
        } elseif ($chGran === 'monthly') {
            $chMo = substr($chRangeStart, 0, 7);
            $chMoEnd = substr($chRangeEnd, 0, 7);
            while ($chMo <= $chMoEnd) {
                $chMoStart = $chMo . '-01';
                if ($chMoStart < $chRangeStart) {
                    $chMoStart = $chRangeStart;
                }
                $chMoLast = date('Y-m-t', strtotime($chMo . '-01'));
                if ($chMoLast > $chRangeEnd) {
                    $chMoLast = $chRangeEnd;
                }
                $chBuckets[] = ['label' => date('M Y', strtotime($chMo . '-01')), 'start' => $chMoStart, 'end' => $chMoLast];
                $chMo = date('Y-m', strtotime($chMo . '-01 +1 month'));
            }
        } else {
            $chDd = $chRangeStart;
            while ($chDd <= $chRangeEnd) {
                $chBuckets[] = ['label' => date('M d', strtotime($chDd)), 'start' => $chDd, 'end' => $chDd];
                $chDd = date('Y-m-d', strtotime($chDd . ' +1 day'));
            }
        }
        $chLabels = [];
        $chProd = [];
        $chOutPts = [];
        $chBalPts = [];
        $chBal = (int)$total_current;
        for ($chBi = count($chBuckets) - 1; $chBi >= 0; $chBi--) {
            $chBIn = 0;
            $chBOut = 0;
            $chBd = $chBuckets[$chBi]['start'];
            while ($chBd <= $chBuckets[$chBi]['end']) {
                $chBIn += $chIn[$chBd] ?? 0;
                $chBOut += $chOut[$chBd] ?? 0;
                $chBd = date('Y-m-d', strtotime($chBd . ' +1 day'));
            }
            $chBuckets[$chBi]['bal'] = $chBal;
            $chBal = $chBal - $chBIn + $chBOut;
            $chBuckets[$chBi]['in'] = $chBIn;
            $chBuckets[$chBi]['out'] = $chBOut;
        }
        foreach ($chBuckets as $chB) {
            $chLabels[] = $chB['label'];
            $chProd[] = $chB['in'];
            $chOutPts[] = $chB['out'];
            $chBalPts[] = $chB['bal'];
        }
        $salesGoalFileInv = __DIR__ . '/php_backend/sales_goal.php';
        $salesGoalInv = file_exists($salesGoalFileInv) ? max(0, (int)include $salesGoalFileInv) : 50;
        ?>
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-chart-line"></i> Inventory Analysis</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="inventory.php" id="chart-filter-form">
                        <?php if ($searchInv !== '' && $searchInv !== '%%'): ?>
                        <input type="hidden" name="search-inv" value="<?= htmlspecialchars(trim($_GET['search-inv'] ?? '')) ?>">
                        <?php endif; ?>
                        <?php if ($currentDate !== 'all'): ?>
                        <input type="hidden" name="current-date" value="<?= htmlspecialchars($currentDate) ?>">
                        <?php endif; ?>
                        <?php if ($searchHistory !== ''): ?>
                        <input type="hidden" name="search-history" value="<?= htmlspecialchars($searchHistory) ?>">
                        <?php endif; ?>
                        <?php if ($historyDate !== 'all'): ?>
                        <input type="hidden" name="history-date" value="<?= htmlspecialchars($historyDate) ?>">
                        <?php endif; ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="chart-gran">View:</label>
                        <select id="chart-gran" name="ch-gran" onchange="document.getElementById('chart-filter-form').submit()">
                            <option value="daily" <?= $chGran === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= $chGran === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= $chGran === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="custom" <?= $chGran === 'custom' ? 'selected' : '' ?>>Custom</option>
                        </select>
                        <label for="chart-n">Last:</label>
                        <input type="number" id="chart-n" name="ch-n" min="1" max="93" value="<?= htmlspecialchars($chN) ?>" style="width:64px;">
                        <label for="chart-from">From:</label>
                        <input type="date" id="chart-from" name="ch-from" value="<?= $chGran === 'custom' ? htmlspecialchars($chRangeStart) : '' ?>">
                        <label for="chart-to">To:</label>
                        <input type="date" id="chart-to" name="ch-to" value="<?= $chGran === 'custom' ? htmlspecialchars($chRangeEnd) : '' ?>">
                        <button type="submit" class="btn-secondary" style="padding:6px 10px;">Apply</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div style="height: 320px;"><canvas id="levelChart"></canvas></div>
            </div>
        </div>
        <script>
        const chLabels = <?= json_encode($chLabels) ?>;
        const chProd = <?= json_encode($chProd) ?>;
        const chOut = <?= json_encode($chOutPts) ?>;
        const chBal = <?= json_encode($chBalPts) ?>;
        new Chart(document.getElementById('levelChart'), {
            data: { labels: chLabels, datasets: [
                { type: 'bar', label: 'Production', data: chProd, backgroundColor: 'rgba(34,197,94,0.6)' },
                { type: 'bar', label: 'Stock-Out', data: chOut, backgroundColor: 'rgba(239,68,68,0.6)' },
                { type: 'line', label: 'Ending Balance (Sacks)', data: chBal, borderColor: '#22c55e', tension: 0.3, pointRadius: 3 }
            ]},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

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
                        <?php if ($chGran !== 'daily'): ?>
                        <input type="hidden" name="ch-gran" value="<?= htmlspecialchars($chGran) ?>">
                        <?php endif; ?>
                        <?php if ($chN != 7): ?>
                        <input type="hidden" name="ch-n" value="<?= htmlspecialchars($chN) ?>">
                        <?php endif; ?>
                        <?php if ($chGran === 'custom'): ?>
                        <input type="hidden" name="ch-from" value="<?= htmlspecialchars($chRangeStart) ?>">
                        <input type="hidden" name="ch-to" value="<?= htmlspecialchars($chRangeEnd) ?>">
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
                        $monthOpts = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS m FROM inventory WHERE status != 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search) ORDER BY m DESC");
                        $monthOpts->bindValue(':search', $searchInv);
                        $monthOpts->execute();
                        $currentMonths = $monthOpts->fetchAll(PDO::FETCH_COLUMN);
                        $yearOpts = $pdo->prepare("SELECT DISTINCT YEAR(created_at) AS y FROM inventory WHERE status != 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search) ORDER BY y DESC");
                        $yearOpts->bindValue(':search', $searchInv);
                        $yearOpts->execute();
                        $currentYears = $yearOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="current-date-filter">Filter by Date:</label>
                        <select id="current-date-filter" name="current-date" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="all" <?= $currentDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($currentDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $currentDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="current-month-filter">Month:</label>
                        <select id="current-month-filter" name="current-month" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="all" <?= $currentMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php foreach ($currentMonths as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $currentMonth === $m ? 'selected' : '' ?>><?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="current-year-filter">Year:</label>
                        <select id="current-year-filter" name="current-year" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="all" <?= $currentYear === 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php foreach ($currentYears as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= (string)$currentYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="current-status-filter">Status:</label>
                        <select id="current-status-filter" name="current-status" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="all" <?= $currentStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Recent" <?= $currentStatus === 'Recent' ? 'selected' : '' ?>>Recent</option>
                            <option value="Processing" <?= $currentStatus === 'Processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="Sorted" <?= $currentStatus === 'Sorted' ? 'selected' : '' ?>>Sorted</option>
                        </select>
                        <label for="current-sort">Sort by:</label>
                        <select id="current-sort" name="current-sort" onchange="document.getElementById('current-filter-form').submit()">
                            <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $currentSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $currentSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchInv !== '' || $currentDate !== 'all' || $currentMonth !== 'all' || $currentYear !== 'all' || $currentStatus !== 'all' || $currentSort !== 'newest'): ?>
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
                if ($currentMonth !== 'all') {
                    $currentSql .= " AND DATE_FORMAT(created_at, '%Y-%m') = :cmonth";
                    $currentParams[':cmonth'] = $currentMonth;
                }
                if ($currentYear !== 'all') {
                    $currentSql .= " AND YEAR(created_at) = :cyear";
                    $currentParams[':cyear'] = $currentYear;
                }
                if ($currentStatus !== 'all') {
                    $currentSql .= " AND status = :cstatus";
                    $currentParams[':cstatus'] = $currentStatus;
                }
                $sortMap = ['newest' => 'created_at DESC', 'oldest' => 'created_at ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
                $currentSql .= " ORDER BY " . $sortMap[$currentSort];
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
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-arrow"></i> Recent Inventory Movements</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Quantity</th>
                                <th>Stocks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Last 3 days net movements (newest first).
                            // Inflow = production touches (status != Completed) by DATE(updated_at).
                            // Outflow = inventory Completed rows by DATE(updated_at).
                            // Balance is reconstructed backward from current stock,
                            // so it is an approximation, not an exact ledger.
                            $moveStmtIn = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production WHERE DATE(updated_at) = :d AND status != 'Completed'");
                            $moveStmtOut = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE DATE(updated_at) = :d AND status = 'Completed'");
                            $moveDays = [];
                            for ($m = 0; $m < 3; $m++) {
                                $moveDay = date('Y-m-d', strtotime("today -$m days"));
                                $moveStmtIn->execute([':d' => $moveDay]);
                                $moveIn = (int)$moveStmtIn->fetchColumn();
                                $moveStmtOut->execute([':d' => $moveDay]);
                                $moveOut = (int)$moveStmtOut->fetchColumn();
                                if ($moveIn != 0 || $moveOut != 0) {
                                    $moveDays[] = ['day' => $moveDay, 'net' => $moveIn - $moveOut];
                                }
                            }
                            $moveBal = (int)$total_current;
                            foreach ($moveDays as &$move) {
                                $move['bal'] = $moveBal;
                                $moveBal = $moveBal - $move['net'];
                            }
                            unset($move);
                            if (empty($moveDays)):
                            ?>
                            <tr><td colspan="4">No recent movements.</td></tr>
                            <?php else: ?>
                            <?php foreach ($moveDays as $move): ?>
                            <tr>
                                <td><?= date('M d', strtotime($move['day'])) ?></td>
                                <td><?= $move['net'] >= 0 ? 'Production' : 'Stock-Out' ?></td>
                                <td><?= ($move['net'] >= 0 ? '+' : '') . $move['net'] ?></td>
                                <td><?= htmlspecialchars($move['bal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
                        <?php if ($chGran !== 'daily'): ?>
                        <input type="hidden" name="ch-gran" value="<?= htmlspecialchars($chGran) ?>">
                        <?php endif; ?>
                        <?php if ($chN != 7): ?>
                        <input type="hidden" name="ch-n" value="<?= htmlspecialchars($chN) ?>">
                        <?php endif; ?>
                        <?php if ($chGran === 'custom'): ?>
                        <input type="hidden" name="ch-from" value="<?= htmlspecialchars($chRangeStart) ?>">
                        <input type="hidden" name="ch-to" value="<?= htmlspecialchars($chRangeEnd) ?>">
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
                        $monthOpts = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS m FROM inventory WHERE status = 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search OR status LIKE :search) ORDER BY m DESC");
                        $monthOpts->bindValue(':search', $historyLike);
                        $monthOpts->execute();
                        $historyMonths = $monthOpts->fetchAll(PDO::FETCH_COLUMN);
                        $yearOpts = $pdo->prepare("SELECT DISTINCT YEAR(created_at) AS y FROM inventory WHERE status = 'Completed' AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search OR status LIKE :search) ORDER BY y DESC");
                        $yearOpts->bindValue(':search', $historyLike);
                        $yearOpts->execute();
                        $historyYears = $yearOpts->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-filter"></i>
                        <label for="history-date-filter">Filter by Date:</label>
                        <select id="history-date-filter" name="history-date" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $historyDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($historyDates as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $historyDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="history-month-filter">Month:</label>
                        <select id="history-month-filter" name="history-month" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $historyMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php foreach ($historyMonths as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $historyMonth === $m ? 'selected' : '' ?>><?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="history-year-filter">Year:</label>
                        <select id="history-year-filter" name="history-year" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $historyYear === 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php foreach ($historyYears as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= (string)$historyYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="history-sort">Sort by:</label>
                        <select id="history-sort" name="history-sort" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="newest" <?= $historySort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $historySort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $historySort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $historySort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchHistory !== '' || $historyDate !== 'all' || $historyMonth !== 'all' || $historyYear !== 'all' || $historySort !== 'newest'): ?>
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
                if ($historyMonth !== 'all') {
                    $historySql .= " AND DATE_FORMAT(created_at, '%Y-%m') = :hmonth";
                    $historyParams[':hmonth'] = $historyMonth;
                }
                if ($historyYear !== 'all') {
                    $historySql .= " AND YEAR(created_at) = :hyear";
                    $historyParams[':hyear'] = $historyYear;
                }
                $historySortMap = ['newest' => 'created_at DESC', 'oldest' => 'created_at ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
                $historySql .= " ORDER BY " . $historySortMap[$historySort];
                $stmt = $pdo->prepare($historySql);
                foreach ($historyParams as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                ?>
                </div>
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