<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff', 'manager']);

// Filters (GET so search + dropdowns combine in the URL).
$searchHist = trim($_GET['search'] ?? '');
$filterUser = trim($_GET['user'] ?? 'all');
$filterAction = trim($_GET['action'] ?? 'all');
$filterDate = $_GET['date'] ?? 'all';
if ($filterDate !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = 'all';
}
$filterMonth = $_GET['month'] ?? 'all';
$filterYear = $_GET['year'] ?? 'all';
$filterSort = $_GET['sort'] ?? 'newest';
if ($filterMonth !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $filterMonth)) {
    $filterMonth = 'all';
}
if ($filterYear !== 'all' && !preg_match('/^\d{4}$/', $filterYear)) {
    $filterYear = 'all';
}
if (!in_array($filterSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
    $filterSort = 'newest';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>

    <div class="historypage">
        <div class="page-header">
            <h1>Activity History</h1>
        </div>
        <p class="section-desc">View system activities and transactions.</p>

        <!-- Filter bar -->
        <div class="content-card">
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="history.php" id="history-filter-form">
                        <input type="text" id="search-box-hist" placeholder="Search..." name="search" value="<?= htmlspecialchars($searchHist) ?>"><!--Set the value of search bar for consistent memory-->
                        <button type="submit" id="search-btn-hist"><i class="fa-solid fa-magnifying-glass"></i></button>

                        <?php
                        require_once "php_backend/db.php";
                        $userOpts = $pdo->query("SELECT DISTINCT user FROM history ORDER BY user")->fetchAll(PDO::FETCH_COLUMN);
                        $actionOpts = $pdo->query("SELECT DISTINCT action FROM history ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
                        $dateOpts = $pdo->query("SELECT DISTINCT DATE(created_at) AS d FROM history ORDER BY d DESC")->fetchAll(PDO::FETCH_COLUMN);
                        $monthOpts = $pdo->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS m FROM history ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
                        $yearOpts = $pdo->query("SELECT DISTINCT YEAR(created_at) AS y FROM history ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <i class="fa-solid fa-user"></i>
                        <label for="user-filter">User:</label>
                        <select id="user-filter" name="user" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $filterUser === 'all' ? 'selected' : '' ?>>All Users</option>
                            <?php foreach ($userOpts as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>" <?= $filterUser === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-filter"></i>
                        <label for="action-filter">Action:</label>
                        <select id="action-filter" name="action" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $filterAction === 'all' ? 'selected' : '' ?>>All Actions</option>
                            <?php foreach ($actionOpts as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-calendar"></i>
                        <label for="date-filter">Date:</label>
                        <select id="date-filter" name="date" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $filterDate === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <?php foreach ($dateOpts as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $filterDate === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="month-filter">Month:</label>
                        <select id="month-filter" name="month" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $filterMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                            <?php foreach ($monthOpts as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= htmlspecialchars(date('M Y', strtotime($m . '-01'))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="year-filter">Year:</label>
                        <select id="year-filter" name="year" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                            <?php foreach ($yearOpts as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= (string)$filterYear === (string)$y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="sort-filter">Sort by:</label>
                        <select id="sort-filter" name="sort" onchange="document.getElementById('history-filter-form').submit()">
                            <option value="newest" <?= $filterSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $filterSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="highest" <?= $filterSort === 'highest' ? 'selected' : '' ?>>Highest quantity</option>
                            <option value="lowest" <?= $filterSort === 'lowest' ? 'selected' : '' ?>>Lowest quantity</option>
                        </select>
                        <?php if ($searchHist !== '' || $filterUser !== 'all' || $filterAction !== 'all' || $filterDate !== 'all' || $filterMonth !== 'all' || $filterYear !== 'all' || $filterSort !== 'newest'): ?>
                        <a href="history.php" class="btn-secondary" style="text-decoration:none;padding:6px 10px;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Activity -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> System Activity</h2>
            </div>
            <div class="card-body">
                <?php
                require_once "php_backend/db.php";
                $histSql = "SELECT * FROM history WHERE 1 = 1";
                $histParams = [];
                if ($searchHist !== '') {
                    $histSql .= " AND (user LIKE :search OR action LIKE :search OR ref_id LIKE :search OR product LIKE :search)";
                    $histParams[':search'] = "%" . $searchHist . "%";
                }
                if ($filterUser !== 'all') {
                    $histSql .= " AND user = :huser";
                    $histParams[':huser'] = $filterUser;
                }
                if ($filterAction !== 'all') {
                    $histSql .= " AND action = :haction";
                    $histParams[':haction'] = $filterAction;
                }
                if ($filterDate !== 'all') {
                    $histSql .= " AND DATE(created_at) = :hdate";
                    $histParams[':hdate'] = $filterDate;
                }
                if ($filterMonth !== 'all') {
                    $histSql .= " AND DATE_FORMAT(created_at, '%Y-%m') = :hmonth";
                    $histParams[':hmonth'] = $filterMonth;
                }
                if ($filterYear !== 'all') {
                    $histSql .= " AND YEAR(created_at) = :hyear";
                    $histParams[':hyear'] = $filterYear;
                }
                $filterSortMap = ['newest' => 'created_at DESC', 'oldest' => 'created_at ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
                $histSql .= " ORDER BY " . $filterSortMap[$filterSort];
                $stmt = $pdo->prepare($histSql);
                foreach ($histParams as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                ?>
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $histRows = [];
                            $histError = false;
                            try {
                                $stmt->execute();
                                $histRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $histError = true;
                                echo "<tr><td colspan='4'>Search query not found!</td></tr>";
                            }
                            if (!$histError && empty($histRows)):
                            ?>
                            <tr><td colspan="4"><?= ($searchHist !== '' || $filterUser !== 'all' || $filterAction !== 'all' || $filterDate !== 'all' || $filterMonth !== 'all' || $filterYear !== 'all' || $filterSort !== 'newest') ? "No activity matches your search/filter." : "No activity yet." ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($histRows as $h_row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d h:i A', strtotime($h_row['created_at']))) ?></td>
                                <td><?= htmlspecialchars($h_row['user']) ?></td>
                                <td><?= htmlspecialchars($h_row['action']) ?></td>
                                <td><?= htmlspecialchars(!empty($h_row['ref_id']) ? 'Batch ' . $h_row['ref_id'] . ' - ' . $h_row['quantity'] . ' ' . strtolower($h_row['unit']) : $h_row['quantity'] . ' ' . strtolower($h_row['unit'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- System Activity END -->
    </div> <!-- Historypage END -->
</body>
</html>
