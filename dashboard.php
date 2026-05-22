<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id'];

// Fetch fresh data from database
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate sessions remaining (30 - sessions_used)
$sessions_total = 30;
$sessions_used = $user['sessions_used'] ?? 0;
$sessions_remaining = $sessions_total - $sessions_used;

// Get active reservations count
$reservation_stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE student_id = ? AND status = 'pending'");
$reservation_stmt->bind_param("i", $user_id);
$reservation_stmt->execute();
$active_reservations = $reservation_stmt->get_result()->fetch_assoc()['count'];
$reservation_stmt->close();

// Update session variables
$_SESSION['name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];
$_SESSION['course'] = $user['course'];
$_SESSION['course_level'] = $user['course_level'];
$_SESSION['email'] = $user['email'];
$_SESSION['address'] = $user['address'];
$_SESSION['id_number'] = $user['id_number'];
$_SESSION['sessions_used'] = $sessions_used;
$_SESSION['sessions'] = $sessions_remaining;
$_SESSION['profile_pic'] = $user['profile_pic'];

// Get announcements from database
$announcement_stmt = $conn->prepare("SELECT * FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
$announcement_stmt->execute();
$announcements = $announcement_stmt->get_result();
$announcement_stmt->close();

// Fetch notifications from database
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$db_notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

$student_name = htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']));
$student_course = htmlspecialchars($user['course'] ?? 'N/A');
$student_year = htmlspecialchars($user['course_level'] ?? 'N/A');
$student_email = htmlspecialchars($user['email'] ?? 'N/A');
$student_address = htmlspecialchars($user['address'] ?? 'N/A');
$profile_pic = htmlspecialchars($user['profile_pic'] ?? '');
$student_id = htmlspecialchars($user['id_number'] ?? 'N/A');
$current_points = $user['total_points'] ?? 0;
$initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

// Convert notifications to JSON for JavaScript
$notifications_json = json_encode(array_map(function($n) {
    return [
        'id' => $n['id'],
        'title' => $n['title'],
        'message' => $n['message'],
        'type' => $n['type'],
        'time' => date('M j, g:i A', strtotime($n['created_at'])),
        'read' => (bool)$n['is_read']
    ];
}, $db_notifications));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS | Student Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: #F5F7FB; font-family: 'Inter', sans-serif; color: #1E293B; display: flex; min-height: 100vh; }
    
    /* Sidebar */
    .sidebar { width: 280px; background: #FFFFFF; border-right: 1px solid #E9EEF3; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; padding: 28px 20px; }
    .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
    .logo-image { width: 42px; height: 42px; object-fit: contain; border-radius: 12px; }
    .logo-icon { background: #3B82F6; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; display: none; }
    .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
    .logo-text span { color: #3B82F6; }
    .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
    .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
    .nav-item i { width: 22px; color: #7E8BA0; }
    .nav-item:hover { background: #F1F5F9; color: #1E293B; }
    .nav-item.active { background: #EFF6FF; color: #3B82F6; }
    .nav-item.active i { color: #3B82F6; }
    .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
    .user-avatar { width: 44px; height: 44px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
    .user-details h4 { font-size: 14px; font-weight: 700; }
    .user-details p { font-size: 12px; color: #6C7A91; }
    .logout-icon { margin-left: auto; color: #EF4444; background: none; border: none; cursor: pointer; font-size: 18px; }
    
    /* Main Content */
    .main-content { margin-left: 280px; flex: 1; padding: 28px 36px; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
    .page-breadcrumb h1 { font-size: 26px; font-weight: 700; }
    .breadcrumb-links { display: flex; align-items: center; gap: 8px; margin-top: 6px; font-size: 13px; color: #64748B; }
    .breadcrumb-links i { font-size: 10px; }
    .breadcrumb-links span:last-child { color: #3B82F6; font-weight: 500; }
    .header-actions { display: flex; gap: 16px; align-items: center; }
    .notif-btn { background: white; border-radius: 40px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 1px solid #E9EEF3; cursor: pointer; position: relative; }
    .notif-dot { position: absolute; top: 10px; right: 12px; width: 8px; height: 8px; background: #EF4444; border-radius: 50%; display: none; }
    .notif-panel { position: absolute; top: 60px; right: 20px; width: 360px; max-height: 480px; background: white; border-radius: 20px; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.15); border: 1px solid #E9EEF3; z-index: 1000; display: none; flex-direction: column; }
    .notif-panel.show { display: flex; }
    .notif-header { padding: 16px 20px; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: center; }
    .notif-header h4 { font-size: 15px; font-weight: 700; }
    .mark-read { background: none; border: none; color: #3B82F6; font-size: 11px; cursor: pointer; }
    .notif-list { flex: 1; overflow-y: auto; max-height: 400px; }
    .notif-item { padding: 14px 20px; border-bottom: 1px solid #F0F2F5; display: flex; gap: 12px; cursor: pointer; }
    .notif-item.unread { background: #EFF6FF; }
    .notif-icon { width: 36px; height: 36px; background: #EFF6FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #3B82F6; }
    .notif-content { flex: 1; }
    .notif-title { font-size: 13px; font-weight: 600; }
    .notif-message { font-size: 12px; color: #6C7A91; }
    .notif-time { font-size: 10px; color: #94A3B8; margin-top: 6px; }
    .empty-notif { padding: 40px 20px; text-align: center; color: #94A3B8; }
    .student-chip { background: white; border-radius: 40px; padding: 6px 18px; display: flex; align-items: center; gap: 10px; border: 1px solid #E9EEF3; font-size: 13px; }
    
    /* Stats Cards */
    .stats-row { display: flex; gap: 24px; margin-bottom: 32px; }
    .stat-card { background: white; border-radius: 24px; padding: 20px 24px; flex: 1; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; }
    .stat-left { flex: 1; }
    .stat-title { font-size: 13px; font-weight: 600; color: #5B6E8C; text-transform: uppercase; margin-bottom: 12px; }
    .stat-number { font-size: 34px; font-weight: 800; color: #0F172A; }
    .stat-icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 22px; }
    
    /* Mid Row - 3 columns */
    .mid-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
    .card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; padding: 24px; }
    .card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
    .card-header i { font-size: 20px; color: #3B82F6; background: #EFF6FF; padding: 8px; border-radius: 14px; }
    .card-header h3 { font-size: 18px; font-weight: 700; }
    .student-info-row { display: flex; flex-direction: column; gap: 16px; }
    .info-item { display: flex; gap: 12px; padding: 8px 0; border-bottom: 1px solid #F0F2F5; }
    .info-icon { width: 32px; color: #3B82F6; }
    .info-label { font-size: 11px; font-weight: 600; color: #6C7A91; text-transform: uppercase; }
    .info-value { font-size: 14px; font-weight: 500; color: #1E293B; }
    .avatar-large { display: flex; justify-content: center; margin-bottom: 16px; }
    .avatar-circle-large { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border: 3px solid #3B82F6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .avatar-circle-large img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-circle-large svg { width: 55px; height: 55px; fill: #3B82F6; }
    .announce-meta { font-size: 11px; font-weight: 600; color: #3B82F6; margin-bottom: 6px; }
    .announce-box { background: #F8FAFE; border-left: 3px solid #3B82F6; padding: 14px 16px; border-radius: 14px; font-size: 13px; margin-bottom: 16px; color: #334155; }
    
    /* Rewards Widget */
    .rewards-progress { margin-bottom: 20px; }
    .progress-label { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
    .progress-bar-bg { height: 10px; background: #E2E8F0; border-radius: 10px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #3B82F6, #10B981); border-radius: 10px; width: 0%; }
    .reward-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #F8FAFE; border-radius: 12px; margin-bottom: 10px; }
    .reward-item:last-child { margin-bottom: 0; }
    .btn-claim { background: #3B82F6; color: white; border: none; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-claim:hover { background: #2563EB; transform: translateY(-1px); }
    .btn-claim.disabled { background: #CBD5E1; cursor: not-allowed; }
    .view-all-link { display: block; text-align: center; margin-top: 16px; font-size: 12px; color: #3B82F6; text-decoration: none; }
    .view-all-link:hover { text-decoration: underline; }
    
    /* Footer Card */
    .footer-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border-radius: 24px; padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; }
    .footer-info { display: flex; align-items: center; gap: 16px; }
    .footer-avatar { width: 48px; height: 48px; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; font-size: 18px; }
    .footer-name h4 { color: white; font-size: 16px; }
    .footer-name p { color: #94A3B8; font-size: 12px; }
    .footer-sessions .label { color: #94A3B8; font-size: 11px; text-transform: uppercase; }
    .footer-sessions .value { color: white; font-size: 24px; font-weight: 700; }
    
    @media (max-width: 1000px) { .main-content { margin-left: 0; } .stats-row { flex-direction: column; } .mid-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('studentFallbackLogo').style.display='flex';">
    <div id="studentFallbackLogo" class="logo-icon" style="display: none;">
      <i class="fas fa-user-graduate"></i>
    </div>
    <div class="logo-text"><span>CCS</span> Student</div>
  </div>
  <div class="nav-menu">
    <a href="dashboard.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="editProfile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="student_rules.php" class="nav-item"><i class="fas fa-gavel"></i> Lab Rules</a>
    <a href="student_rewards.php" class="nav-item"><i class="fas fa-gift"></i> Rewards/Points</a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $initials; ?></div>
    <div class="user-details">
      <h4><?php echo $student_name; ?></h4>
      <p><?php echo $student_course; ?></p>
    </div>
    <form method="POST" action="logout.php" style="display:inline;">
      <button type="submit" class="logout-icon"><i class="fas fa-sign-out-alt"></i></button>
    </form>
  </div>
</div>

<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb">
      <h1>CCS Student Dashboard</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i> <span>Dashboard</span> <i class="fas fa-chevron-right"></i> <span>Overview</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="notif-btn" id="notifBtn" onclick="toggleNotifications()">
        <i class="far fa-bell"></i>
        <div class="notif-dot" id="notifDot"></div>
      </div>
      <div class="notif-panel" id="notifPanel">
        <div class="notif-header">
          <h4>Notifications</h4>
          <button class="mark-read" onclick="markAllAsRead()">Mark all as read</button>
        </div>
        <div class="notif-list" id="notifList"></div>
      </div>
      <div class="student-chip"><i class="fas fa-user"></i> CCS Student · <strong><?php echo $student_course; ?></strong></div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Sessions Used</div>
        <div class="stat-number"><?php echo $sessions_used; ?></div>
      </div>
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Sessions Remaining</div>
        <div class="stat-number"><?php echo $sessions_remaining; ?></div>
      </div>
      <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Active Reservations</div>
        <div class="stat-number"><?php echo $active_reservations; ?></div>
      </div>
      <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    </div>
  </div>

  <div class="mid-row">
    <!-- Student Information Card -->
    <div class="card">
      <div class="card-header"><i class="fas fa-id-card"></i><h3>Student Information</h3></div>
      <div class="avatar-large">
        <div class="avatar-circle-large">
          <?php if (!empty($profile_pic) && file_exists($profile_pic)): ?>
            <img src="<?= $profile_pic ?>" alt="Profile">
          <?php else: ?>
            <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          <?php endif; ?>
        </div>
      </div>
      <div class="student-info-row">
        <div class="info-item"><div class="info-icon"><i class="fas fa-user"></i></div><div class="info-content"><div class="info-label">Full Name</div><div class="info-value"><?= $student_name ?></div></div></div>
        <div class="info-item"><div class="info-icon"><i class="fas fa-id-card"></i></div><div class="info-content"><div class="info-label">ID Number</div><div class="info-value"><?= $student_id ?></div></div></div>
        <div class="info-item"><div class="info-icon"><i class="fas fa-graduation-cap"></i></div><div class="info-content"><div class="info-label">Course</div><div class="info-value"><?= $student_course ?></div></div></div>
        <div class="info-item"><div class="info-icon"><i class="fas fa-layer-group"></i></div><div class="info-content"><div class="info-label">Year Level</div><div class="info-value"><?= $student_year ?></div></div></div>
        <div class="info-item"><div class="info-icon"><i class="fas fa-envelope"></i></div><div class="info-content"><div class="info-label">Email</div><div class="info-value"><?= $student_email ?></div></div></div>
        <div class="info-item"><div class="info-icon"><i class="fas fa-map-marker-alt"></i></div><div class="info-content"><div class="info-label">Address</div><div class="info-value"><?= $student_address ?></div></div></div>
      </div>
    </div>

    <!-- Announcements Card -->
    <div class="card">
      <div class="card-header"><i class="fas fa-bullhorn"></i><h3>Announcements</h3></div>
      <?php if ($announcements && $announcements->num_rows > 0): ?>
        <?php while ($announce = $announcements->fetch_assoc()): ?>
          <div class="announce-meta"><?= htmlspecialchars($announce['created_by']) ?> | <?= date('M j, Y', strtotime($announce['created_at'])) ?></div>
          <div class="announce-box"><?= nl2br(htmlspecialchars($announce['content'])) ?></div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="announce-meta">CCS Admin | <?= date('M j, Y') ?></div>
        <div class="announce-box">No announcements available at this time.</div>
      <?php endif; ?>
    </div>

    <!-- Rewards/Points Widget Card -->
    <div class="card">
      <div class="card-header"><i class="fas fa-gift"></i><h3>My Rewards & Points</h3></div>
      
      <?php
      // Calculate points progress
      $max_points = 2000;
      $points_percentage = min(($current_points / $max_points) * 100, 100);
      $points_remaining = $max_points - $current_points;
      ?>
      
      <div class="rewards-progress">
        <div class="progress-label">
          <span><i class="fas fa-star" style="color: #F59E0B;"></i> Points Progress</span>
          <span style="font-weight: 700; color: #3B82F6;"><?php echo number_format($current_points); ?> / <?php echo number_format($max_points); ?></span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-fill" style="width: <?php echo $points_percentage; ?>%;"></div>
        </div>
        <div style="font-size: 11px; color: #6C7A91; margin-top: 8px;">
          <?php echo $points_remaining > 0 ? "✨ $points_remaining more points to reach the next reward!" : "🎉 Congratulations! You've reached the maximum points!"; ?>
        </div>
      </div>
      
      <div style="margin-top: 16px;">
        <div style="font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 12px;">🎁 Available Rewards</div>
        
        <div class="reward-item">
          <div><i class="fas fa-coffee" style="color: #D97706; width: 24px;"></i> <span style="font-size: 13px;">10% Off Canteen Voucher</span></div>
          <button class="btn-claim" onclick="claimReward('Canteen Voucher', 20, <?php echo $current_points; ?>)">Claim (20 pts)</button>
        </div>
        
        <div class="reward-item">
          <div><i class="fas fa-print" style="color: #8B5CF6; width: 24px;"></i> <span style="font-size: 13px;">Free Printing (10 pages)</span></div>
          <button class="btn-claim" onclick="claimReward('Free Printing', 35, <?php echo $current_points; ?>)">Claim (35 pts)</button>
        </div>
        
        <div class="reward-item">
          <div><i class="fas fa-certificate" style="color: #F59E0B; width: 24px;"></i> <span style="font-size: 13px;">CCS Merit Certificate</span></div>
          <button class="btn-claim" onclick="claimReward('Merit Certificate', 50, <?php echo $current_points; ?>)">Claim (50 pts)</button>
        </div>
      </div>
      
      <a href="student_rewards.php" class="view-all-link">
        View All Rewards <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </div>

  <!-- Footer Card -->
  <div class="footer-card">
    <div class="footer-info">
      <div class="footer-avatar"><?php echo $initials; ?></div>
      <div class="footer-name">
        <h4><?= $student_name ?></h4>
        <p><?= $student_course ?> · <?= $student_id ?></p>
      </div>
    </div>
    <div class="footer-sessions">
      <div class="label">Remaining Sessions</div>
      <div class="value"><?= $sessions_remaining ?> <span style="font-size:12px;color:#3B82F6;">left</span></div>
    </div>
  </div>
</div>

<script>
  let notifications = <?php echo $notifications_json; ?>;
  let unreadCount = notifications.filter(n => !n.read).length;

  function updateNotificationBadge() {
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = unreadCount > 0 ? 'block' : 'none';
  }

  function renderNotifications() {
    const list = document.getElementById('notifList');
    if (!list) return;
    if (notifications.length === 0) {
      list.innerHTML = '<div class="empty-notif"><i class="far fa-bell-slash"></i><br>No notifications</div>';
      return;
    }
    list.innerHTML = '';
    notifications.forEach(notif => {
      const item = document.createElement('div');
      item.className = `notif-item ${!notif.read ? 'unread' : ''}`;
      let iconHtml = '<i class="fas fa-bell"></i>';
      if (notif.type === 'announcement') iconHtml = '<i class="fas fa-bullhorn"></i>';
      else if (notif.type === 'reservation') iconHtml = '<i class="fas fa-calendar-check"></i>';
      else if (notif.type === 'profile') iconHtml = '<i class="fas fa-user-check"></i>';
      item.innerHTML = `
        <div class="notif-icon">${iconHtml}</div>
        <div class="notif-content">
          <div class="notif-title">${escapeHtml(notif.title)}</div>
          <div class="notif-message">${escapeHtml(notif.message)}</div>
          <div class="notif-time">${escapeHtml(notif.time)}</div>
        </div>
      `;
      list.appendChild(item);
    });
  }

  function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { if (m === '&') return '&amp;'; if (m === '<') return '&lt;'; if (m === '>') return '&gt;'; return m; }); }

  function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    if (panel) panel.classList.toggle('show');
  }

  function markAllAsRead() {
    fetch('mark_notifications_read.php', { method: 'POST' })
      .then(() => { notifications.forEach(n => n.read = true); unreadCount = 0; updateNotificationBadge(); renderNotifications(); })
      .catch(err => console.error('Error marking notifications as read:', err));
  }

  function claimReward(rewardName, points, currentPoints) {
    if (currentPoints >= points) {
      if (confirm(`Redeem ${rewardName} for ${points} points?`)) {
        alert(`✅ "${rewardName}" has been claimed! Please visit the CCS office to claim your reward.`);
        // Here you can add AJAX call to record redemption in database
      }
    } else {
      alert(`❌ You need ${points - currentPoints} more points to claim this reward.`);
    }
  }

  updateNotificationBadge();
  renderNotifications();

  document.addEventListener('click', function(e) {
    const panel = document.getElementById('notifPanel');
    const btn = document.getElementById('notifBtn');
    if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
      panel.classList.remove('show');
    }
  });
</script>
</body>
</html>