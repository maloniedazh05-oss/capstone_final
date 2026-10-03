<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff', 'manager']);
// 50KG per 1Sac
$kgRate = 50;
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
    $trendIn[$trend_row['day']] = (float) $trend_row['total_qty'];
}
$trendStmtOut = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
$trendStmtOut->execute([':start' => $chart_start . ' 00:00:00', ':end' => $chart_today . ' 23:59:59']);
$trendOut = [];
while ($trend_row = $trendStmtOut->fetch(PDO::FETCH_ASSOC)) {
    $trendOut[$trend_row['day']] = (float) $trend_row['total_qty'];
}
$trendCur = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status != 'Completed'");
$trendCur->execute();
$trendBal = (float) $trendCur->fetchColumn();
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
    $chartData[] = round($pt['bal'], 2);
}
// Target minimum stock (Sacks). Set by admin/manager on the inventory tab.
$salesGoalFile = __DIR__ . '/php_backend/sales_goal.php';
$salesGoal = file_exists($salesGoalFile) ? max(0, (int) include $salesGoalFile) : 50;
$goalData = array_fill(0, count($chartData), $salesGoal);

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    <?php $NEED_CHART = true;
    require_once "php_backend/head_assets.php"; ?>
</head>

<body>
    <?php require_once "main-sidebar.php"; ?>
    <?php
    // Total stock lives in the single-row `total` ledger (added by completed
    // batches in updateBatch.php, deducted in insertItem.php).
    $vermi = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
    $vermi->execute();

    $current_vermicast = round((float) ($vermi->fetchColumn() ?? 0), 2);
    $vermiFmt = rtrim(rtrim(number_format($current_vermicast, 2, '.', ''), '0'), '.');
    ?>
    <div class="dashboardpage">
        <div class="page-header">
            <h1>Dashboard</h1>
        </div>
        <div style="border-bottom: 1px solid var(--color-border);">
            <?php
            $hour = (int) date('G');
            $greet = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
            ?>
            <h2>Good <?= $greet ?>, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></h2>
        </div>
        <?php
        // Stock notice band driven by the safety-stock goal (G): no gaps,
        // server-rendered so the logos + icon survive.
        if ($current_vermicast >= $salesGoal) {
            $notifClass = 'notif-green';
            $notifIcon = 'fa-circle-check';
            $notifText = "Stocks levels are healthy ({$vermiFmt} Sacks, at/above safety stock of {$salesGoal})";
        } elseif ($current_vermicast >= $salesGoal / 2) {
            $notifClass = 'notif-orange';
            $notifIcon = 'fa-triangle-exclamation';
            $notifText = "Stocks levels are low ({$vermiFmt} Sacks left, safety stock is {$salesGoal})";
        } elseif ($current_vermicast > 0) {
            $notifClass = 'notif-red';
            $notifIcon = 'fa-triangle-exclamation';
            $notifText = "Action required: stock ({$vermiFmt} Sacks) is below safety stock ({$salesGoal})";
        } else {
            $notifClass = 'notif-danger';
            $notifIcon = 'fa-circle-xmark';
            $notifText = "No stocks! Safety stock is {$salesGoal} Sacks.";
        }
        ?>
        <h2 id="dashboard-notif" class="notif <?= $notifClass ?>">
            <i class="fa-solid <?= $notifIcon ?>"></i>
            <span><?= htmlspecialchars($notifText) ?></span>
        </h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="feedback-success">
                <i class="fa-solid fa-circle-check"></i> Safety stock saved successfully!
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="feedback-error">
                <i class="fa-solid fa-circle-exclamation"></i> Could not save the goal. Please try again.
            </div>
        <?php endif; ?>


        <div class="info-cards">
            <div class="stat-card stat-card-green" id="currentStock">
                <h2>Vermicast Stock</h2>
                <h3 id="fertilizerStock" class="conv-val" data-sacks="<?= $current_vermicast ?>" data-unit="KG"><?= rtrim(rtrim(number_format(($current_vermicast ?? 0) * 50, 2, '.', ''), '0'), '.') ?> KG</h3>
                <div class="stat-sub"></div>
            </div>
            <div class="stat-card stat-card-blue">
                <h2>Active Batches</h2>
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM production WHERE status != 'Completed'");
                $stmt->execute();
                $count = $stmt->fetchColumn();

                // Recent production
                $today = date('Y-m-d');
                $stmt_prod = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production WHERE production_date >= ? AND production_date <= ? AND status = 'Completed'");
                $stmt_prod->execute([$today . ' 00:00:00', $today . ' 23:59:59']);
                $prod_count = $stmt_prod->fetchColumn();
                ?>
                <h3><?= $count ?? 0 ?></h3>
            </div>
            <div class="stat-card stat-card-purple">
                <h2>Completed Today</h2>
                <h3><?= rtrim(rtrim(number_format((float) ($prod_count ?? 0), 2, '.', ''), '0'), '.') ?> Sacks</h3>
            </div>
        </div><!-- info-cards END -->
        <!-- In dashboardpage, after info-cards -->
        <div class="info-cards trend-row">
            <div class="stat-card stat-card-amber" style="display: flex; align-items: center; justify-content: center;">
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <h2 id="salesGoal">Safety Stock (Target Minimum Stock)</h2>
                    <h3><?= htmlspecialchars($salesGoal) ?> Sacks</h3>
                    <?php if (in_array($userRole, ['admin', 'manager'])): ?>
                    <form method="POST" action="php_backend/setGoal.php">
                        <input type="number" id="sales_goal" name="sales_goal" min="0" max="1000000"
                            value="<?= htmlspecialchars($salesGoal) ?>" style="width:100px;">
                        <button type="submit" class="btn-primary">Save Goal</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-card trend-card" style="min-height: 380px; justify-content: flex-start; align-items: stretch;">
                <div class="card-header card-header-flex" style="background: none; flex-wrap: wrap; row-gap: 5px; width: 100%; max-width: 100%;">
                    <h2>Inventory Trend (<?= $trend === '30' ? '30 Days' : ($trend === '7' ? '7 Days' : 'Recent') ?>)
                    </h2>
                    <div class="card-filter">
                        <a href="index.php?trend=recent" class="btn-secondary"
                            style="text-decoration:none;<?= $trend === 'recent' ? 'font-weight:bold;' : '' ?>">Recent</a>
                        <a href="index.php?trend=7" class="btn-secondary"
                            style="text-decoration:none;<?= $trend === '7' ? 'font-weight:bold;' : '' ?>">Last
                            7</a>
                        <a href="index.php?trend=30" class="btn-secondary"
                            style="text-decoration:none;<?= $trend === '30' ? 'font-weight:bold;' : '' ?>">30
                            Days</a>
                    </div>
                    </div>
                <div class="trend-chart-wrap" style="position: relative; width: 100%; max-width: 100%; height: 280px; min-width: 0; overflow: hidden;"><canvas id="stockChart" style="max-width: 100%;"></canvas></div>
            </div>
        </div>

        <script>
        // Card value conversion (temporary display only): Sacks x 50 = KG, KG / 50 = Sacks.
        // Desktop (>768px): hover converts, leave restores, taps ignored.
        // Mobile (<=768px): hover disabled, tap toggles original/converted.
        // Crossing the breakpoint resets every card to its original text.
        (function () {
            const isMobile = window.matchMedia('(max-width: 768px)');
            const rate = <?= $kgRate ?>;
            const fmt = v => {
                v = Math.round(v * 100) / 100;
                return Number.isInteger(v) ? String(v) : v.toFixed(2);
            };
            const converted = el => {
                const sacks = parseFloat(el.dataset.sacks || '0');
                const unit = el.dataset.unit || 'Sacks';
                const base = unit === 'KG' ? sacks : sacks * rate;
                const outUnit = unit === 'KG' ? ' Sacks' : ' KG';
                const sign = base < 0 ? '-' : (el.dataset.plus === '1' && base > 0 ? '+' : '');
                return sign + fmt(Math.abs(base)) + outUnit;
            };
            const cards = [];
            document.querySelectorAll('.conv-val').forEach(el => {
                const card = el.closest('.stat-card');
                if (!card) return;
                const state = { el, orig: el.textContent, flipped: false };
                cards.push(state);
                card.addEventListener('mouseenter', () => {
                    if (isMobile.matches) return;
                    el.textContent = converted(el);
                });
                card.addEventListener('mouseleave', () => {
                    if (isMobile.matches) return;
                    el.textContent = state.orig;
                    state.flipped = false;
                });
                card.addEventListener('click', () => {
                    if (!isMobile.matches) return;
                    state.flipped = !state.flipped;
                    el.textContent = state.flipped ? converted(el) : state.orig;
                });
            });
            const resetAll = () => cards.forEach(s => { s.flipped = false; s.el.textContent = s.orig; });
            if (typeof isMobile.addEventListener === 'function') {
                isMobile.addEventListener('change', resetAll);
            }
        })();
        </script>

        <script>
            // Fetch data via AJAX or inline PHP
            const labels = <?= json_encode($chartLabels) ?>;
            const stockData = <?= json_encode($chartData) ?>;
            const goalData = <?= json_encode($goalData) ?>;

            new Chart(document.getElementById('stockChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Ending Balance (Sacks)',
                        data: stockData,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3


                    }, {
                        label: 'Safety Stock',
                        data: goalData,
                        borderColor: '#ef4444',
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    resizeDelay: 100,
                    layout: {
                        autoPadding: true,
                        padding: {
                            right: 4
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                boxWidth: 14,
                                padding: 14
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 0,
                                minRotation: 0,
                                autoSkip: true,
                                autoSkipPadding: 12,
                                maxTicksLimit: 10
                            },
                            grid: {
                                drawTicks: false
                            }
                        },
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

    </div> <!-- dashboardpage END-->

</body>

</html>