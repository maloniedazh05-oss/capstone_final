<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff']);
// 50KG per 1Sac

// Inventory trend + sales goal setup.
// Ending balance per day, reconstructed backward from current stock
// (same math as inventory Movements: balance = current - inflows-after + outflows-after).
// Approximation, not an exact ledger - the schema keeps no history table.
$trend = $_GET['trend'] ?? 'recent';
if (!in_array($trend, ['recent', '7', '30'], true)) {
    $trend = 'recent';
}
$trendDays = $trend === '30' ? 30 : ($trend === '7' ? 7 : 3);
$chart_today = date('Y-m-d');
$chart_start = date('Y-m-d', strtotime($chart_today . ' -' . ($trendDays - 1) . ' days'));
$trendStmtIn = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM production WHERE status != 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
$trendStmtIn->execute([':start' => $chart_start . ' 00:00:00', ':end' => $chart_today . ' 23:59:59']);
$trendIn = [];
while ($trend_row = $trendStmtIn->fetch(PDO::FETCH_ASSOC)) {
    $trendIn[$trend_row['day']] = (int)$trend_row['total_qty'];
}
$trendStmtOut = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
$trendStmtOut->execute([':start' => $chart_start . ' 00:00:00', ':end' => $chart_today . ' 23:59:59']);
$trendOut = [];
while ($trend_row = $trendStmtOut->fetch(PDO::FETCH_ASSOC)) {
    $trendOut[$trend_row['day']] = (int)$trend_row['total_qty'];
}
$trendCur = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status != 'Completed'");
$trendCur->execute();
$trendBal = (int)$trendCur->fetchColumn();
$trendPts = [];
$trend_day = $chart_today;
while ($trend_day >= $chart_start) {
    $trendPts[] = ['day' => $trend_day, 'bal' => $trendBal];
    $trendBal = $trendBal - ($trendIn[$trend_day] ?? 0) + ($trendOut[$trend_day] ?? 0);
    $trend_day = date('Y-m-d', strtotime($trend_day . ' -1 day'));
}
$trendPts = array_reverse($trendPts);
$chartLabels = [];
$chartData = [];
foreach ($trendPts as $pt) {
    $chartLabels[] = date('M d', strtotime($pt['day']));
    $chartData[] = $pt['bal'];
}
// Target minimum stock (Sacks). Set by admin/manager on the inventory tab.
$salesGoalFile = __DIR__ . '/php_backend/sales_goal.php';
$salesGoal = file_exists($salesGoalFile) ? max(0, (int)include $salesGoalFile) : 50;
$goalData = array_fill(0, count($chartData), $salesGoal);

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href='style.css'>
    <?php $NEED_CHART = true; require_once "php_backend/head_assets.php"; ?>
