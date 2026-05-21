<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));
?>
<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('fallbackLogo').style.display='flex';">
    <div id="fallbackLogo" class="logo-icon" style="display: none;">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="logo-text">CCS <span>Admin</span></div>
  </div>
  <div class="nav-menu">
    <a href="admin_dashboard.php" class="nav-item <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-line"></i> Dashboard
    </a>
    <a href="students.php" class="nav-item <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
      <i class="fas fa-users"></i> Students
    </a>
    <a href="sit_in_management.php" class="nav-item <?php echo $current_page == 'sit_in_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-chair"></i> Sit-in
    </a>
    <a href="reservation_management.php" class="nav-item <?php echo $current_page == 'reservation_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-calendar-alt"></i> Reservation
    </a>
    <a href="announcement_management.php" class="nav-item <?php echo $current_page == 'announcement_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="leaderboard.php" class="nav-item <?php echo $current_page == 'leaderboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-trophy"></i> Leaderboard
    </a>
    <a href="rewards_points.php" class="nav-item <?php echo $current_page == 'rewards_points.php' ? 'active' : ''; ?>">
      <i class="fas fa-gift"></i> Rewards/Points
    </a>
    <a href="performance.php" class="nav-item <?php echo $current_page == 'performance.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-line"></i> Performance
    </a>
    <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
      <i class="fas fa-cog"></i> Settings
    </a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $admin_initial; ?></div>
    <div class="user-details">
      <h4><?php echo htmlspecialchars($admin_name); ?></h4>
      <p>Administrator</p>
    </div>
    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<style>
.sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; z-index: 10; padding: 28px 20px; overflow-y: auto; }
.logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
.logo-image { width: 45px; height: 45px; object-fit: contain; border-radius: 12px; }
.logo-icon { background: #3B82F6; width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; }
.logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
.logo-text span { color: #3B82F6; }
.nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
.nav-item:hover { background: #F1F5F9; color: #1E293B; }
.nav-item.active { background: #EFF6FF; color: #3B82F6; }
.nav-item i { width: 22px; }
.bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
.user-details h4 { font-size: 14px; font-weight: 700; color: #0F172A; }
.user-details p { font-size: 12px; color: #6C7A91; }
.logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
@media (max-width: 1000px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; transition: transform 0.3s; } }
</style>