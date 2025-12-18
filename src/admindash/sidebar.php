<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-text">
                <h2>☕ Shukran Café</h2>
                <p>Admin Panel</p>
            </div>
            <button id="sidebarToggle" class="sidebar-toggle" title="Toggle sidebar" aria-expanded="true" aria-label="Toggle sidebar">☰</button>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">
            <i class='bx bx-grid-alt'></i>
            <span>Dashboard</span>
        </a>
        <a href="inventory.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '' ?>">
            <i class='bx bx-package'></i>
            <span>Inventory</span>
        </a>
        <a href="raw_materials.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'raw_materials.php' ? 'active' : '' ?>">
            <i class='bx bx-leaf'></i>
            <span>Raw Materials</span>
        </a>
        <a href="sales_validation.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'sales_validation.php' ? 'active' : '' ?>">
            <i class='bx bx-check-circle'></i>
            <span>Sales Validation</span>
        </a>
        <a href="stock_monitoring.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'stock_monitoring.php' ? 'active' : '' ?>">
            <i class='bx bx-line-chart'></i>
            <span>Stock Monitoring</span>
        </a>
        <a href="reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <i class='bx bx-file'></i>
            <span>Reports</span>
        </a>
    </nav>
</div>
<script>
;(function(){
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    
    // If head class exists, transfer to body and remove from head
    var htmlElem = document.documentElement;
    if (htmlElem.classList.contains('sidebar-will-collapse')) {
        document.body.classList.add('collapsed');
        htmlElem.classList.remove('sidebar-will-collapse');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        document.body.classList.remove('collapsed');
        btn.setAttribute('aria-expanded', 'true');
    }
    
    // Toggle sidebar on button click
    btn.addEventListener('click', function(){
        var isCollapsed = document.body.classList.toggle('collapsed');
        btn.setAttribute('aria-expanded', !isCollapsed);
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
})();
</script>