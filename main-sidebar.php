<div class="main-sidebar">
    <ul>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li><a href='dashboard.php'>Dashboard</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
            <li><a href='inventory.php'>Inventory</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li><a href='production.php'>Production</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li><a href='sales.php'>Sales</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li><a href='forecasting.php'>Forecasting</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li><a href='reports.php'>Reports</a></li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li><a href='users.php'>Users</a></li>
        <?php endif; ?>
    </ul>
    <div class="currentUser">
    <h3><?=$_SESSION['user_name'] ?? ''?></h3>
    <h4><?=$_SESSION['user_role'] ?? ''?></h4>
    </div>
    
</div>