<?php
session_start();

// Redirect if not logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Get student info
$student_query = $conn->query("SELECT * FROM students WHERE id_number = '$student_id'");
$student = $student_query->fetch_assoc();

// Get announcements (system-wide notifications)
$announcements = $conn->query("SELECT * FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 10");

// Get reservation updates for this student
$reservations = $conn->query("SELECT * FROM reservations WHERE id_number = '$student_id' ORDER BY created_at DESC LIMIT 5");

// Get sit-in session history
$sessions = $conn->query("SELECT * FROM sit_in_sessions WHERE id_number = '$student_id' ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Student - Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 20px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .page-header p { color: #6C7A91; margin-top: 4px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; }
        .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
        .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }
        
        /* Notifications Container */
        .notifications-container { display: flex; flex-direction: column; gap: 20px; }
        .notification-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 20px; border-bottom: 1px solid #F0F2F5; background: #FAFBFF; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #3B82F6; }
        .notification-item { display: flex; align-items: flex-start; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #F1F5F9; transition: all 0.2s; }
        .notification-item:hover { background: #F8FAFE; }
        .notification-item.unread { background: #EFF6FF; }
        .notification-icon { width: 44px; height: 44px; background: #EFF6FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .notification-icon.announcement { background: #FEF3C7; color: #D97706; }
        .notification-icon.reservation { background: #DCFCE7; color: #15803D; }
        .notification-icon.session { background: #DBEAFE; color: #1D4ED8; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 700; margin-bottom: 4px; }
        .notification-message { font-size: 13px; color: #6C7A91; margin-bottom: 6px; }
        .notification-time { font-size: 11px; color: #94A3B8; display: flex; align-items: center; gap: 6px; }
        .mark-read { background: none; border: none; color: #3B82F6; font-size: 12px; cursor: pointer; padding: 4px 8px; border-radius: 8px; }
        .mark-read:hover { background: #EFF6FF; }
        .empty-state { text-align: center; padding: 60px; color: #8A99B0; }
        .empty-icon { font-size: 56px; margin-bottom: 16px; opacity: 0.5; }
        
        @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo-area"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div><div class="logo-text">CCS <span>Student</span></div></div>
    <div class="nav-menu">
        <a href="student_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="student_edit_profile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
        <a href="student_history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
        <a href="student_reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="student_rules.php" class="nav-item"><i class="fas fa-gavel"></i> Lab Rules</a>
        <a href="student_rewards.php" class="nav-item"><i class="fas fa-gift"></i> Rewards/Points</a>
        <a href="student_notifications.php" class="nav-item active"><i class="fas fa-bell"></i> Notifications</a>
    </div>
    <div class="bottom-user">
        <div class="user-avatar"><?php echo strtoupper(substr($student_name, 0, 2)); ?></div>
        <div><h4><?php echo htmlspecialchars($student_name); ?></h4><p>Student</p></div>
        <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> Notifications & Alerts</h1>
            <p>Stay updated with the latest announcements and activity updates</p>
        </div>
        <button onclick="markAllAsRead()" style="background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer;"><i class="fas fa-check-double"></i> Mark All as Read</button>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div><h4>Announcements</h4><div class="number"><?php echo $announcements ? $announcements->num_rows : 0; ?></div></div>
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
        </div>
        <div class="stat-card">
            <div><h4>Reservation Updates</h4><div class="number"><?php echo $reservations ? $reservations->num_rows : 0; ?></div></div>
            <div class="icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card">
            <div><h4>Session Alerts</h4><div class="number"><?php echo $sessions ? $sessions->num_rows : 0; ?></div></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>

    <div class="notifications-container">
        <!-- Announcements Section -->
        <div class="notification-card">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
            </div>
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while ($row = $announcements->fetch_assoc()): ?>
                <div class="notification-item unread">
                    <div class="notification-icon announcement"><i class="fas fa-bullhorn"></i></div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars(substr($row['content'], 0, 150)); ?>...</div>
                        <div class="notification-time">
                            <i class="fas fa-user"></i> CCS Administrator
                            <i class="fas fa-calendar" style="margin-left: 12px;"></i> <?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                    <button class="mark-read" onclick="markRead(this)"><i class="fas fa-check"></i> Mark read</button>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bullhorn empty-icon"></i><div>No announcements yet</div></div>
            <?php endif; ?>
        </div>

        <!-- Reservation Updates -->
        <div class="notification-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Reservation Updates</h3>
            </div>
            <?php if ($reservations && $reservations->num_rows > 0): ?>
                <?php while ($row = $reservations->fetch_assoc()): ?>
                <div class="notification-item">
                    <div class="notification-icon reservation"><i class="fas fa-calendar-check"></i></div>
                    <div class="notification-content">
                        <div class="notification-title">Reservation <?php echo ucfirst($row['status']); ?></div>
                        <div class="notification-message">Your reservation for <?php echo htmlspecialchars($row['laboratory']); ?> on <?php echo date('M d, Y', strtotime($row['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($row['time_in'])); ?> has been <strong><?php echo $row['status']; ?></strong>.</div>
                        <div class="notification-time"><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-calendar-alt empty-icon"></i><div>No reservation updates</div></div>
            <?php endif; ?>
        </div>

        <!-- Session Alerts -->
        <div class="notification-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Session Alerts</h3>
            </div>
            <?php if ($sessions && $sessions->num_rows > 0): ?>
                <?php while ($row = $sessions->fetch_assoc()): ?>
                <div class="notification-item">
                    <div class="notification-icon session"><i class="fas fa-chair"></i></div>
                    <div class="notification-content">
                        <div class="notification-title">Sit-in Session <?php echo ucfirst($row['status']); ?></div>
                        <div class="notification-message">Your sit-in session at <?php echo htmlspecialchars($row['laboratory']); ?> on <?php echo date('M d, Y', strtotime($row['created_at'])); ?> was <strong><?php echo $row['status']; ?></strong>.</div>
                        <div class="notification-time"><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-clock empty-icon"></i><div>No session alerts</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markRead(button) {
    const notificationItem = button.closest('.notification-item');
    notificationItem.classList.remove('unread');
    button.style.display = 'none';
}

function markAllAsRead() {
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    unreadItems.forEach(item => {
        item.classList.remove('unread');
        const markBtn = item.querySelector('.mark-read');
        if (markBtn) markBtn.style.display = 'none';
    });
    alert('All notifications marked as read!');
}
</script>
</body>
</html>