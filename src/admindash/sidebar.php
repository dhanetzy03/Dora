<div class="sidebar">
    <div class="sidebar-header">
        <h2>☕ Shukran Café</h2>
        <p>Admin Panel</p>
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
