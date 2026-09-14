<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff']);

// 50KG per 1Sac
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href='style.css'>
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

        <p id="dashboard-notif">
        </p>
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


        <div class="info-cards">
            <div class="stat-card">
                <h2>Vermicast Stock</h2>
                <p><?= $current_vermicast ?? 0 ?></p>
            </div>
            <div class="stat-card">
                <h2>Next Period Stockout Prediction</h2>
            </div>
            <div class="stat-card"><h2 id="goalText">Monthly sales Goal</h2></div>
        </div><!-- info-cards END-->
        
        <h2>Monthly Sales Target</h2>
        <input type="number" min="0" id="salesInput" value="0">
    </div> <!-- dashboardpage END-->
<script>
    // Target Goal auto-fetch value ready for calculation
    const salesInput = document.getElementById('salesInput');

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("goalText").innerHTML = "Monthly sales Goal<br><br>" + localStorage.getItem("salesGoal");

        salesInput.addEventListener("change", () => {
            document.getElementById("goalText").innerHTML = "Monthly sales Goal<br><br>" + salesInput.value;
            localStorage.setItem("salesGoal", salesInput.value)
        });
    });
</script>
</body>
</html>