<?php
$currentPage = strtolower(basename($_SERVER['PHP_SELF']));
$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';
?>
<div class="main-sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-title">
            <i class="fa-solid fa-leaf"></i>
            <span>VERMICAST</span>
        </div>
        <div class="brand-subtitle">ERP System</div>
        <button type="button" class="mobile-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <ul class="sidebar-nav">
        <?php if (in_array($userRole, ['admin', 'staff'])): ?>
            <li class="<?=$currentPage == 'dashboard.php' ? 'active' : ''?>" data-page="dashboard.php">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin', 'manager'])): ?>
            <li class="<?=$currentPage == 'inventory.php' ? 'active' : ''?>" data-page="inventory.php">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Inventory</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin', 'staff'])): ?>
            <li class="<?=$currentPage == 'production.php' ? 'active' : ''?>" data-page="production.php">
                <i class="fa-solid fa-industry"></i>
                <span>Production</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin'])): ?>
            <li class="<?=$currentPage == 'sales.php' ? 'active' : ''?>" data-page="sales.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Sales</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin'])): ?>
            <li class="<?=$currentPage == 'forecasting.php' ? 'active' : ''?>" data-page="forecasting.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Forecasting</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin'])): ?>
            <li class="<?=$currentPage == 'reports.php' ? 'active' : ''?>" data-page="reports.php">
                <i class="fa-solid fa-file-lines"></i>
                <span>Reports</span>
            </li>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin'])): ?>
            <li class="<?=$currentPage == 'users.php' ? 'active' : ''?>" data-page="users.php">
                <i class="fa-solid fa-gear"></i>
                <span>Users</span>
            </li>
        <?php endif; ?>
    </ul>

    <div class="currentUser">
        <div class="user-info">
            <i class="fa-solid fa-circle-user"></i>
            <span><?=htmlspecialchars($userName)?></span>
        </div>
        <div class="user-role-badge"><?=ucfirst(strtolower($userRole))?></div>
        <?php if (!empty($_SESSION['user_id']) || (isset($id) && $id)): ?>
            <form action="php_backend/logout.php" method="POST" class="logout-form">
                <button type="submit" class="logout-btn">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Sidebar navigation
    document.querySelectorAll(".sidebar-nav li").forEach((li) => {
        li.addEventListener("click", () => {
            const page = li.dataset.page;
            if (page) {
                window.location.href = page;
            }
        });
    });

    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainSidebar = document.getElementById('mainSidebar');
    if (sidebarToggle && mainSidebar) {
        sidebarToggle.addEventListener('click', () => {
            mainSidebar.classList.toggle('mobile-open');
        });
    }
</script>