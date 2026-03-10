<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-text">
                <h2>☕ Shukran Café</h2>
                <p>Staff Panel</p>
            </div>
            <button id="sidebarToggle" class="sidebar-toggle" title="Toggle sidebar" aria-expanded="true" aria-label="Toggle sidebar">☰</button>
        </div>
    </div>
    <div class="sidebar-user-header">
        <div class="user-avatar">
            <i class='bx bx-user'></i>
        </div>
        <div class="user-info user-info-centered">
            <div class="user-info-name">Staff User</div>
            <div class="user-info-status">Logged in</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="staff.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : '' ?>">
            <i class='bx bx-grid-alt'></i>
            <span>Dashboard</span>
        </a>
        <!-- Add more staff-specific links here if needed -->
    </nav>
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item">
            <i class='bx bx-log-out'></i>
            <span>Logout</span>
        </a>
    </div>
</div>
<script>
;(function(){
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    var htmlElem = document.documentElement;
    // Initial state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        htmlElem.classList.add('sidebar-will-collapse');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        htmlElem.classList.remove('sidebar-will-collapse');
        btn.setAttribute('aria-expanded', 'true');
    }
    btn.addEventListener('click', function(){
        var isCollapsed = htmlElem.classList.toggle('sidebar-will-collapse');
        btn.setAttribute('aria-expanded', !isCollapsed);
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
})();
</script>