</head>
<body>
<?php require_once "main-sidebar.php"; ?>
    <?php
    // Fetch the non-completed vermi for totla current stock:
    $vermi = $pdo->prepare("SELECT product, quantity, status FROM inventory WHERE status != :completed");
    $vermi->execute([':completed' => 'Completed']);

    $current_vermicast = 0;

    while ($vermicast = $vermi->fetch(PDO::FETCH_ASSOC)) {
        $current_vermicast += $vermicast['quantity'];
    }
    ?>
    <div class="dashboardpage">
        <div class="page-header">
            <h1>Dashboard</h1>
        </div>
        <div style="border-bottom: 1px solid var(--color-border);">
            <?php 
            $day = date('A');
            $greet = $day == 'AM' ? $greet = 'Morning' : $greet = 'Evening';            
            ?>
            <h2>Good <?=$greet?>, <strong><?=htmlspecialchars($_SESSION['user_name'])?></strong></h2>
        </div>
        <h2 id="dashboard-notif" >
        <script>let notif = document.getElementById('dashboard-notif');</script>
    <!-- Stock notficiation dashboard,  -->
            <?php if($current_vermicast > 20): ?>
                <script>
                    notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'green';
                    notif.innerHTML = "Stocks levels are healthy";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 10 && $current_vermicast < 5): ?>
                <script>
                    notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'brown';
                    notif.innerHTML = "Stocks levels are low";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 4 && $current_vermicast > 0): ?>
                <script>
                    notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'orange';
                    notif.innerHTML = "Stocks levels are critically low!";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 1): ?>
                <script>
                    notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'red';
                    notif.innerHTML = "No stocks!";
                </script>
            <?php endif; ?>
            </h2>

        <div class="info-cards">
            <div class="stat-card">
                <h2>Vermicast Stock</h2>
                <h2><?= $current_vermicast ?? 0 ?> Sacks</h2>
            </div>
            <div class="stat-card">
                <h2>Active Batches</h2>
                <?php 
                require_once "php_backend/db.php";
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM production WHERE status != 'Completed'");
                $stmt->execute();
                $count = $stmt->fetchColumn();

                // Recent production
                $today = date('Y-m-d');
                $stmt_prod = $pdo->prepare("SELECT SUM(quantity), status FROM production WHERE production_date >= ? AND production_date <= ? AND status = 'Completed'");
                $stmt_prod->execute([$today . ' 00:00:00', $today . ' 23:59:59']);
                $prod_count = $stmt_prod->fetchColumn();
                ?>
                <h3><?= $count ?? 0 ?></h3>
            </div>
            <div class="stat-card"><h2>Completed Today</h2>
            <h3><?=(int)$prod_count ?? 0?> Sacks</h3>
            </div>
        </div><!-- info-cards END-->
<!-- In dashboardpage, after info-cards -->
<div class="info-cards">
    <div class="stat-card">
        <h2 id="salesGoal">Sales goal (Target Minimum Stock)</h2>
        <h3><?= htmlspecialchars($salesGoal) ?> Sacks</h3>
        <form method="POST" action="php_backend/setGoal.php">
            <input type="number" id="sales_goal" name="sales_goal" min="0" max="1000000"
                value="<?= htmlspecialchars($salesGoalInv) ?>" style="width:100px;">
            <button type="submit" class="btn-primary">Save Goal</button>
        </form>
    </div>
    <div class="stat-card" style="grid-column: span 2; height: 320px;">
        <div class="card-header card-header-flex">
            <h2>Inventory Trend (<?= $trend === '30' ? '30 Days' : ($trend === '7' ? '7 Days' : 'Recent') ?>)</h2>
            <div class="card-filter">
                <a href="index.php?trend=recent" class="btn-secondary" style="text-decoration:none;padding:6px 10px;<?= $trend === 'recent' ? 'font-weight:bold;' : '' ?>">Recent</a>
                <a href="index.php?trend=7" class="btn-secondary" style="text-decoration:none;padding:6px 10px;<?= $trend === '7' ? 'font-weight:bold;' : '' ?>">Last 7</a>
                <a href="index.php?trend=30" class="btn-secondary" style="text-decoration:none;padding:6px 10px;<?= $trend === '30' ? 'font-weight:bold;' : '' ?>">30 Days</a>
            </div>
        </div>
        <canvas id="stockChart" height="200"></canvas>
    </div>
</div>

<script>
// Fetch data via AJAX or inline PHP
const labels = <?= json_encode($chartLabels) ?>;
const stockData = <?= json_encode($chartData) ?>;
const goalData = <?= json_encode($goalData) ?>;

new Chart(document.getElementById('stockChart'), {
    type: 'line',
    data: { labels, datasets: [{
        label: 'Ending Balance (Sacks)',
        data: stockData,
        borderColor: '#22c55e',
        backgroundColor: 'rgba(34,197,94,0.1)',
        fill: true,
        tension: 0.3,
        pointRadius: 3
    }, {
        label: 'Target Minimum Stock',
        data: goalData,
        borderColor: '#ef4444',
        borderDash: [6, 4],
        pointRadius: 0,
        fill: false,
        tension: 0
    }]},
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true }},
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
    }
});
</script>

    </div> <!-- dashboardpage END-->

</body>
</html>