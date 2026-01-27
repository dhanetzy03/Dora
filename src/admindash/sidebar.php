<style>
/* Sidebar layout and page shifting when toggled */
.sidebar{
    position:fixed;
    top:0;
    left:0;
    height:100vh;
    width:260px;
    background:#2f3a46;
    overflow:auto;
    transition:width .18s ease, transform .18s ease;
    z-index:1000;
}

body.collapsed .sidebar{ width:70px; }

.main-content{
    margin-left:260px !important;
    transition:margin-left .18s ease;
    padding:20px;
}
body.collapsed .main-content{ margin-left:70px !important; }

/* Hide text labels when collapsed */
body.collapsed .sidebar .brand-text p,
body.collapsed .sidebar .sidebar-nav .nav-item span{
    display:none;
}

/* Mobile: sidebar overlays content */
@media (max-width:800px){
    .sidebar{ transform:translateX(-100%); }
    body.show-sidebar .sidebar{ transform:translateX(0); }
    .main-content{ margin-left:0 !important; }
}
</style>

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
              <span>Products</span>
        </a>
        <a href="sales_validation.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'sales_validation.php' ? 'active' : '' ?>">
            <i class='bx bx-check-circle'></i>
            <span>Sales</span>
        </a>
        <a href="stock_monitoring.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'stock_monitoring.php' ? 'active' : '' ?>">
            <i class='bx bx-line-chart'></i>
            <span>Stocks</span>
        </a>
        <a href="spoilage.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'spoilage.php' ? 'active' : '' ?>">
            <i class='bx bx-trash'></i>
            <span>Spoilage</span>
        </a>
        <a href="inventory_snapshots.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'inventory_snapshots.php' ? 'active' : '' ?>">
            <i class='bx bx-photo-album'></i>
            <span>Beg/End Inventory</span>
        </a>
        <a href="reports_new.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reports_new.php' || basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <i class='bx bx-file'></i>
            <span>Reports</span>
        </a>
    </nav>
</div>
<script>
;(function(){
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    // If previous script set html.sidebar-will-collapse, transfer to body and remove marker
    var htmlElem = document.documentElement;
    if (htmlElem.classList.contains('sidebar-will-collapse')) {
        document.body.classList.add('collapsed');
        htmlElem.classList.remove('sidebar-will-collapse');
        btn.setAttribute('aria-expanded', 'false');
    }

    // Restore saved state from localStorage
    var saved = localStorage.getItem('sidebarCollapsed');
    var isCollapsed = saved === 'true';
    if (isCollapsed) {
        document.body.classList.add('collapsed');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        document.body.classList.remove('collapsed');
        btn.setAttribute('aria-expanded', 'true');
    }

    // Toggle sidebar on button click
    btn.addEventListener('click', function(){
        var nowCollapsed = document.body.classList.toggle('collapsed');
        btn.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
        localStorage.setItem('sidebarCollapsed', nowCollapsed);
    });
})();
</script>