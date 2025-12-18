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
        <div class="user-avatar" style="background:#fff3; border-radius:50%; width:48px; height:48px; display:flex; align-items:center; justify-content:center; font-size:24px; color:#fff; margin:0 auto 8px auto;">
            <i class='bx bx-user'></i>
        </div>
        <div class="user-info" style="text-align:center; color:#fff;">
            <div style="font-weight:600; font-size:15px;">Staff User</div>
            <div style="font-size:12px; opacity:0.8;">Logged in</div>
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
<style>
.sidebar-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    padding-bottom: 20px;
}
.sidebar-footer .nav-item {
    border-top: 1px solid rgba(255,255,255,0.08);
    margin-top: 10px;
    padding-top: 18px;
    color: #fff;
    justify-content: flex-start;
}
.sidebar-user-header {
    padding: 24px 0 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 8px;
}
</style>
