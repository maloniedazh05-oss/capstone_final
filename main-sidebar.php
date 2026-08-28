<div class="main-sidebar">
    <button id="navicon">=</button>
    <h1 id="pagetitle"></h1>
    <ul>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li>Dashboard</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
            <li>Inventory</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li>Production</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li>Sales</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li>Forecasting</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li>Reports</li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
            <li>Users</li>
        <?php endif; ?>
    </ul>
    <div class="currentUser">
    <h3><?=$_SESSION['user_name'] ?? ''?></h3>
    <h4><?=$_SESSION['user_role'] ?? ''?></h4>
    <?php 
    if($id && $user):
    ?>
    <form action="php_backend/logout.php">
        <button type="submit">Logout</button>
    </form>
        <?php endif; ?>
    </div>
</div>
<script>
    document.getElementById('pagetitle').textContent = document.title;
    document.querySelector(".main-sidebar #pagetitle").addEventListener("click", () => {
        const title = document.title.toLowerCase();
        const fetchTitle = title == 'homepage' ? 'index' : title;

        window.location.href = fetchTitle + '.php';
    });

    document.querySelectorAll("li").forEach((e) => {
        e.addEventListener("click", (link) => {
            //console.log(link.target.textContent);
            window.location.href= link.target.textContent + '.php';
        })
    })
</script>