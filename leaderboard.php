<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}
$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_name = $_SESSION['admin_name'] ?? 'CCS Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get students ordered by points
$result = $conn->query("SELECT id_number, first_name, last_name, sessions, COALESCE(total_points, 0) as points FROM students ORDER BY total_points DESC LIMIT 20");

// Get additional statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_points = $conn->query("SELECT SUM(total_points) as sum FROM students")->fetch_assoc()['sum'] ?? 0;
$avg_points = $total_students > 0 ? round($total_points / $total_students) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Leaderboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
        .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 700; display: none; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
        .nav-item:hover { background: #F1F5F9; color: #1E293B; }
        .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 20px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        .logout-icon:hover { opacity: 0.8; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .stat-card h4 { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6C7A91; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
        .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }
        
        /* Leaderboard Container */
        .leaderboard-container { background: white; border-radius: 24px; overflow: hidden; border: 1px solid #EFF3F8; }
        .leaderboard-header { background: linear-gradient(135deg, #0F172A, #1E293B); color: white; padding: 30px; text-align: center; }
        .leaderboard-header h1 { font-size: 26px; font-weight: 700; margin-bottom: 8px; }
        .leaderboard-header p { font-size: 14px; opacity: 0.8; }
        
        /* Rank Badges */
        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; font-weight: bold; font-size: 18px; margin-right: 15px; background: #E2E8F0; color: #1E293B; }
        .rank-1 { background: #FBBF24; color: #000; box-shadow: 0 0 0 3px rgba(251,191,36,0.3); }
        .rank-2 { background: #C0C0C0; color: #000; }
        .rank-3 { background: #CD7F32; color: #fff; }
        
        /* Student Rows */
        .student-row { display: flex; align-items: center; padding: 16px 24px; border-bottom: 1px solid #F0F2F5; transition: all 0.2s; }
        .student-row:hover { background: #F8FAFE; }
        .points { margin-left: auto; font-weight: bold; font-size: 20px; color: #3B82F6; }
        .student-avatar { width: 45px; height: 45px; background: #EFF6FF; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-weight: 700; font-size: 16px; color: #3B82F6; }
        .student-name { font-weight: 700; font-size: 15px; }
        .student-id { font-size: 12px; color: #6C7A91; margin-top: 2px; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px; color: #8A99B0; }
        .empty-icon { font-size: 56px; margin-bottom: 16px; opacity: 0.5; }
        
        @media (max-width: 1000px) { 
            .main-content { margin-left: 0; padding: 20px; } 
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- UNIFIED SIDEBAR - Same as admin_dashboard.php -->
<div class="sidebar">
    <div class="logo-area">
        <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('adminFallbackLogo').style.display='flex';">
        <div id="adminFallbackLogo" class="logo-icon" style="display: none;">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="logo-text">CCS <span>Admin</span></div>
    </div>
    <div class="nav-menu">
        <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
        <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
        <a href="sit_in_management.php" class="nav-item"><i class="fas fa-chair"></i> Sit-in</a>
        <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-pie"></i> Reports</a>
        <a href="leaderboard.php" class="nav-item active"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="add_points.php" class="nav-item"><i class="fas fa-plus-circle"></i> Add Perusal/Point</a>
        <a href="view_performance.php" class="nav-item"><i class="fas fa-chart-simple"></i> View Performance</a>
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

<div class="main-content">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <h4><i class="fas fa-users"></i> Total Students</h4>
                <div class="number"><?php echo $total_students; ?></div>
            </div>
            <div class="icon"><i class="fas fa-graduation-cap"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <h4><i class="fas fa-star"></i> Total Points</h4>
                <div class="number">⭐ <?php echo number_format($total_points); ?></div>
            </div>
            <div class="icon"><i class="fas fa-trophy"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <h4><i class="fas fa-chart-line"></i> Average Points</h4>
                <div class="number">⭐ <?php echo $avg_points; ?></div>
            </div>
            <div class="icon"><i class="fas fa-chart-simple"></i></div>
        </div>
    </div>

    <!-- Leaderboard Container -->
    <div class="leaderboard-container">
        <div class="leaderboard-header">
            <h1><i class="fas fa-trophy"></i> CCS Student Leaderboard</h1>
            <p>Top performers based on points earned</p>
        </div>
        <div style="padding: 20px 0;">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $rank = 1; while ($row = $result->fetch_assoc()): ?>
                <div class="student-row">
                    <div class="rank-badge <?php echo $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : '')); ?>"><?php echo $rank++; ?></div>
                    <div class="student-avatar"><?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?></div>
                    <div>
                        <div class="student-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                        <div class="student-id"><?php echo $row['id_number']; ?></div>
                    </div>
                    <div class="points">⭐ <?php echo $row['points']; ?> pts</div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line empty-icon"></i>
                    <div style="font-size: 16px; font-weight: 500; margin-bottom: 8px;">No data available</div>
                    <span style="font-size: 13px;">No students found in the system.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>