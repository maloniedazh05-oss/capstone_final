<?php
require_once "php_backend/session.php";

requireRole(['admin']);

// Report branch tabs. Only production is built; the rest are placeholders.
$tab = $_GET['tab'] ?? 'production';
if (!in_array($tab, ['production', 'inventory', 'stockout', 'forecast'], true)) {
    $tab = 'production';
}

// Production filters. Default: today back to 29 days ago, all statuses.
$repTo = $_GET['date-to'] ?? '';
$repFrom = $_GET['date-from'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repTo) || $repTo > date('Y-m-d')) {
    $repTo = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repFrom)) {
    $repFrom = date('Y-m-d', strtotime($repTo . ' -29 days'));
}
if ($repFrom > $repTo) {
    $repTmp = $repFrom;
    $repFrom = $repTo;
    $repTo = $repTmp;
}
$repStatus = $_GET['status'] ?? 'all';
if (!in_array($repStatus, ['all', 'Recent', 'Ongoing', 'Completed'], true)) {
    $repStatus = 'all';
}

// Forecast CSV export must stream before any HTML output (headers).
if ($tab === 'forecast' && ($_GET['export'] ?? '') === 'csv') {
    require_once "php_backend/db.php";
    require_once "php_backend/forecast_lib.php";
    $csvProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
    $csvProd = $_GET['fc-product'] ?? ($csvProdOpts[0] ?? 'Vermicast');
    if (!in_array($csvProd, $csvProdOpts, true)) {
        $csvProd = $csvProdOpts[0] ?? 'Vermicast';
    }
    $csvPeriod = $_GET['fc-period'] ?? '30';
    if (!in_array($csvPeriod, ['7', '14', '30'], true)) {
        $csvPeriod = '30';
    }
    $csvSteps = (int)$csvPeriod;
    $csvToday = date('Y-m-d');
    $csvTailStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at >= :start GROUP BY DATE(updated_at)");
    $csvTailStmt->execute([':prod' => $csvProd, ':start' => date('Y-m-d', strtotime($csvToday . ' -6 days')) . ' 00:00:00']);
    $csvTailMap = [];
    while ($csvTailRow = $csvTailStmt->fetch(PDO::FETCH_ASSOC)) {
        $csvTailMap[$csvTailRow['day']] = (int)$csvTailRow['q'];
    }
    $csvTailDays = [];
    $ctd = date('Y-m-d', strtotime($csvToday . ' -6 days'));
    while ($ctd <= $csvToday) {
        $csvTailDays[] = $ctd;
        $ctd = date('Y-m-d', strtotime($ctd . ' +1 day'));
    }
    $csvRes = runForecast($pdo, $csvProd, 365, $csvSteps, 'auto');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="forecast-' . $csvProd . '-' . $csvSteps . 'd.csv"');
    echo "Date,Actual Stock-Out,Forecast\n";
    foreach ($csvTailDays as $cd) {
        echo date('M d', strtotime($cd)) . ',' . ($csvTailMap[$cd] ?? 0) . ",\n";
    }
    if (!$csvRes['thin']) {
        $cd = $csvToday;
        foreach ($csvRes['forecast'] as $cq) {
            $cd = date('Y-m-d', strtotime($cd . ' +1 day'));
            echo date('M d', strtotime($cd)) . ',,' . $cq . "\n";
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="assets/node_modules/chart.js/dist/chart.umd.js"></script>
    <style>
    .report-tabs { display: flex; gap: 8px; margin: 12px 0 16px; flex-wrap: wrap; }
    .report-tabs a { text-decoration: none; padding: 8px 14px; border-radius: 6px; }
    @media print {
        .main-sidebar, .card-filter, .report-tabs, #print-btn { display: none !important; }
        body { background: #fff; }
        .content-card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
    }
    </style>
</head>
<body>
    <?php require_once "main-sidebar.php";?>

    <div class="reportspage">
        <div class="page-header">
            <h1>Reports</h1>
        </div>

        <div class="report-tabs">
            <a href="reports.php?tab=production" class="btn-secondary" style="text-decoration:none;<?= $tab === 'production' ? 'font-weight:bold;' : '' ?>">Production</a>
            <a href="reports.php?tab=inventory" class="btn-secondary" style="text-decoration:none;<?= $tab === 'inventory' ? 'font-weight:bold;' : '' ?>">Inventory</a>
            <a href="reports.php?tab=stockout" class="btn-secondary" style="text-decoration:none;<?= $tab === 'stockout' ? 'font-weight:bold;' : '' ?>">Stock-Out</a>
            <a href="reports.php?tab=forecast" class="btn-secondary" style="text-decoration:none;<?= $tab === 'forecast' ? 'font-weight:bold;' : '' ?>">Forecast</a>
        </div>

        <?php if ($tab === 'production'): ?>
        <?php
        require_once "php_backend/db.php";
        // Daily map first, then bucketed - same pattern as the inventory chart.
        $repStmt = $pdo->prepare("SELECT DATE(production_date) AS day, SUM(quantity) AS total_qty, COUNT(*) AS batches, SUM(CASE WHEN status = 'Completed' THEN quantity ELSE 0 END) AS done_qty, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS done_batches FROM production WHERE production_date BETWEEN :start AND :end" . ($repStatus !== 'all' ? " AND status = :status" : "") . " GROUP BY DATE(production_date)");
        $repStmt->bindValue(':start', $repFrom . ' 00:00:00');
        $repStmt->bindValue(':end', $repTo . ' 23:59:59');
        if ($repStatus !== 'all') {
            $repStmt->bindValue(':status', $repStatus);
        }
        $repStmt->execute();
        $repMap = [];
        while ($repRow = $repStmt->fetch(PDO::FETCH_ASSOC)) {
            $repMap[$repRow['day']] = $repRow;
        }
        // Buckets adapt to range width: daily (<=31d), weekly (<=92d), monthly above.
        $repDays = (strtotime($repTo) - strtotime($repFrom)) / 86400 + 1;
        $repBuckets = [];
        if ($repDays <= 31) {
            $bd = $repFrom;
            while ($bd <= $repTo) {
                $repBuckets[] = ['label' => date('M d', strtotime($bd)), 'start' => $bd, 'end' => $bd];
                $bd = date('Y-m-d', strtotime($bd . ' +1 day'));
            }
        } elseif ($repDays <= 92) {
            $wk = $repFrom;
            while ($wk <= $repTo) {
                $wkEnd = date('Y-m-d', strtotime($wk . ' +6 days'));
                if ($wkEnd > $repTo) {
                    $wkEnd = $repTo;
                }
                $repBuckets[] = ['label' => 'Wk ' . date('M d', strtotime($wk)), 'start' => $wk, 'end' => $wkEnd];
                $wk = date('Y-m-d', strtotime($wk . ' +7 days'));
            }
        } else {
            $mo = substr($repFrom, 0, 7);
            $moEnd = substr($repTo, 0, 7);
            while ($mo <= $moEnd) {
                $moStart = $mo . '-01';
                if ($moStart < $repFrom) {
                    $moStart = $repFrom;
                }
                $moLast = date('Y-m-t', strtotime($mo . '-01'));
                if ($moLast > $repTo) {
                    $moLast = $repTo;
                }
                $repBuckets[] = ['label' => date('M Y', strtotime($mo . '-01')), 'start' => $moStart, 'end' => $moLast];
                $mo = date('Y-m', strtotime($mo . '-01 +1 month'));
            }
        }
        $repLabels = [];
        $repQtys = [];
        $repTotal = 0;
        $repDoneQty = 0;
        $repDoneBatches = 0;
        foreach ($repBuckets as $b) {
            $bQty = 0;
            $bd = $b['start'];
            while ($bd <= $b['end']) {
                if (isset($repMap[$bd])) {
                    $bQty += (float)$repMap[$bd]['total_qty'];
                    $repDoneQty += (float)$repMap[$bd]['done_qty'];
                    $repDoneBatches += (int)$repMap[$bd]['done_batches'];
                }
                $bd = date('Y-m-d', strtotime($bd . ' +1 day'));
            }
            $repLabels[] = $b['label'];
            $repQtys[] = round($bQty, 2);
            $repTotal += $bQty;
        }
        $repAvg = $repDoneBatches > 0 ? round($repDoneQty / $repDoneBatches, 1) : 0;
        // Records list under the same filters + own search/sort.
        $searchRec = trim($_GET['search-rec'] ?? '');
        $recSort = $_GET['rec-sort'] ?? 'newest';
        if (!in_array($recSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
            $recSort = 'newest';
        }
        $recSortMap = ['newest' => 'production_date DESC', 'oldest' => 'production_date ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
        $recSql = "SELECT batch_id, production_date, item, quantity, unit, status FROM production WHERE production_date BETWEEN :start AND :end" . ($repStatus !== 'all' ? " AND status = :status" : "") . ($searchRec !== '' ? " AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search)" : "") . " ORDER BY " . $recSortMap[$recSort];
        $recStmt = $pdo->prepare($recSql);
        $recStmt->bindValue(':start', $repFrom . ' 00:00:00');
        $recStmt->bindValue(':end', $repTo . ' 23:59:59');
        if ($repStatus !== 'all') {
            $recStmt->bindValue(':status', $repStatus);
        }
        if ($searchRec !== '') {
            $recStmt->bindValue(':search', "%" . $searchRec . "%");
        }
        $recRows = [];
        $recError = false;
        try {
            $recStmt->execute();
            $recRows = $recStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $recError = true;
        }
        ?>

        <div class="page-header">
            <h1>Production Report</h1>
        </div>
        <p class="section-desc">Monitor fertilizer production activities.</p>

        <!-- Filter Card -->
        <div class="content-card">
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="reports.php" id="report-filter-form">
                        <input type="hidden" name="tab" value="production">
                        <label for="date-from">Date From:</label>
                        <input type="date" id="date-from" name="date-from" value="<?= htmlspecialchars($repFrom) ?>">
                        <label for="date-to">Date To:</label>
                        <input type="date" id="date-to" name="date-to" value="<?= htmlspecialchars($repTo) ?>">
                        <label for="rep-status">Status:</label>
                        <select id="rep-status" name="status">
                            <option value="all" <?= $repStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Recent" <?= $repStatus === 'Recent' ? 'selected' : '' ?>>Recent</option>
                            <option value="Ongoing" <?= $repStatus === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Completed" <?= $repStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <button type="submit" class="btn-primary" style="padding:7px 16px;">Apply</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="info-cards">
            <div class="stat-card">
                <h2>Total Production</h2>
                <h3><?= number_format($repTotal) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Completed Batches</h2>
                <h3><?= htmlspecialchars($repDoneBatches) ?></h3>
            </div>
            <div class="stat-card">
                <h2>Average Per Batch</h2>
                <h3><?= htmlspecialchars($repAvg) ?> Sacks</h3>
            </div>
        </div>

        <!-- Production Overview chart -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-column"></i> Production Overview</h2>
            </div>
            <div class="card-body">
                <div style="height: 320px;"><canvas id="prodChart"></canvas></div>
            </div>
        </div>
        <script>
        new Chart(document.getElementById('prodChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($repLabels) ?>,
                datasets: [{ label: 'Production (Sacks)', data: <?= json_encode($repQtys) ?>, backgroundColor: 'rgba(34,197,94,0.6)' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

        <!-- Production Records -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-list-ul"></i> Production Records</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="reports.php" id="rec-filter-form">
                        <input type="hidden" name="tab" value="production">
                        <input type="hidden" name="date-from" value="<?= htmlspecialchars($repFrom) ?>">
                        <input type="hidden" name="date-to" value="<?= htmlspecialchars($repTo) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($repStatus) ?>">
                        <input type="text" id="search-box-rec" placeholder="Search..." name="search-rec" value="<?= htmlspecialchars($searchRec) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-rec"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <label for="rec-sort">Sort by:</label>
                        <select id="rec-sort" name="rec-sort" onchange="document.getElementById('rec-filter-form').submit()">
                            <option value="newest" <?= $recSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $recSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $recSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $recSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchRec !== '' || $recSort !== 'newest'): ?>
                        <a href="reports.php?tab=production&date-from=<?= urlencode($repFrom) ?>&date-to=<?= urlencode($repTo) ?>&status=<?= urlencode($repStatus) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                    <a href="php_backend/report_pdf.php?branch=production&date-from=<?= urlencode($repFrom) ?>&date-to=<?= urlencode($repTo) ?>&status=<?= urlencode($repStatus) ?>&search-rec=<?= urlencode($searchRec) ?>&rec-sort=<?= urlencode($recSort) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;"><i class="fa-solid fa-file-pdf"></i> Save as PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Qty.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recError): ?>
                            <tr><td colspan="5">Search query not found!</td></tr>
                            <?php elseif (empty($recRows)): ?>
                            <tr><td colspan="5">No production records match your filters.</td></tr>
                            <?php else: ?>
                            <?php foreach ($recRows as $r_row): ?>
                            <tr>
                                <td><?= htmlspecialchars($r_row['batch_id']) ?></td>
                                <td><?= htmlspecialchars(date('M d', strtotime($r_row['production_date']))) ?></td>
                                <td><?= htmlspecialchars($r_row['item']) ?></td>
                                <td><strong><?= htmlspecialchars($r_row['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($r_row['status']) ?><?= $r_row['status'] === 'Completed' ? ' ✓' : '' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($tab === 'inventory'): ?>
        <?php
        require_once "php_backend/db.php";
        // Filters (defaults: today back 29 days, all products; span capped like the charts).
        $invTo = $_GET['inv-to'] ?? '';
        $invFrom = $_GET['inv-from'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invTo) || $invTo > date('Y-m-d')) {
            $invTo = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invFrom)) {
            $invFrom = date('Y-m-d', strtotime($invTo . ' -29 days'));
        }
        if ($invFrom > $invTo) {
            $invTmp = $invFrom;
            $invFrom = $invTo;
            $invTo = $invTmp;
        }
        if ((strtotime($invTo) - strtotime($invFrom)) / 86400 > 92) {
            $invFrom = date('Y-m-d', strtotime($invTo . ' -92 days'));
        }
        $invProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
        $invProd = $_GET['inv-product'] ?? 'all';
        if ($invProd !== 'all' && !in_array($invProd, $invProdOpts, true)) {
            $invProd = 'all';
        }
        $prodCondInv = $invProd !== 'all' ? " AND product = :prod" : "";
        // Current stock = ending point of the backward reconstruction.
        $curStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status != 'Completed'" . $prodCondInv);
        if ($invProd !== 'all') {
            $curStmt->bindValue(':prod', $invProd);
        }
        $curStmt->execute();
        $invCurrent = (int)$curStmt->fetchColumn();
        // Daily arrivals (inventory rows created, all sources: production
        // completions, manual receipts, imports) + outflow (Completed flips).
        // Arrivals key on created_at so each row counts exactly once; touches
        // and edits of existing rows move no stock and are ignored.
        $invToday = date('Y-m-d');
        $mapStart = date('Y-m-d', strtotime($invFrom . ' -1 day'));
        $inStmt = $pdo->prepare("SELECT DATE(created_at) AS day, SUM(quantity) AS q FROM inventory WHERE DATE(created_at) BETWEEN :start AND :end" . $prodCondInv . " GROUP BY DATE(created_at)");
        $inStmt->bindValue(':start', $mapStart);
        $inStmt->bindValue(':end', $invToday);
        if ($invProd !== 'all') {
            $inStmt->bindValue(':prod', $invProd);
        }
        $inStmt->execute();
        $invIn = [];
        while ($inRow = $inStmt->fetch(PDO::FETCH_ASSOC)) {
            $invIn[$inRow['day']] = (int)$inRow['q'];
        }
        $outStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end" . $prodCondInv . " GROUP BY DATE(updated_at)");
        $outStmt->bindValue(':start', $mapStart . ' 00:00:00');
        $outStmt->bindValue(':end', $invToday . ' 23:59:59');
        if ($invProd !== 'all') {
            $outStmt->bindValue(':prod', $invProd);
        }
        $outStmt->execute();
        $invOut = [];
        while ($outRow = $outStmt->fetch(PDO::FETCH_ASSOC)) {
            $invOut[$outRow['day']] = (int)$outRow['q'];
        }
        // End-of-day balances, newest -> oldest (reconstructed, approximate).
        $invBal = [];
        $bal = $invCurrent;
        $bd = $invToday;
        while ($bd >= $mapStart) {
            $invBal[$bd] = $bal;
            $bal = $bal - ($invIn[$bd] ?? 0) + ($invOut[$bd] ?? 0);
            $bd = date('Y-m-d', strtotime($bd . ' -1 day'));
        }
        $invBegin = $invBal[$mapStart];
        $invEnd = $invBal[$invTo];
        $invAdded = 0;
        $invOutSum = 0;
        $trendLabels = [];
        $trendVals = [];
        $moveRows = [];
        $searchMove = trim($_GET['search-move'] ?? '');
        $moveSort = $_GET['move-sort'] ?? 'newest';
        if (!in_array($moveSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
            $moveSort = 'newest';
        }
        $md = $invFrom;
        while ($md <= $invTo) {
            $mIn = $invIn[$md] ?? 0;
            $mOut = $invOut[$md] ?? 0;
            $invAdded += $mIn;
            $invOutSum += $mOut;
            $trendLabels[] = date('M d', strtotime($md));
            $trendVals[] = $invBal[$md];
            if ($mIn != 0) {
                $moveRows[] = ['day' => $md, 'tx' => 'Production', 'qty' => $mIn, 'eff' => '+' . $mIn, 'bal' => $invBal[$md]];
            }
            if ($mOut != 0) {
                $moveRows[] = ['day' => $md, 'tx' => 'Stock-Out', 'qty' => $mOut, 'eff' => '-' . $mOut, 'bal' => $invBal[$md]];
            }
            $md = date('Y-m-d', strtotime($md . ' +1 day'));
        }
        // In-table search (matches date text or transaction) + sort.
        if ($searchMove !== '') {
            $moveRows = array_values(array_filter($moveRows, function ($r) use ($searchMove) {
                return stripos($r['day'], $searchMove) !== false || stripos($r['tx'], $searchMove) !== false;
            }));
        }
        usort($moveRows, function ($a, $b) use ($moveSort) {
            if ($moveSort === 'oldest') {
                return strcmp($a['day'], $b['day']);
            } elseif ($moveSort === 'highest') {
                return abs($b['qty']) - abs($a['qty']);
            } elseif ($moveSort === 'lowest') {
                return abs($a['qty']) - abs($b['qty']);
            }
            return strcmp($b['day'], $a['day']);
        });
        ?>
        <div class="page-header">
            <h1>Inventory Report</h1>
        </div>
        <p class="section-desc">Monitor fertilizer stock movement and inventory.</p>

        <!-- Filter Card -->
        <div class="content-card">
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="reports.php" id="inv-report-filter-form">
                        <input type="hidden" name="tab" value="inventory">
                        <label for="inv-date-from">Date From:</label>
                        <input type="date" id="inv-date-from" name="inv-from" value="<?= htmlspecialchars($invFrom) ?>">
                        <label for="inv-date-to">Date To:</label>
                        <input type="date" id="inv-date-to" name="inv-to" value="<?= htmlspecialchars($invTo) ?>">
                        <label for="inv-product">Fertilizer:</label>
                        <select id="inv-product" name="inv-product">
                            <option value="all" <?= $invProd === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($invProdOpts as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $invProd === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding:7px 16px;">Apply</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="info-cards">
            <div class="stat-card">
                <h2>Beginning Stock</h2>
                <h3><?= number_format($invBegin) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Production Added</h2>
                <h3>+<?= number_format($invAdded) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Stock-Out</h2>
                <h3>-<?= number_format($invOutSum) ?> Sacks</h3>
            </div>
        </div>

        <!-- Ending inventory banner -->
        <div class="content-card">
            <div class="card-body">
                <p class="section-desc" style="margin:0;"><strong><?= $invTo === $invToday ? 'CURRENT' : 'ENDING' ?> INVENTORY: <?= number_format($invEnd) ?> SACKS</strong></p>
            </div>
        </div>

        <!-- Inventory Trend -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-line"></i> Inventory Trend</h2>
            </div>
            <div class="card-body">
                <div style="height: 320px;"><canvas id="invTrendChart"></canvas></div>
            </div>
        </div>
        <script>
        new Chart(document.getElementById('invTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{ label: 'Ending Balance (Sacks)', data: <?= json_encode($trendVals) ?>, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', fill: true, tension: 0.3, pointRadius: 2 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

        <!-- Inventory Movement -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-arrow-right-arrow-left"></i> Inventory Movement</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="reports.php" id="move-filter-form">
                        <input type="hidden" name="tab" value="inventory">
                        <input type="hidden" name="inv-from" value="<?= htmlspecialchars($invFrom) ?>">
                        <input type="hidden" name="inv-to" value="<?= htmlspecialchars($invTo) ?>">
                        <input type="hidden" name="inv-product" value="<?= htmlspecialchars($invProd) ?>">
                        <input type="text" id="search-box-move" placeholder="Search..." name="search-move" value="<?= htmlspecialchars($searchMove) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-move"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <label for="move-sort">Sort by:</label>
                        <select id="move-sort" name="move-sort" onchange="document.getElementById('move-filter-form').submit()">
                            <option value="newest" <?= $moveSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $moveSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $moveSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $moveSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchMove !== '' || $moveSort !== 'newest'): ?>
                        <a href="reports.php?tab=inventory&inv-from=<?= urlencode($invFrom) ?>&inv-to=<?= urlencode($invTo) ?>&inv-product=<?= urlencode($invProd) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction</th>
                                <th>Qty.</th>
                                <th>Effect</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($moveRows)): ?>
                            <tr><td colspan="5"><?= $searchMove !== '' ? "No movements match '" . htmlspecialchars($searchMove) . "'." : "No stock movements in this range." ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($moveRows as $m_row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d', strtotime($m_row['day']))) ?></td>
                                <td><?= htmlspecialchars($m_row['tx']) ?></td>
                                <td><?= htmlspecialchars($m_row['qty']) ?></td>
                                <td><?= htmlspecialchars($m_row['eff']) ?></td>
                                <td><?= htmlspecialchars($m_row['bal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($tab === 'stockout'): ?>
        <?php
        require_once "php_backend/db.php";
        // Filters (defaults: today back 29 days, all products; span capped like the charts).
        $soTo = $_GET['so-to'] ?? '';
        $soFrom = $_GET['so-from'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $soTo) || $soTo > date('Y-m-d')) {
            $soTo = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $soFrom)) {
            $soFrom = date('Y-m-d', strtotime($soTo . ' -29 days'));
        }
        if ($soFrom > $soTo) {
            $soTmp = $soFrom;
            $soFrom = $soTo;
            $soTo = $soTmp;
        }
        if ((strtotime($soTo) - strtotime($soFrom)) / 86400 > 92) {
            $soFrom = date('Y-m-d', strtotime($soTo . ' -92 days'));
        }
        $soProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
        $soProd = $_GET['so-product'] ?? 'all';
        if ($soProd !== 'all' && !in_array($soProd, $soProdOpts, true)) {
            $soProd = 'all';
        }
        $soProdCond = $soProd !== 'all' ? " AND product = :prod" : "";
        // Daily stock-out map: Completed flips keyed on updated_at.
        $soStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND DATE(updated_at) BETWEEN :start AND :end" . $soProdCond . " GROUP BY DATE(updated_at)");
        $soStmt->bindValue(':start', $soFrom);
        $soStmt->bindValue(':end', $soTo);
        if ($soProd !== 'all') {
            $soStmt->bindValue(':prod', $soProd);
        }
        $soStmt->execute();
        $soMap = [];
        while ($soRow = $soStmt->fetch(PDO::FETCH_ASSOC)) {
            $soMap[$soRow['day']] = (int)$soRow['q'];
        }
        $soLabels = [];
        $soVals = [];
        $soTotal = 0;
        $soHigh = 0;
        $soHighDay = '';
        $sd = $soFrom;
        while ($sd <= $soTo) {
            $sq = $soMap[$sd] ?? 0;
            $soLabels[] = date('M d', strtotime($sd));
            $soVals[] = $sq;
            $soTotal += $sq;
            if ($sq > $soHigh) {
                $soHigh = $sq;
                $soHighDay = date('M d', strtotime($sd));
            }
            $sd = date('Y-m-d', strtotime($sd . ' +1 day'));
        }
        $soDays = (strtotime($soTo) - strtotime($soFrom)) / 86400 + 1;
        $soAvg = $soDays > 0 ? round($soTotal / $soDays, 1) : 0;
        // Records under the same filters + own search/sort.
        $searchSo = trim($_GET['search-so'] ?? '');
        $soRecSort = $_GET['so-sort'] ?? 'newest';
        if (!in_array($soRecSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
            $soRecSort = 'newest';
        }
        $soRecSortMap = ['newest' => 'updated_at DESC', 'oldest' => 'updated_at ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
        $soRecSql = "SELECT DATE(updated_at) AS day, product, quantity, description FROM inventory WHERE status = 'Completed' AND DATE(updated_at) BETWEEN :start AND :end" . $soProdCond . ($searchSo !== '' ? " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search)" : "") . " ORDER BY " . $soRecSortMap[$soRecSort];
        $soRecStmt = $pdo->prepare($soRecSql);
        $soRecStmt->bindValue(':start', $soFrom);
        $soRecStmt->bindValue(':end', $soTo);
        if ($soProd !== 'all') {
            $soRecStmt->bindValue(':prod', $soProd);
        }
        if ($searchSo !== '') {
            $soRecStmt->bindValue(':search', "%" . $searchSo . "%");
        }
        $soRecRows = [];
        $soRecError = false;
        try {
            $soRecStmt->execute();
            $soRecRows = $soRecStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $soRecError = true;
        }
        ?>
        <div class="page-header">
            <h1>Stock-Out Report</h1>
        </div>
        <p class="section-desc">View fertilizer stock-out transactions.</p>

        <!-- Filter Card -->
        <div class="content-card">
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="reports.php" id="so-report-filter-form">
                        <input type="hidden" name="tab" value="stockout">
                        <label for="so-date-from">Date From:</label>
                        <input type="date" id="so-date-from" name="so-from" value="<?= htmlspecialchars($soFrom) ?>">
                        <label for="so-date-to">Date To:</label>
                        <input type="date" id="so-date-to" name="so-to" value="<?= htmlspecialchars($soTo) ?>">
                        <label for="so-product">Fertilizer Type:</label>
                        <select id="so-product" name="so-product">
                            <option value="all" <?= $soProd === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($soProdOpts as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $soProd === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding:7px 16px;">Apply</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="info-cards">
            <div class="stat-card">
                <h2>Total Stock-Out</h2>
                <h3><?= number_format($soTotal) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Average Daily</h2>
                <h3><?= htmlspecialchars($soAvg) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Highest Stock-Out</h2>
                <h3><?= number_format($soHigh) ?> Sacks</h3>
                <?php if ($soHighDay !== ''): ?>
                <p class="section-desc" style="margin:0;"><?= htmlspecialchars($soHighDay) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stock-Out Overview chart -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-column"></i> Stock-Out Overview</h2>
            </div>
            <div class="card-body">
                <div style="height: 320px;"><canvas id="soChart"></canvas></div>
            </div>
        </div>
        <script>
        new Chart(document.getElementById('soChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($soLabels) ?>,
                datasets: [{ label: 'Stock-Out (Sacks)', data: <?= json_encode($soVals) ?>, backgroundColor: 'rgba(239,68,68,0.6)' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

        <!-- Stock-Out Records -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-list-ul"></i> Stock-Out Records</h2>
                <div class="card-filter">
                    <div class="search-bar">
                        <form method="GET" action="reports.php" id="so-rec-filter-form">
                        <input type="hidden" name="tab" value="stockout">
                        <input type="hidden" name="so-from" value="<?= htmlspecialchars($soFrom) ?>">
                        <input type="hidden" name="so-to" value="<?= htmlspecialchars($soTo) ?>">
                        <input type="hidden" name="so-product" value="<?= htmlspecialchars($soProd) ?>">
                        <input type="text" id="search-box-so" placeholder="Search..." name="search-so" value="<?= htmlspecialchars($searchSo) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-so"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <label for="so-rec-sort">Sort by:</label>
                        <select id="so-rec-sort" name="so-sort" onchange="document.getElementById('so-rec-filter-form').submit()">
                            <option value="newest" <?= $soRecSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $soRecSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $soRecSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $soRecSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchSo !== '' || $soRecSort !== 'newest'): ?>
                        <a href="reports.php?tab=stockout&so-from=<?= urlencode($soFrom) ?>&so-to=<?= urlencode($soTo) ?>&so-product=<?= urlencode($soProd) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                    <a href="php_backend/report_pdf.php?branch=stockout&so-from=<?= urlencode($soFrom) ?>&so-to=<?= urlencode($soTo) ?>&so-product=<?= urlencode($soProd) ?>&search-so=<?= urlencode($searchSo) ?>&so-sort=<?= urlencode($soRecSort) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;"><i class="fa-solid fa-file-pdf"></i> Save as PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Qty.</th>
                                <th>Desc/Receiver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($soRecError): ?>
                            <tr><td colspan="4">Search query not found!</td></tr>
                            <?php elseif (empty($soRecRows)): ?>
                            <tr><td colspan="4">No stock-out records match your filters.</td></tr>
                            <?php else: ?>
                            <?php foreach ($soRecRows as $s_row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d', strtotime($s_row['day']))) ?></td>
                                <td><?= htmlspecialchars($s_row['product']) ?></td>
                                <td><strong><?= htmlspecialchars($s_row['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($s_row['description'] ?: 'None') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php
        require_once "php_backend/db.php";
        require_once "php_backend/forecast_lib.php";
        $fcProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
        $fcProd = $_POST['fc-product'] ?? $_GET['fc-product'] ?? ($fcProdOpts[0] ?? 'Vermicast');
        if (!in_array($fcProd, $fcProdOpts, true)) {
            $fcProd = $fcProdOpts[0] ?? 'Vermicast';
        }
        $fcPeriod = $_POST['fc-period'] ?? $_GET['fc-period'] ?? '30';
        if (!in_array($fcPeriod, ['7', '14', '30'], true)) {
            $fcPeriod = '30';
        }
        $fcSteps = (int)$fcPeriod;
        // Historical availability label for the selected product.
        $fcRangeStmt = $pdo->prepare("SELECT MIN(updated_at) AS mn, MAX(updated_at) AS mx FROM inventory WHERE status = 'Completed' AND product = :prod");
        $fcRangeStmt->execute([':prod' => $fcProd]);
        $fcRangeRow = $fcRangeStmt->fetch(PDO::FETCH_ASSOC);
        $fcRangeLabel = ($fcRangeRow && $fcRangeRow['mn']) ? date('F Y', strtotime($fcRangeRow['mn'])) . ' - ' . date('F Y', strtotime($fcRangeRow['mx'])) : 'No completed data';
        // Current inventory of the selected product.
        $fcCurStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status != 'Completed' AND product = :prod");
        $fcCurStmt->execute([':prod' => $fcProd]);
        $fcCurrent = (int)$fcCurStmt->fetchColumn();
        // Last 7 actual days for the table and CSV.
        $fcToday = date('Y-m-d');
        $fcTailStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at >= :start GROUP BY DATE(updated_at)");
        $fcTailStmt->execute([':prod' => $fcProd, ':start' => date('Y-m-d', strtotime($fcToday . ' -6 days')) . ' 00:00:00']);
        $fcTailMap = [];
        while ($fcTailRow = $fcTailStmt->fetch(PDO::FETCH_ASSOC)) {
            $fcTailMap[$fcTailRow['day']] = (int)$fcTailRow['q'];
        }
        $fcTailDays = [];
        $td = date('Y-m-d', strtotime($fcToday . ' -6 days'));
        while ($td <= $fcToday) {
            $fcTailDays[] = $td;
            $td = date('Y-m-d', strtotime($td . ' +1 day'));
        }
        $fcResult = null;
        $fcHistWarn = false;
        if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['fc-generate'])) {
            $fcResult = runForecast($pdo, $fcProd, 365, $fcSteps, 'auto');
            if (!$fcResult['thin']) {
                try {
                    $fcHist = $pdo->prepare("INSERT INTO forecasting_history (product, period_days, alpha, total_demand, daily_json, method, range_days) VALUES (:prod, :days, :alpha, :total, :daily, :method, :range)");
                    $fcHist->execute([':prod' => $fcProd, ':days' => $fcSteps, ':alpha' => 0.3, ':total' => array_sum($fcResult['forecast']), ':daily' => json_encode($fcResult['forecast']), ':method' => $fcResult['method'], ':range' => 365]);
                } catch (Exception $e) {
                    try {
                        $fcHist = $pdo->prepare("INSERT INTO forecasting_history (product, period_days, alpha, total_demand, daily_json) VALUES (:prod, :days, :alpha, :total, :daily)");
                        $fcHist->execute([':prod' => $fcProd, ':days' => $fcSteps, ':alpha' => 0.3, ':total' => array_sum($fcResult['forecast']), ':daily' => json_encode($fcResult['forecast'])]);
                    } catch (Exception $e2) {
                        $fcHistWarn = true;
                    }
                }
            }
        }
        // CSV export is handled at the top of this file (before HTML output).
        ?>
        <div class="page-header">
            <h1>Forecast Report</h1>
        </div>
        <p class="section-desc">View historical and forecasted fertilizer demand.</p>

        <!-- Filter Card -->
        <div class="content-card">
            <div class="card-body">
                <div class="search-bar">
                    <form method="POST" action="reports.php?tab=forecast" id="fc-report-filter-form">
                        <label for="fc-product">Fertilizer:</label>
                        <select id="fc-product" name="fc-product" required>
                            <?php foreach ($fcProdOpts as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $fcProd === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="section-desc" style="margin:0;">Historical: <strong><?= htmlspecialchars($fcRangeLabel) ?></strong></span>
                        <label for="fc-period">Forecast:</label>
                        <select id="fc-period" name="fc-period" required>
                            <option value="7" <?= $fcPeriod === '7' ? 'selected' : '' ?>>Next 7 Days</option>
                            <option value="14" <?= $fcPeriod === '14' ? 'selected' : '' ?>>Next 14 Days</option>
                            <option value="30" <?= $fcPeriod === '30' ? 'selected' : '' ?>>Next 30 Days</option>
                        </select>
                        <input type="hidden" name="fc-generate" value="1">
                        <button type="submit" class="btn-primary" style="padding:7px 16px;">Generate</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($fcResult !== null && !$fcResult['thin']): ?>
        <?php
        $fcTotal = array_sum($fcResult['forecast']);
        $fcDiff = $fcCurrent - $fcTotal;
        // Chart history tail: last 30 days actuals.
        $fcHistStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at >= :start GROUP BY DATE(updated_at)");
        $fcHistStmt->execute([':prod' => $fcProd, ':start' => date('Y-m-d', strtotime($fcToday . ' -29 days')) . ' 00:00:00']);
        $fcHistMap = [];
        while ($fcHistRow = $fcHistStmt->fetch(PDO::FETCH_ASSOC)) {
            $fcHistMap[$fcHistRow['day']] = (int)$fcHistRow['q'];
        }
        $fcHistLabels = [];
        $fcHistVals = [];
        $hd = date('Y-m-d', strtotime($fcToday . ' -29 days'));
        while ($hd <= $fcToday) {
            $fcHistLabels[] = date('M d', strtotime($hd));
            $fcHistVals[] = $fcHistMap[$hd] ?? 0;
            $hd = date('Y-m-d', strtotime($hd . ' +1 day'));
        }
        $fcLabels = [];
        $fd = $fcToday;
        for ($i = 1; $i <= $fcSteps; $i++) {
            $fd = date('Y-m-d', strtotime($fd . ' +1 day'));
            $fcLabels[] = date('M d', strtotime($fd));
        }
        ?>
        <!-- Summary cards -->
        <div class="info-cards">
            <div class="stat-card">
                <h2>Current Inventory</h2>
                <h3><?= number_format($fcCurrent) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Forecasted Demand</h2>
                <h3><?= number_format($fcTotal) ?> Sacks</h3>
            </div>
            <div class="stat-card">
                <h2>Difference</h2>
                <h3><?= ($fcDiff >= 0 ? '+' : '') . number_format($fcDiff) ?> Sacks</h3>
            </div>
        </div>

        <!-- Forecast Overview -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-line"></i> Forecast Overview</h2>
            </div>
            <div class="card-body">
                <p class="section-desc">Method: <?= htmlspecialchars($fcResult['method']) ?></p>
                <div style="height: 320px;"><canvas id="fcChart"></canvas></div>
            </div>
        </div>
        <script>
        new Chart(document.getElementById('fcChart'), {
            data: {
                labels: <?= json_encode(array_merge($fcHistLabels, $fcLabels)) ?>,
                datasets: [
                    { type: 'line', label: 'Actual', data: <?= json_encode(array_merge($fcHistVals, array_fill(0, $fcSteps, null))) ?>, borderColor: '#22c55e', tension: 0.3, pointRadius: 2, spanGaps: false },
                    { type: 'line', label: 'Forecast', data: <?= json_encode(array_merge(array_fill(0, count($fcHistVals), null), $fcResult['forecast'])) ?>, borderColor: '#3b82f6', borderDash: [6, 4], tension: 0.3, pointRadius: 2, spanGaps: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

        <!-- Forecast Results -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-calendar-days"></i> Forecast Results</h2>
                <div class="card-filter">
                    <a href="php_backend/report_pdf.php?branch=forecast&fc-product=<?= urlencode($fcProd) ?>&fc-period=<?= urlencode($fcPeriod) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;"><i class="fa-solid fa-file-pdf"></i> Save as PDF</a>
                    <a href="reports.php?tab=forecast&export=csv&fc-product=<?= urlencode($fcProd) ?>&fc-period=<?= urlencode($fcPeriod) ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($fcTotal > $fcCurrent): ?>
                <div class="feedback-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Forecasted demand may exceed current inventory.</span>
                </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Actual Stock-Out</th>
                                <th>Forecast</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fcTailDays as $tday): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d', strtotime($tday))) ?></td>
                                <td><?= htmlspecialchars($fcTailMap[$tday] ?? 0) ?></td>
                                <td>—</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php $fd = $fcToday; ?>
                            <?php foreach ($fcResult['forecast'] as $fq): ?>
                            <?php $fd = date('Y-m-d', strtotime($fd . ' +1 day')); ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d', strtotime($fd))) ?></td>
                                <td>—</td>
                                <td><strong><?= htmlspecialchars($fq) ?> Sacks</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($fcResult !== null && $fcResult['thin']): ?>
        <div class="content-card">
            <div class="card-body">
                <p class="section-desc">Not enough history yet - forecasts unlock after about 15 days of sales for this product.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
