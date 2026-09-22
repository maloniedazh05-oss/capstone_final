<?php 
require_once "php_backend/session.php";

requireRole(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php";?>
    <?php 
    require_once "php_backend/db.php";

    // Fetch status completed in inventory from selected month.
    $searchSales = trim($_GET['search-sales'] ?? '');
    $salesSort = $_GET['sales-sort'] ?? 'newest';
    if (!in_array($salesSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
        $salesSort = 'newest';
    }
    ?>
    <div class="salespage">
        <div class="page-header">
            <h1>Sales Management</h1>
        </div>

        <?php if (isset($_GET['import_success'])): ?>
            <div class="feedback-success">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($_GET['import_success']) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['import_error'])): ?>
            <div class="feedback-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($_GET['import_error']) ?>
            </div>
        <?php endif; ?>

        <!-- Card 1: Import Historical Sales -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-file-import"></i> Import Historical Sales</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="php_backend/import.php" enctype="multipart/form-data">
                    <div class="form-row" style="align-items: center;">
                        <div class="form-group">
                            <label for="historyFile">Upload Sales Data (.json, .txt)</label>
                            <input type="file" id="historyFile" accept=".json,.txt" name="historyFile" required>
                        </div>
                        <div class="form-group" style="justify-content: center; padding-top: 18px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; color: var(--color-text-main);">
                                <input type="checkbox" name="confirm_overwrite" value="yes" style="width: auto; margin: 0;">
                                Overwrite previously imported rows in this date range
                            </label>
                        </div>
                    </div>
                    <div style="margin-top: 14px;">
                        <button type="submit" class="btn-primary">Import Historical Data</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card 2: Monthly Sales Breakdown -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-chart-line"></i> Monthly Sales Records</h2>
                <div class="card-filter">
                    <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                        <select id="months-select" name="month-selected">
                            <?php
                            // Months that have Completed inventory (sold) records
                            $stmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(updated_at, '%Y-%m-01 00:00:00') AS month_start, DATE_FORMAT(updated_at, '%M %Y') AS month_label FROM inventory WHERE status = :status ORDER BY month_start DESC");
                            $stmt->execute([':status' => 'Completed']);
                            $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $selected = $_GET['month-selected'] ?? '';
                            if (!$months) {
                                echo "<option>No Record</option>";
                            }
                            foreach ($months as $m) {
                                $sel = ($selected == $m['month_start']) ? ' selected' : '';
                                echo "<option value='" . htmlspecialchars($m['month_start']) . "'$sel>" . htmlspecialchars($m['month_label']) . "</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding: 7px 16px;">View Month Sales</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Monthly total + daily breakdown of Completed inventory for the selected month
                if (!empty($_GET['month-selected'])) {
                    $month_start = $_GET['month-selected'];
                    $month_end = date('Y-m-t 23:59:59', strtotime($month_start));

                    $stmt_total = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS total_qty, COUNT(*) AS rec_count FROM inventory WHERE status = :status AND updated_at BETWEEN :start AND :end");
                    $stmt_total->execute([':status' => 'Completed', ':start' => $month_start, ':end' => $month_end]);
                    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);

                    $stmt_days = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS qty, COUNT(*) AS recs FROM inventory WHERE status = :status AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at) ORDER BY day");
                    
                    $stmt_days->execute([':status' => 'Completed', ':start' => $month_start, ':end' => $month_end]);
                    $days = $stmt_days->fetchAll(PDO::FETCH_ASSOC);
                    // In-table search (matches the date text) + sort.
                    if ($searchSales !== '') {
                        $days = array_values(array_filter($days, function ($d) use ($searchSales) {
                            return stripos($d['day'], $searchSales) !== false;
                        }));
                    }
                    usort($days, function ($a, $b) use ($salesSort) {
                        if ($salesSort === 'oldest') {
                            return strcmp($a['day'], $b['day']);
                        } elseif ($salesSort === 'highest') {
                            return $b['qty'] - $a['qty'];
                        } elseif ($salesSort === 'lowest') {
                            return $a['qty'] - $b['qty'];
                        }
                        return strcmp($b['day'], $a['day']);
                    });
                ?>
                    <div class="sales-summary-bar">
                        <div class="summary-item">
                            <span class="summary-label"><i class="fa-solid fa-basket-shopping"></i> Total Sold:</span>
                            <span class="summary-value"><strong><?= htmlspecialchars($total_row['total_qty']) ?></strong> Sacks</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><i class="fa-solid fa-receipt"></i> Completed Records:</span>
                            <span class="summary-value"><strong><?= htmlspecialchars($total_row['rec_count']) ?></strong></span>
                        </div>
                    </div>

                    <?php if (!$days): ?>
                        <p class="section-desc"><?= $searchSales !== '' ? "No sales match '" . htmlspecialchars($searchSales) . "'." : "No Completed records this month." ?></p>
                    <?php else: ?>
                    <div class="search-bar" style="margin-bottom: 12px;">
                        <form method="GET" action="sales.php" id="sales-filter-form">
                        <input type="hidden" name="month-selected" value="<?= htmlspecialchars($_GET['month-selected'] ?? '') ?>">
                        <input type="text" id="search-box-sales" placeholder="Search date..." name="search-sales" value="<?= htmlspecialchars($searchSales) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-sales"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <i class="fa-solid fa-filter"></i>
                        <label for="sales-sort">Sort by:</label>
                        <select id="sales-sort" name="sales-sort" onchange="document.getElementById('sales-filter-form').submit()">
                            <option value="newest" <?= $salesSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $salesSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $salesSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $salesSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchSales !== '' || $salesSort !== 'newest'): ?>
                        <a href="sales.php?month-selected=<?= urlencode($_GET['month-selected'] ?? '') ?>" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Quantity Sold</th>
                                    <th>Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['day']) ?></td>
                                    <td><strong><?= htmlspecialchars($d['qty']) ?> Sacks</strong></td>
                                    <td><?= htmlspecialchars($d['recs']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                <?php } else { ?>
                    <p class="section-desc" style="margin: 0; color: var(--color-text-muted);">Please select a month above and click <strong>View Month Sales</strong> to view sales records.</p>
                <?php } ?>
            </div>
        </div>
    </div><!--Salespage END-->
</body>
</html>
