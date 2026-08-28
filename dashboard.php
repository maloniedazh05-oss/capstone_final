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
        <p id="dashboard-notif">
        </p>
    <!-- Stock notficiation dashboard,  -->
            <?php if($current_vermicast > 20): ?>
                <script>
                    const notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'green';
                    notif.innerHTML = "Stocks levels are healthy";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 10 && $current_vermicast < 5): ?>
                <script>
                    const notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'brown';
                    notif.innerHTML = "Stocks levels are low";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 4 && $current_vermicast > 0): ?>
                <script>
                    const notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'orange';
                    notif.innerHTML = "Stocks levels are critically low!";
                </script>
            <?php endif; ?>
            <?php if($current_vermicast < 1): ?>
                <script>
                    const notif = document.getElementById('dashboard-notif');
                    notif.style.color = 'red';
                    notif.innerHTML = "No stocks!";
                </script>
            <?php endif; ?>

Monthly Sales Target: <input type="number" min="0" id="salesInput" value="0">
        <div class="info-cards">
            <div class="stat-card">
                <h2>Vermicast Stock</h2>

                <p><?= $current_vermicast ?? 0 ?></p>
            </div>
            <div class="stat-card">Next Period Stockout Prediction</div>
            <div class="stat-card"><p id="goalText">Monthly sales Goal</p></div>
        </div><!-- info-cards END-->
    </div> <!-- dashboardpage END-->
<script>
    // Target Goal auto-fetch value ready for calculation
    const salesInput = document.getElementById('salesInput');
    let fetchValue = salesInput.value;
    salesInput.addEventListener("change", () => {
    fetchValue = salesInput.value == '' ? 0 : salesInput.value;
    document.getElementById("goalText").textContent = "Monthly sales Goal: " + salesInput.value;
    console.log(fetchValue);
    });
</script>
</body>
</html>