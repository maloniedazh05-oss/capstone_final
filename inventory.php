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
$currentSort = $_GET['current-sort'] ?? 'newest';
if ($currentMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $currentMonth)) {
    $currentMonth = 'all';
}
if ($currentYear !== 'all' && !preg_match('/^\d{4}$/', $currentYear)) {
    $currentYear = 'all';
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
$chN = isset($_GET['ch-n']) ? (int) $_GET['ch-n'] : 7;
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
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    <?php $NEED_CHART = true;
    require_once "php_backend/head_assets.php"; ?>
</head>

<body>
    <?php require_once "main-sidebar.php"; ?>

    <!-- Update/Edit Total Stock Dialog START -->
<dialog id="item-diag">

    <div class="dialog-header">
        <h3>
            <i class="fa-solid fa-pen-to-square"></i>
            Update/Edit Total Stock
        </h3>
    </div>

    <?php
    $totalStmt = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
    $totalStmt->execute();
    $dialogTotal = (float) ($totalStmt->fetchColumn() ?? 0);

    // 1 sack = 50 kilograms
    $KG_PER_SACK = 50;
    ?>

    <div class="dialog-body">

        <form method="POST" action="php_backend/insertItem.php">

            <div class="form-group" style="margin-bottom: 12px;">

                <!-- Current stock -->
                <div style="margin-bottom: 8px;">
                    Total Stock:
                    <strong><?= $dialogTotal ?> Sacks</strong>
                    <small>
                        (<?= number_format($dialogTotal * $KG_PER_SACK, 0) ?> KG)
                    </small>
                </div>

                <!-- Amount -->
                <label for="deduct-quantity">
                    Amount to Deduct:
                </label>

                <input
    type="number"
    id="deduct-quantity"
    name="quantity"
    min="1"
    step="1"
    required
>

                <!-- Unit -->
                <label for="deduct-unit" style="margin-top: 8px;">
                    Unit:
                </label>

                <select
    id="deduct-unit"
    name="unit"
    required
    onchange="updateDeductLimit(this.value)"
>
    <option value="">Select Unit</option>
    <option value="KG">KG</option>
    <option value="Sacks">Sacks</option>
</select>
<script>
function updateDeductLimit(unit) {

    const quantityInput = document.getElementById('deduct-quantity');
    const helpText = document.getElementById('deduct-help');

    const totalSacks = <?= $dialogTotal ?>;
    const KG_PER_SACK = 50;

    if (unit === 'KG') {

        const maxKG = totalSacks * KG_PER_SACK;

        quantityInput.max = maxKG;

        helpText.textContent =
            'Amount to remove (KG). Cannot exceed ' +
            maxKG.toLocaleString() +
            ' KG (' +
            totalSacks +
            ' Sacks).';

    } else if (unit === 'Sacks') {

        quantityInput.max = totalSacks;

        helpText.textContent =
            'Amount to remove (Sacks). Cannot exceed ' +
            totalSacks +
            ' Sacks.';

    } else {

        quantityInput.removeAttribute('max');

        helpText.textContent =
            'Select a unit.';
    }
}
</script>

                <!-- Dynamic explanation -->
                <div
                    id="deduct-help"
                    style="margin-top: 6px; font-size: 0.85rem; color: #666;"
                >
                   
                </div>

            </div>

            <!-- Receiver -->
            <details class="dialog-details">

                <summary class="summaries">
                    Receiver Name
                </summary>

                <div class="form-group" style="margin-top: 8px;">

                    <label>Receipt</label>

                    <textarea
                        placeholder="Enter Company/Client"
                        name="description"
                        class="desc"
                    ></textarea>

                </div>

            </details>

            <div class="dialog-actions">

                <button
                    type="button"
                    class="btn-secondary"
                    command="close"
                    commandfor="item-diag"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary"
                >
                    <i class="fa-solid fa-minus"></i>
                    Deduct Stock
                </button>

            </div>

        </form>

    </div>

</dialog>
<!-- Update/Edit Total Stock Dialog END -->

    <!-- Feedback dialog -->
    <dialog id="feedback-diag">
        <div class="dialog-header">
            <h3><i class="fa-solid fa-circle-info"></i> Notice</h3>
        </div>
        <div class="dialog-body">
            <div id="message" style="margin-bottom: 16px; color: var(--color-text-main);">Nothing to see here..</div>
            <div class="dialog-actions" style="justify-content: center;">
                <button type="button" class="btn-primary" command="close" commandfor="feedback-diag"
                    onclick="window.location.href='inventory.php'">Close</button>
            </div>
        </div>
    </dialog>
    <div class="inventorypage">
        <div class="page-header">
            <h1>Inventory Management</h1>
        </div>
       
        <?php  // TOTAL CURRENT
        //require_once "php_backend/db.php";

        $stmt_stock = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
        $stmt_stock->execute();
        $total_current = round((float) ($stmt_stock->fetchColumn() ?? 0), 2);
        $totalFmt = rtrim(rtrim(number_format($total_current, 2, '.', ''), '0'), '.');
        ?>
        <div id="notif" class="notif">
            <i class="fa-solid fa-circle-info"></i>
            <span id="notif-text">Checking stock status...</span>
        </div>
        <script>
            (function () {
                const notif = document.getElementById('notif');
                <?php if ($total_current >= 100): ?>
                    notif.className = 'notif notif-green';
                    notif.style.color = '#15803d';
                    notif.innerHTML =
                        '<i class="fa-solid fa-circle-check"></i> Status: Good (<?= $totalFmt ?> Sacks Available)';
                <?php elseif ($total_current >= 50): ?>
                    notif.className = 'notif notif-good';
                    notif.style.color = '#15803d';
                    notif.innerHTML =
                        '<i class="fa-solid fa-circle-info"></i> Status: Sufficient (<?= $totalFmt ?> Sacks Available)';
                <?php elseif ($total_current >= 20): ?>
                    notif.className = 'notif notif-good';
                    notif.style.color = '#15803d';
                    notif.innerHTML =
                        '<i class="fa-solid fa-circle-info"></i> Status: OK (<?= $totalFmt ?> Sacks Available)';
                <?php elseif ($total_current >= 8): ?>
                    notif.className = 'notif notif-orange';
                    notif.style.color = 'orange';
                    notif.innerHTML =
                        '<i class="fa-solid fa-triangle-exclamation"></i> Status: Low Stock (<?= $totalFmt ?> Sacks Left)';
                <?php elseif ($total_current > 0): ?>
                    notif.className = 'notif notif-red';
                    notif.style.color = 'red';
                    notif.innerHTML =
                        '<i class="fa-solid fa-triangle-exclamation"></i> Status: Critically Low Stock! (<?= $totalFmt ?> Sacks Left)';
                <?php else: ?>
                    notif.className = 'notif notif-red';
                    notif.style.color = 'red';
                    notif.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Status: No Stock Available!';
                <?php endif; ?>
            })();
        </script>

       <!--Inventory head Summary-->
        <div class="info-cards">
            <div class="stat-card stat-card-green">
                <h2>Current Stock</h2>
                <h3 class="conv-val" data-sacks="<?= $total_current ?>" data-unit="Sacks"><?= $totalFmt ?> Sacks</h3>
                <div class="stat-sub">Available now</div>
            </div>

            <div class="stat-card stat-card-blue">
                <?php
                $stmt_produced = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production WHERE status = 'Completed' AND YEAR(updated_at) = YEAR(CURDATE()) AND MONTH(updated_at) = MONTH(CURDATE())");
                $stmt_produced->execute();
                $total_produced = $stmt_produced->fetchColumn();
                ?>
                <h2>Total Produced</h2>
                <h3 class="conv-val" data-sacks="<?= round((float)($total_produced ?? 0), 2) ?>" data-unit="Sacks"><?= rtrim(rtrim(number_format((float) ($total_produced ?? 0), 2, '.', ''), '0'), '.') ?> Sacks</h3>
                <div class="stat-sub">This month</div>
                <div class="stat-desc">Production records within selected period</div>
            </div>

            <div class="stat-card stat-card-amber">
                <?php
                $stmt_released = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM history WHERE action IN ('Stock Deducted', 'Stock-Out Recorded') AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
                $stmt_released->execute();
                $total_released = $stmt_released->fetchColumn();
                ?>
                <h2>Total Released</h2>
                <h3 class="conv-val" data-sacks="<?= round((float)($total_released ?? 0), 2) ?>" data-unit="Sacks"><?= rtrim(rtrim(number_format((float) ($total_released ?? 0), 2, '.', ''), '0'), '.') ?> Sacks</h3>
                <div class="stat-sub">This month</div>
                <div class="stat-desc">Quantity issued or sold</div>
            </div>

            <div class="stat-card stat-card-purple">
                <?php
                $stmt_today = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production WHERE status = 'Completed' AND DATE(updated_at) = CURDATE()");
                $stmt_today->execute();
                $total_today = $stmt_today->fetchColumn();
                ?>
                <h2>Production Today</h2>
                <h3 class="conv-val" data-sacks="<?= round((float)($total_today ?? 0), 2) ?>" data-unit="Sacks"><?= rtrim(rtrim(number_format((float) ($total_today ?? 0), 2, '.', ''), '0'), '.') ?> Sacks</h3>
                <div class="stat-sub">Today</div>
            </div>
        </div>
        <!--Inventory head Summary END-->

        <!-- Inventory Analysis Chart -->
        <?php
        // Detailed analysis: production inflow + stock-out + ending balance per bucket.
        // Daily maps first (same aggregates as Movements), then bucketed.
        // Balances reconstructed backward from current stock (approximation).
        $chStmtIn = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM production WHERE status != 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
        $chStmtIn->execute([':start' => $chRangeStart . ' 00:00:00', ':end' => $chToday . ' 23:59:59']);
        $chIn = [];
        while ($ch_row = $chStmtIn->fetch(PDO::FETCH_ASSOC)) {
            $chIn[$ch_row['day']] = (int) $ch_row['total_qty'];
        }
        $chStmtOut = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
        $chStmtOut->execute([':start' => $chRangeStart . ' 00:00:00', ':end' => $chToday . ' 23:59:59']);
        $chOut = [];
        while ($ch_row = $chStmtOut->fetch(PDO::FETCH_ASSOC)) {
            $chOut[$ch_row['day']] = (int) $ch_row['total_qty'];
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
        $chBal = (int) $total_current;
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
        $salesGoalInv = file_exists($salesGoalFileInv) ? max(0, (int) include $salesGoalFileInv) : 50;
        ?>
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-chart-line"></i> Inventory Analysis</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="inventory.php" id="chart-filter-form">
                            <?php if ($searchInv !== '' && $searchInv !== '%%'): ?>
                                <input type="hidden" name="search-inv"
                                    value="<?= htmlspecialchars(trim($_GET['search-inv'] ?? '')) ?>">
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
                            <select id="chart-gran" name="ch-gran"
                                onchange="document.getElementById('chart-filter-form').submit()">
                                <option value="daily" <?= $chGran === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $chGran === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $chGran === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="custom" <?= $chGran === 'custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                            <label for="chart-n">Last:</label>
                            <input type="number" id="chart-n" name="ch-n" min="1" max="93"
                                value="<?= htmlspecialchars($chN) ?>"
                                style="width:96px;padding:9px 10px;font-size:0.95rem;">
                            <label for="chart-from">From:</label>
                            <input type="date" id="chart-from" name="ch-from"
                                value="<?= $chGran === 'custom' ? htmlspecialchars($chRangeStart) : '' ?>"
                                style="padding:9px 10px;font-size:0.95rem;min-width:170px;">
                            <label for="chart-to">To:</label>
                            <input type="date" id="chart-to" name="ch-to"
                                value="<?= $chGran === 'custom' ? htmlspecialchars($chRangeEnd) : '' ?>"
                                style="padding:9px 10px;font-size:0.95rem;min-width:170px;">
                            <button type="submit" class="btn-primary">Apply</button>
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
                data: {
                    labels: chLabels,
                    datasets: [{
                        type: 'bar',
                        label: 'Production',
                        data: chProd,
                        backgroundColor: 'rgba(34,197,94,0.6)'
                    },
                    {
                        type: 'bar',
                        label: 'Stock-Out',
                        data: chOut,
                        backgroundColor: 'rgba(239,68,68,0.6)'
                    },
                    {
                        type: 'line',
                        label: 'Ending Balance (Sacks)',
                        data: chBal,
                        borderColor: '#22c55e',
                        tension: 0.3,
                        pointRadius: 3
                    }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sacks'
                            }
                        }
                    }
                }
            });
        </script>

        <!-- Card 1: Current Stock Levels -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-list-ul"></i>Stocks</h2>
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
                            <input type="text" id="search-box-inv" placeholder="Search..." name="search-inv"
                                value="<?= htmlspecialchars($searchInv) ?>">
                            <!--Set the value of search bar for consistent memory-->
                            <button type="submit" id="search-btn-inv"><i
                                    class="fa-solid fa-magnifying-glass"></i></button>

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
                            <select id="current-date-filter" name="current-date"
                                onchange="document.getElementById('current-filter-form').submit()">
                                <option value="all" <?= $currentDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                                <?php foreach ($currentDates as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>" <?= $currentDate === $d ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="current-month-filter">Month:</label>
                            <select id="current-month-filter" name="current-month"
                                onchange="document.getElementById('current-filter-form').submit()">
                                <option value="all" <?= $currentMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                                <?php foreach ($currentMonths as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>" <?= $currentMonth === $m ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="current-year-filter">Year:</label>
                            <select id="current-year-filter" name="current-year"
                                onchange="document.getElementById('current-filter-form').submit()">
                                <option value="all" <?= $currentYear === 'all' ? 'selected' : '' ?>>All Years</option>
                                <?php foreach ($currentYears as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>" <?= (string) $currentYear === (string) $y ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($y) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="current-sort">Sort by:</label>
                            <select id="current-sort" name="current-sort"
                                onchange="document.getElementById('current-filter-form').submit()">
                                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Newest
                                </option>
                                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest
                                </option>
                                <option value="highest" <?= $currentSort === 'highest' ? 'selected' : '' ?>>Highest
                                    quantity</option>
                                <option value="lowest" <?= $currentSort === 'lowest' ? 'selected' : '' ?>>Lowest
                                    quantity</option>
                            </select>
                            <?php if ($searchInv !== '' || $currentDate !== 'all' || $currentMonth !== 'all' || $currentYear !== 'all' || $currentSort !== 'newest'): ?>
                                <a href="inventory.php<?= ($searchHistory !== '' || $historyDate !== 'all') ? '?search-history=' . urlencode($searchHistory) . '&history-date=' . urlencode($historyDate) : '' ?>"
                                    class="btn-secondary" style="text-decoration:none;">Clear</a>
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
                <?php else: ?>
                    <?php
                    $currentSql = "SELECT * FROM inventory WHERE status != 'Completed'";
                    $currentParams = [];
                    if ($searchInv !== '') {
                        $currentSql .= " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search)";
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
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Description</th>
                                    <th>Date Created</th>
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
                                        <td class="stock-level-cell"><strong><?= htmlspecialchars($row['quantity']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($row['unit']) ?></td>
                                        <td class="record-note"><?= htmlspecialchars($row['description'] ?: 'None') ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div> <!-- Card 1 END -->

        <!-- Card 2: Transactions -->
        <?php if ($prod): ?>
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-circle-plus"></i>Transactions</h2>
                </div>
                <div class="card-body">
                    <p class="section-desc">Deduct from the total stock.</p>
                    <button type="button" class="btn-primary" command="show-modal" commandfor="item-diag">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Total Stock
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
                                $moveIn = (int) $moveStmtIn->fetchColumn();
                                $moveStmtOut->execute([':d' => $moveDay]);
                                $moveOut = (int) $moveStmtOut->fetchColumn();
                                if ($moveIn != 0 || $moveOut != 0) {
                                    $moveDays[] = ['day' => $moveDay, 'net' => $moveIn - $moveOut];
                                }
                            }
                            $moveBal = (int) $total_current;
                            foreach ($moveDays as &$move) {
                                $move['bal'] = $moveBal;
                                $moveBal = $moveBal - $move['net'];
                            }
                            unset($move);
                            if (empty($moveDays)):
                                ?>
                                <tr>
                                    <td colspan="4">No recent movements.</td>
                                </tr>
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

        <!-- Card 3: History START-->
        <div class="card-body"> 
        
<thead>
    <tr>
        <th>Transaction</th>
        <th>Quantity</th>
        <th>Unit</th>
        <th>Date Created</th>
    </tr>
</thead>

<tbody> 

<?php if (empty($historyRows)): ?>

    <tr>
        <td colspan="4">
            No deducted transactions yet.
        </td>
    </tr>

<?php else: ?>

    <?php foreach ($historyRows as $h_row): ?>

        <tr>

            <td>
                <strong>
                    <?= htmlspecialchars($h_row['action']) ?>
                </strong>
            </td>

            <td>
                <?= htmlspecialchars($h_row['quantity']) ?>
            </td>

            <td>
                <?= htmlspecialchars($h_row['unit']) ?>
            </td>

            <td>
                <?= htmlspecialchars($h_row['created_at']) ?>
            </td>

        </tr>

    <?php endforeach; ?>

<?php endif; ?>

</tbody>
    <?php
    require_once "php_backend/db.php";


    $historySql = "
    SELECT
        id,
        action,
        ref_id,
        product,
        quantity,
        unit,
        created_at
    FROM history
    WHERE action = 'Stock Deducted'
";

    $historyParams = [];

    // Search
    if ($searchHistory !== '') {
        $historySql .= "
            AND (
                CAST(ref_id AS CHAR) LIKE :search
                OR action LIKE :search
                OR product LIKE :search
                OR description LIKE :search
                OR unit LIKE :search
            )
        ";

        $historyParams[':search'] = "%" . $searchHistory . "%";
    }

    // Date
    if ($historyDate !== 'all') {
        $historySql .= " AND DATE(created_at) = :hdate";
        $historyParams[':hdate'] = $historyDate;
    }

    // Month
    if ($historyMonth !== 'all') {
        $historySql .= " AND DATE_FORMAT(created_at, '%Y-%m') = :hmonth";
        $historyParams[':hmonth'] = $historyMonth;
    }

    // Year
    if ($historyYear !== 'all') {
        $historySql .= " AND YEAR(created_at) = :hyear";
        $historyParams[':hyear'] = $historyYear;
    }

    // Sorting
    $historySortMap = [
        'newest' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'highest' => 'quantity DESC',
        'lowest' => 'quantity ASC'
    ];

    $historySql .= " ORDER BY " . $historySortMap[$historySort];

    $historyStmt = $pdo->prepare($historySql);

    foreach ($historyParams as $key => $value) {
        $historyStmt->bindValue($key, $value);
    }

    $historyStmt->execute();

    $historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="table-responsive">
<div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-arrow"></i> Recent Inventory Movements</h2>
        <table class="data-table" id="historyTable">

            <thead>
                <tr>
                   <th>Transaction</th>
                    <th>Product</th>
                    <th>Quantity</th>
                   <th>Unit</th>
                   <th>Date Created</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($historyRows)): ?>

                    <tr>
                        <td colspan="5">
                            <?php
                            if (
                                $searchHistory !== '' ||
                                $historyDate !== 'all' ||
                                $historyMonth !== 'all' ||
                                $historyYear !== 'all'
                            ) {
                                echo "No history matches your search/filter.";
                            } else {
                                echo "No transactions yet.";
                            }
                            ?>
                        </td>
                    </tr>

                <?php else: ?>
<div class="card-body">
                    <?php foreach ($historyRows as $h_row): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($h_row['action']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($h_row['quantity']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($h_row['unit']) ?>
                            </td>

                            <td>
    <?= htmlspecialchars($h_row['product'] ?? 'Stock') ?>
</td>

                            <td>
                                <?= htmlspecialchars($h_row['created_at']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
            <!--Content Card End, History END-->
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";

                if (!$prod):
                    ?>
                    <p class="section-desc">No completed items yet.</p>
                <?php else: ?>
                    <?php
                    $historySql = "SELECT * FROM inventory WHERE status = 'Completed'";
                    $historyParams = [];
                    if ($searchHistory !== '') {
                        $historySql .= " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search)";
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
                                <tr>
                                    <td colspan="5">
                                        <?= ($searchHistory !== '' || $historyDate !== 'all') ? "No history matches your search/filter." : "No completed items yet." ?>
                                    </td>
                                </tr>
                            <?php else: ?> 
                                <?php foreach ($historyRows as $h_row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h_row['product']) ?></td>
                                        <td><strong><?= htmlspecialchars($h_row['quantity']) ?></strong></td>
                                        <td><?= htmlspecialchars($h_row['unit']) ?></td>
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
document.addEventListener('DOMContentLoaded', function () {

    const unitSelect = document.getElementById('deduct-unit');
    const quantityInput = document.getElementById('deduct-quantity');

    const totalSacks = <?= $dialogTotal ?>;
    const KG_PER_SACK = 50;

    function updateMaximum() {

        if (unitSelect.value === 'KG') {

            // Example: 52 sacks × 50 kg = 2600 kg
            quantityInput.max = totalSacks * KG_PER_SACK;

        } else {

            // Sacks
            quantityInput.max = totalSacks;
        }

        quantityInput.value = '';
    }

    unitSelect.addEventListener('change', updateMaximum);

    updateMaximum();
});
// =====================================================
// TOTAL STOCK DEDUCTION UNIT CONVERSION
// New hover js convert data
// 1 SACK = 50 KG 
// =====================================================

const deductUnit = document.getElementById('deduct-unit');
const deductQuantity = document.getElementById('deduct-quantity');
const deductHelp = document.getElementById('deduct-help');

const totalSacks = <?= $dialogTotal ?>;
const KG_PER_SACK = 50;

if (deductUnit && deductQuantity) {

    deductUnit.addEventListener('change', function () {

        if (this.value === 'KG') {
            // 52 sacks × 50 KG = 2600 KG
            const maxKg = totalSacks * KG_PER_SACK;

            deductQuantity.max = maxKg;
            deductQuantity.step = '1';

            deductHelp.textContent =
                'Amount to remove (KG). Maximum: ' +
                maxKg +
                ' KG (' +
                totalSacks +
                ' Sacks).';

        }

        else if (this.value === 'Sacks') {

            deductQuantity.max = totalSacks;
            deductQuantity.step = '1';

            deductHelp.textContent =
                'Amount to remove (Sacks). Maximum: ' +
                totalSacks +
                ' Sacks.';

        }

        else {

            deductQuantity.max = totalSacks;

            deductHelp.textContent =
                'Select a unit.';

        }

        // Clear old amount when changing unit
        deductQuantity.value = '';

    });

}
            // Show feedback dialog on redirect (?success=1 / ?error=1, e.g. after Save Goal):
            <?php if ($feedbackMessage): ?>
                document.getElementById('message').textContent = <?= json_encode($feedbackMessage) ?>;
                document.getElementById('feedback-diag').showModal();
            <?php endif; ?>

            // Show feedback dialog on Detail:
            const details = document.querySelectorAll('.details');
            details.forEach((link) => {
                link.addEventListener("click", (e) => {
                    const detail = e.currentTarget.dataset.detail;
                    const type = e.currentTarget.dataset.type;
                    //console.log(company);
                    if (detail)
                        document.getElementById('message').innerHTML = "<h3>Info: </h3>" + "<p>" +
                            detail + "</p>";
                    else
                        document.getElementById('message').textContent = "No description";

                    document.getElementById('feedback-diag').showModal();
                });
            });
    </script>
    <script>
    // Hover unit conversion (temporary display only): Sacks x 50 = KG,
    // KG / 50 = Sacks. mouseleave restores the exact original text.
    document.querySelectorAll('.conv-val').forEach(el => {
        const orig = el.textContent;
        const sacks = parseFloat(el.dataset.sacks || '0');
        const unit = el.dataset.unit || 'Sacks';
        const rate = 50;
        const fmt = v => {
            v = Math.round(v * 100) / 100;
            return Number.isInteger(v) ? String(v) : v.toFixed(2);
        };
        const card = el.closest('.stat-card');
        if (!card) return;
        card.addEventListener('mouseenter', () => {
            el.textContent = unit === 'KG' ? fmt(sacks / rate) + ' Sacks' : fmt(sacks * rate) + ' KG';
        });
        card.addEventListener('mouseleave', () => {
            el.textContent = orig;
        });
    });
    </script>
</body>

</html>