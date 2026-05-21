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

// ========== DATA FETCHING FOR ALL SECTIONS ==========

// For Search Section
$search_results = [];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $search_stmt = $conn->prepare("SELECT id_number, first_name, middle_name, last_name, course, year_level, sessions, total_points, email 
                                   FROM students 
                                   WHERE id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR course LIKE ? OR email LIKE ?
                                   ORDER BY last_name ASC LIMIT 10");
    $search_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    $search_stmt->execute();
    $search_results = $search_stmt->get_result();
}

// For Sit-in Section
$current_sitins_query = $conn->query("SELECT s.*, CONCAT(st.first_name, ' ', st.last_name) as student_name 
                                       FROM sit_in_sessions s 
                                       LEFT JOIN students st ON s.id_number = st.id_number 
                                       WHERE s.status = 'active' 
                                       ORDER BY s.time_in DESC");
$current_sitins = [];
if ($current_sitins_query) {
    while ($row = $current_sitins_query->fetch_assoc()) {
        $current_sitins[] = $row;
    }
}

// For Shunt List / Students Section
$students_query = "SELECT id, id_number, first_name, middle_name, last_name, course, year_level, sessions, total_points, email 
                   FROM students ORDER BY last_name ASC LIMIT 8";
$students_result = $conn->query($students_query);

// For Create Announcement Section
$announcements_query = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$announcements = [];
if ($announcements_query) {
    while ($row = $announcements_query->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// For View Sit-in Record Section
$sessions_query = "SELECT s.*, 
                   CONCAT(st.first_name, ' ', st.last_name) as student_full_name,
                   st.course, st.year_level
                   FROM sit_in_sessions s
                   LEFT JOIN students st ON s.id_number = st.id_number
                   ORDER BY s.created_at DESC LIMIT 8";
$sessions_result = $conn->query($sessions_query);

// For Reservation Section
$reservations_query = $conn->query("SELECT r.*, CONCAT(st.first_name, ' ', st.last_name) as student_name,
                                     st.course, st.year_level, st.sessions
                                     FROM reservations r
                                     LEFT JOIN students st ON r.id_number = st.id_number
                                     ORDER BY r.created_at DESC LIMIT 8");
$reservations = [];
if ($reservations_query) {
    while ($row = $reservations_query->fetch_assoc()) {
        $reservations[] = $row;
    }
}

// For Leaderboard Section
$leaderboard_query = $conn->query("SELECT id_number, first_name, last_name, total_points 
                                    FROM students 
                                    ORDER BY total_points DESC, last_name ASC 
                                    LIMIT 8");
$leaderboard = [];
if ($leaderboard_query) {
    $rank = 1;
    while ($row = $leaderboard_query->fetch_assoc()) {
        $row['rank'] = $rank++;
        $leaderboard[] = $row;
    }
}

// For Analytics Section
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_sitins = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions")->fetch_assoc()['count'];
$active_sessions_count = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'active'")->fetch_assoc()['count'];
$completed_sessions = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'completed'")->fetch_assoc()['count'];
$completion_rate = $total_sitins > 0 ? round(($completed_sessions / $total_sitins) * 100) : 0;
$total_points_earned = $conn->query("SELECT SUM(points_earned) as sum FROM sit_in_sessions WHERE status = 'completed'")->fetch_assoc()['sum'] ?? 0;

// Weekly activity for analytics
$weekly_data = [];
$week_labels = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('M d', strtotime("-{$i} weeks", strtotime('monday this week')));
    $week_end = date('M d', strtotime("+6 days", strtotime($week_start)));
    $week_labels[] = "$week_start";
    $start_date = date('Y-m-d', strtotime("-{$i} weeks", strtotime('monday this week')));
    $end_date = date('Y-m-d', strtotime("+6 days", strtotime($start_date)));
    $week_query = $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'");
    $weekly_data[] = $week_query ? $week_query->fetch_assoc()['count'] : 0;
}

// For Add Perusal/Point Section
$all_students_for_points = $conn->query("SELECT id_number, first_name, last_name FROM students ORDER BY last_name ASC");

// For View Performance Section
$performance_query = $conn->query("SELECT id_number, first_name, last_name, total_points, sessions 
                                    FROM students 
                                    ORDER BY total_points DESC LIMIT 10");
$performance_data = [];
if ($performance_query) {
    while ($row = $performance_query->fetch_assoc()) {
        $performance_data[] = $row;
    }
}

// Get course distribution for stats
$course_counts = [];
$course_query = $conn->query("SELECT course, COUNT(*) as count FROM students GROUP BY course");
while ($row = $course_query->fetch_assoc()) {
    $course_counts[$row['course']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CCS Admin - Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #F5F7FB; font-family: 'Inter', sans-serif; color: #1E293B; display: flex; min-height: 100vh; }
  
  /* Sidebar */
  .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; z-index: 10; padding: 28px 20px; overflow-y: auto; }
  .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
  .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
  .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 700; display: none; }
  .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
  .logo-text span { color: #3B82F6; }
  .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; }
  .nav-item:hover { background: #F1F5F9; color: #1E293B; }
  .nav-item.active { background: #EFF6FF; color: #3B82F6; }
  .nav-item i { width: 22px; }
  .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
  .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
  .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
  
  /* Main Content */
  .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
  .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
  .page-breadcrumb h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
  .page-breadcrumb p { font-size: 13px; color: #6C7A91; margin-top: 4px; }
  
  /* Stats Row */
  .stats-row { display: flex; gap: 20px; margin-bottom: 28px; flex-wrap: wrap; }
  .stat-card { background: white; border-radius: 20px; padding: 20px; flex: 1; min-width: 180px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
  .stat-left h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-left .number { font-size: 32px; font-weight: 800; color: #0F172A; }
  .stat-left .trend { font-size: 11px; margin-top: 6px; color: #10B981; }
  .stat-icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 22px; }
  
  /* Section Cards */
  .section-card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; margin-bottom: 28px; overflow: hidden; }
  .section-header { padding: 18px 24px; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: #FAFBFF; }
  .section-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .section-header h3 i { color: #3B82F6; }
  .section-header .btn-link { background: none; border: none; color: #3B82F6; font-weight: 600; cursor: pointer; font-size: 12px; text-decoration: none; }
  .table-wrapper { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6C7A91; background: #FCFDFF; border-bottom: 1px solid #EDF2F7; letter-spacing: 0.5px; }
  td { padding: 12px 16px; font-size: 13px; color: #1E293B; border-bottom: 1px solid #F1F5F9; }
  tr:hover td { background: #F8FAFE; }
  
  /* Badges */
  .badge-approved { background: #DCFCE7; color: #15803D; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  .badge-pending { background: #FEF3C7; color: #D97706; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  .badge-rejected { background: #FEE2E2; color: #DC2626; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  .badge-active { background: #DCFCE7; color: #15803D; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  .badge-completed { background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
  
  /* Action Icons */
  .action-icons { display: flex; gap: 12px; }
  .action-icons i { cursor: pointer; font-size: 16px; transition: opacity 0.2s; }
  .action-icons i:hover { opacity: 0.7; }
  .fa-edit { color: #3B82F6; }
  .fa-trash-alt { color: #EF4444; }
  .fa-check-circle { color: #10B981; }
  .fa-times-circle { color: #EF4444; }
  
  /* Leaderboard */
  .leaderboard-list { padding: 8px 0; }
  .leaderboard-item { display: flex; align-items: center; gap: 14px; padding: 12px 20px; border-bottom: 1px solid #F1F5F9; }
  .leaderboard-rank { font-weight: 800; font-size: 20px; color: #CBD5E1; width: 36px; }
  .leaderboard-rank.top-1 { color: #F59E0B; }
  .leaderboard-rank.top-2 { color: #94A3B8; }
  .leaderboard-rank.top-3 { color: #CD7B3E; }
  .leaderboard-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #3B82F6; }
  .leaderboard-info { flex: 1; }
  .leaderboard-name { font-weight: 700; font-size: 14px; }
  .leaderboard-id { font-size: 11px; color: #6C7A91; }
  .leaderboard-points { font-weight: 700; color: #F59E0B; font-size: 13px; }
  
  /* Forms */
  .announcement-form { padding: 20px; }
  .announcement-form input, .announcement-form textarea { width: 100%; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; margin-bottom: 12px; font-family: 'Inter', sans-serif; font-size: 13px; }
  .announcement-form input:focus, .announcement-form textarea:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
  .announcement-form button { background: #3B82F6; color: white; border: none; padding: 12px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.2s; }
  .announcement-form button:hover { background: #2563EB; }
  
  .add-points-form { display: flex; gap: 12px; padding: 20px; flex-wrap: wrap; align-items: flex-end; background: #F8FAFE; }
  .add-points-form .form-group { flex: 1; min-width: 150px; }
  .add-points-form label { font-size: 11px; font-weight: 600; color: #6C7A91; display: block; margin-bottom: 5px; }
  .add-points-form input, .add-points-form select { width: 100%; padding: 10px 12px; border: 1.5px solid #E2E8F0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 13px; }
  .add-points-form button { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .add-points-form button:hover { background: #2563EB; }
  
  .search-box { display: flex; gap: 10px; background: white; border-radius: 40px; padding: 5px 5px 5px 18px; border: 1.5px solid #E2E8F0; max-width: 380px; }
  .search-box i { color: #94A3B8; }
  .search-box input { border: none; flex: 1; outline: none; font-size: 13px; background: transparent; }
  .search-box button { background: #3B82F6; border: none; padding: 8px 20px; border-radius: 40px; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .search-box button:hover { background: #2563EB; }
  
  .report-buttons { display: flex; gap: 10px; }
  .report-btn { padding: 6px 14px; border-radius: 30px; border: none; font-weight: 600; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
  .report-btn.csv { background: #10B981; color: white; }
  .report-btn.csv:hover { background: #059669; }
  .report-btn.docx { background: #3B82F6; color: white; }
  .report-btn.docx:hover { background: #2563EB; }
  .report-btn.pdf { background: #EF4444; color: white; }
  .report-btn.pdf:hover { background: #DC2626; }
  
  .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
  
  /* Modal */
  .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
  .modal-content { background: white; border-radius: 24px; width: 90%; max-width: 500px; padding: 28px; }
  .modal-content h3 { margin-bottom: 20px; font-size: 20px; }
  .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; margin-bottom: 16px; font-family: 'Inter', sans-serif; }
  .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
  .modal-buttons button { padding: 10px 20px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; }
  .modal-buttons .btn-submit { background: #3B82F6; color: white; }
  .modal-buttons .btn-cancel { background: #F1F5F9; color: #1E293B; }
  
  /* Toast */
  .toast { position: fixed; bottom: 24px; right: 24px; background: #1E293B; color: white; padding: 12px 20px; border-radius: 12px; font-size: 14px; transform: translateY(60px); opacity: 0; transition: all 0.3s; z-index: 9999; }
  .toast.show { transform: translateY(0); opacity: 1; }
  .toast.success { background: #10B981; }
  .toast.error { background: #EF4444; }
  
  .empty-row td { text-align: center; padding: 40px !important; color: #8A99B0; }
  .empty-icon { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.5; }
  
  @media (max-width: 1000px) { 
    .main-content { margin-left: 0; padding: 20px; } 
    .stats-row { flex-direction: column; }
    .two-columns { grid-template-columns: 1fr; }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
  }
  
  @media print {
    .sidebar, .stats-row, .search-box, .report-buttons, .action-icons, .btn-link, .add-points-form { display: none; }
    .main-content { margin-left: 0; padding: 0; }
    .section-card { border: none; page-break-inside: avoid; }
  }
</style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<div class="sidebar">
  <div class="logo-area">
    <img src="ccslogo2.png" alt="CCS Logo" class="logo-image" onerror="this.onerror=null; this.style.display='none'; document.getElementById('adminFallbackLogo').style.display='flex';">
    <div id="adminFallbackLogo" class="logo-icon" style="display: none;">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="logo-text">CCS <span>Admin</span></div>
  </div>
  <div class="nav-menu">
    <a href="admin_dashboard.php" class="nav-item active"><i class="fas fa-chart-line"></i> Dashboard</a>
    <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
    <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
    <a href="sit_in_management.php" class="nav-item"><i class="fas fa-chair"></i> Sit-in</a>
    <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php" class="nav-item"><i class="fas fa-chart-pie"></i> Reports</a>
    <a href="leaderboard.php" class="nav-item"><i class="fas fa-trophy"></i> Leaderboard</a>
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

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb">
      <h1>Admin Dashboard</h1>
      <p>Complete overview of CCS Sit-in Management System</p>
    </div>
  </div>

  <!-- ==================== SECTION 1: STATS ROW (Total Items & Text Compile) ==================== -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-left">
        <h4><i class="fas fa-box"></i> Total No. of Items</h4>
        <div class="number"><?php echo $total_students; ?></div>
        <div class="trend"><i class="fas fa-users"></i> Registered Students</div>
      </div>
      <div class="stat-icon"><i class="fas fa-database"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <h4><i class="fas fa-file-alt"></i> Text Compile</h4>
        <div class="number"><?php echo $total_sitins; ?></div>
        <div class="trend"><i class="fas fa-history"></i> Total Sit-in Records</div>
      </div>
      <div class="stat-icon"><i class="fas fa-scroll"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <h4><i class="fas fa-chart-line"></i> Completion Rate</h4>
        <div class="number"><?php echo $completion_rate; ?>%</div>
        <div class="trend"><i class="fas fa-check-circle"></i> Success Rate</div>
      </div>
      <div class="stat-icon"><i class="fas fa-percent"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <h4><i class="fas fa-star"></i> Total Points Earned</h4>
        <div class="number">⭐ <?php echo number_format($total_points_earned); ?></div>
        <div class="trend"><i class="fas fa-trophy"></i> All Time</div>
      </div>
      <div class="stat-icon"><i class="fas fa-medal"></i></div>
    </div>
  </div>

  <!-- ==================== SECTION 2: SEARCH ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-search"></i> Search Student</h3>
      <span class="btn-link" onclick="window.location.href='Search_Student.php'">Advanced Search →</span>
    </div>
    <div style="padding: 20px;">
      <form method="GET" action="" class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" name="search" placeholder="Search by ID, Name, Course, or Email..." value="<?php echo htmlspecialchars($search_query); ?>">
        <button type="submit"><i class="fas fa-arrow-right"></i> Search</button>
      </form>
      
      <?php if (!empty($search_query) && $search_results && $search_results->num_rows > 0): ?>
        <div style="margin-top: 20px;">
          <div class="table-wrapper">
            <table style="margin-top: 10px;">
              <thead>
                <tr><th>ID Number</th><th>Full Name</th><th>Course</th><th>Year Level</th><th>Sessions Left</th><th>Points</th><tr>
              </thead>
              <tbody>
                <?php while ($row = $search_results->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['course']); ?></td>
                    <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                    <td><?php echo $row['sessions']; ?></td>
                    <td>⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php elseif (!empty($search_query)): ?>
        <div style="margin-top: 20px; text-align: center; padding: 20px; color: #8A99B0;">
          <i class="fas fa-user-graduate" style="font-size: 36px; margin-bottom: 10px; display: block;"></i>
          No students found
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ==================== SECTION 3: SHUNT LIST / STUDENT INFORMATION ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-list-ul"></i> Shunt List / Student Information</h3>
      <button class="btn-link" onclick="window.location.href='Student_Information.php'">View All Students →</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID Number</th><th>Full Name</th><th>Course</th><th>Year Level</th><th>Sessions Left</th><th>Points</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students_result && $students_result->num_rows > 0): ?>
            <?php while ($row = $students_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?></td>
                <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                <td><?php echo $row['sessions']; ?></td>
                <td>⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                <td class="action-icons"><i class="fas fa-edit" title="Edit"></i> <i class="fas fa-trash-alt" title="Delete"></i></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="7"><i class="fas fa-user-graduate empty-icon"></i>No student records found</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ==================== SECTION 4: CREATE ANNOUNCEMENT ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-bullhorn"></i> Create Announcement</h3>
      <button class="btn-link" onclick="window.location.href='announcement_management.php'">Manage All →</button>
    </div>
    <div class="announcement-form">
      <input type="text" id="announcementTitle" placeholder="Enter announcement title">
      <textarea id="announcementContent" rows="3" placeholder="Enter announcement content..."></textarea>
      <button onclick="createAnnouncement()"><i class="fas fa-paper-plane"></i> Publish Announcement</button>
    </div>
    <div class="section-header" style="border-top: 1px solid #F0F2F5;">
      <h3><i class="fas fa-history"></i> Recent Announcements</h3>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Title</th><th>Content</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (count($announcements) > 0): ?>
            <?php foreach ($announcements as $ann): ?>
              <tr>
                <td><?php echo htmlspecialchars(substr($ann['title'], 0, 35)); ?></td>
                <td><?php echo htmlspecialchars(substr($ann['content'], 0, 45)); ?>...</td>
                <td><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></td>
                <td><span class="badge-active">Active</span></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="4"><i class="fas fa-newspaper empty-icon"></i>No announcements yet</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ==================== SECTION 5: CURRENT SIT-IN SESSIONS ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-clock"></i> Current Sit-in Sessions</h3>
      <button class="btn-link" onclick="window.location.href='sit_in_management.php'">Manage Sessions →</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID Number</th><th>Student Name</th><th>Purpose</th><th>Laboratory</th><th>Time In</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if (count($current_sitins) > 0): ?>
            <?php foreach ($current_sitins as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                <td><?php echo date('g:i A', strtotime($row['time_in'])); ?></td>
                <td><span class="badge-active"><i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i> Active</span></td>
                <td class="action-icons"><i class="fas fa-sign-out-alt" style="color:#EF4444;" title="Time Out"></i></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="7"><i class="fas fa-chair empty-icon"></i>No active sit-in sessions</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ==================== SECTION 6: VIEW SIT-IN RECORD + GENERATE REPORTS ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-history"></i> View Sit-in Record</h3>
      <div class="report-buttons">
        <button class="report-btn csv" onclick="generateReport('csv')"><i class="fas fa-file-csv"></i> CSV</button>
        <button class="report-btn docx" onclick="generateReport('docx')"><i class="fas fa-file-word"></i> DOCX</button>
        <button class="report-btn pdf" onclick="generateReport('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
      </div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Date</th><th>Student Name</th><th>ID Number</th><th>Lab</th><th>Purpose</th><th>Time In</th><th>Time Out</th><th>Points</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
            <?php while ($row = $sessions_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($row['student_full_name'] ?? $row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                <td><?php echo date('g:i A', strtotime($row['time_in'])); ?></td>
                <td><?php echo $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : '-'; ?></td>
                <td><?php echo $row['points_earned'] ?? 0; ?></td>
                <td><span class="badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="9"><i class="fas fa-database empty-icon"></i>No sit-in records found</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ==================== SECTION 7: RESERVATION ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-calendar-check"></i> Reservation Management</h3>
      <button class="btn-link" onclick="window.location.href='reservation_management.php'">Manage All →</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID Number</th><th>Student Name</th><th>Course/Year</th><th>Purpose</th><th>Laboratory</th><th>Date</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (count($reservations) > 0): ?>
            <?php foreach ($reservations as $res): ?>
              <tr>
                <td><?php echo htmlspecialchars($res['id_number']); ?></td>
                <td><?php echo htmlspecialchars($res['student_name']); ?></td>
                <td><?php echo htmlspecialchars($res['course'] . ' - ' . $res['year_level']); ?></td>
                <td><?php echo htmlspecialchars($res['purpose']); ?></td>
                <td><?php echo htmlspecialchars($res['laboratory']); ?></td>
                <td><?php echo date('M d, Y', strtotime($res['date'] ?? $res['created_at'])); ?></td>
                <td><span class="badge-<?php echo strtolower($res['status']); ?>"><?php echo ucfirst($res['status']); ?></span></td>
                <td class="action-icons">
                  <i class="fas fa-check-circle" style="color:#10B981;" title="Approve"></i>
                  <i class="fas fa-times-circle" style="color:#EF4444;" title="Reject"></i>
                 </d>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="8"><i class="fas fa-calendar-alt empty-icon"></i>No reservations found</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ==================== SECTION 8: LEADERBOARD ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-trophy"></i> Leaderboard - Top Performers</h3>
      <button class="btn-link" onclick="window.location.href='leaderboard.php'">View Full Leaderboard →</button>
    </div>
    <div class="leaderboard-list">
      <?php if (count($leaderboard) > 0): ?>
        <?php foreach ($leaderboard as $index => $student): ?>
          <div class="leaderboard-item">
            <div class="leaderboard-rank <?php echo $index == 0 ? 'top-1' : ($index == 1 ? 'top-2' : ($index == 2 ? 'top-3' : '')); ?>">#<?php echo $student['rank']; ?></div>
            <div class="leaderboard-avatar"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></div>
            <div class="leaderboard-info">
              <div class="leaderboard-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
              <div class="leaderboard-id"><?php echo htmlspecialchars($student['id_number']); ?></div>
            </div>
            <div class="leaderboard-points">⭐ <?php echo $student['total_points']; ?> pts</div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding: 40px; text-align: center; color: #8A99B0;"><i class="fas fa-chart-line empty-icon"></i>No points data available</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ==================== SECTION 9: ANALYTICS ==================== -->
  <div class="two-columns">
    <div class="section-card">
      <div class="section-header">
        <h3><i class="fas fa-chart-line"></i> Analytics - Weekly Activity</h3>
        <button class="btn-link" onclick="window.location.href='reports.php'">Full Analytics →</button>
      </div>
      <div style="padding: 20px;">
        <canvas id="weeklyChart" height="180"></canvas>
      </div>
    </div>
    <div class="section-card">
      <div class="section-header">
        <h3><i class="fas fa-chart-pie"></i> Course Distribution</h3>
      </div>
      <div style="padding: 20px;">
        <canvas id="courseChart" height="180"></canvas>
        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
          <?php foreach ($course_counts as $course => $count): ?>
            <div style="text-align: center;">
              <div style="font-weight: 700;"><?php echo $course; ?></div>
              <div style="font-size: 20px; font-weight: 800; color: #3B82F6;"><?php echo $count; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== SECTION 10: ADD PERUSAL/POINTS ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-plus-circle"></i> Add Perusal / Points</h3>
      <button class="btn-link" onclick="window.location.href='add_points.php'">Manage Points →</button>
    </div>
    <div class="add-points-form">
      <div class="form-group">
        <label><i class="fas fa-id-card"></i> Student ID Number</label>
        <input type="text" id="pointsStudentId" placeholder="Enter student ID" list="studentList">
        <datalist id="studentList">
          <?php 
          $student_list = $conn->query("SELECT id_number, first_name, last_name FROM students ORDER BY last_name ASC LIMIT 30");
          while ($s = $student_list->fetch_assoc()): 
          ?>
            <option value="<?php echo $s['id_number']; ?>"><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></option>
          <?php endwhile; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label><i class="fas fa-star"></i> Points to Add</label>
        <input type="number" id="pointsToAdd" placeholder="Enter points" min="1" value="5">
      </div>
      <div class="form-group">
        <label><i class="fas fa-tag"></i> Reason</label>
        <select id="pointsReason">
          <option value="Sit-in Completion">Sit-in Completion</option>
          <option value="Lab Assistance">Lab Assistance</option>
          <option value="Event Participation">Event Participation</option>
          <option value="Outstanding Performance">Outstanding Performance</option>
          <option value="Perusal Request">Perusal Request</option>
        </select>
      </div>
      <button onclick="addPoints()"><i class="fas fa-plus"></i> Add Points</button>
    </div>
  </div>

  <!-- ==================== SECTION 11: VIEW PERFORMANCE ==================== -->
  <div class="section-card">
    <div class="section-header">
      <h3><i class="fas fa-chart-simple"></i> View Performance - Top Students</h3>
      <button class="btn-link" onclick="window.location.href='view_performance.php'">Full Performance Report →</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID Number</th><th>Student Name</th><th>Total Points</th><th>Sessions Completed</th><th>Performance Level</th></tr>
        </thead>
        <tbody>
          <?php if (count($performance_data) > 0): ?>
            <?php foreach ($performance_data as $perf): ?>
              <?php 
              $level = $perf['total_points'] >= 100 ? 'Excellent' : ($perf['total_points'] >= 50 ? 'Good' : ($perf['total_points'] >= 10 ? 'Average' : 'Needs Improvement'));
              $levelClass = $perf['total_points'] >= 100 ? 'badge-completed' : ($perf['total_points'] >= 50 ? 'badge-active' : 'badge-pending');
              ?>
              <tr>
                <td><?php echo htmlspecialchars($perf['id_number']); ?></td>
                <td><?php echo htmlspecialchars($perf['first_name'] . ' ' . $perf['last_name']); ?></td>
                <td>⭐ <?php echo $perf['total_points']; ?></td>
                <td><?php echo $perf['sessions']; ?> sessions</d>
                <td><span class="<?php echo $levelClass; ?>" style="padding: 4px 12px; border-radius: 30px;"><?php echo $level; ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="5"><i class="fas fa-chart-line empty-icon"></i>No performance data available</d></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
// Initialize Charts
const ctx1 = document.getElementById('weeklyChart')?.getContext('2d');
if (ctx1) {
  new Chart(ctx1, {
    type: 'line',
    data: { 
      labels: <?php echo json_encode($week_labels); ?>, 
      datasets: [{ 
        label: 'Sit-in Sessions', 
        data: <?php echo json_encode($weekly_data); ?>, 
        borderColor: '#3B82F6', 
        backgroundColor: 'rgba(59,130,246,0.1)', 
        tension: 0.4, 
        fill: true,
        pointBackgroundColor: '#3B82F6',
        pointBorderColor: '#fff',
        pointRadius: 4,
        pointHoverRadius: 6
      }] 
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' } } }
  });
}

const ctx2 = document.getElementById('courseChart')?.getContext('2d');
if (ctx2) {
  new Chart(ctx2, {
    type: 'doughnut',
    data: { 
      labels: <?php echo json_encode(array_keys($course_counts)); ?>, 
      datasets: [{ 
        data: <?php echo json_encode(array_values($course_counts)); ?>, 
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899'],
        borderWidth: 0
      }] 
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
  });
}

// Create Announcement
function createAnnouncement() {
  let title = document.getElementById('announcementTitle').value;
  let content = document.getElementById('announcementContent').value;
  
  if (!title || !content) {
    showToast('Please fill in both title and content', 'error');
    return;
  }
  
  fetch('api/create_announcement_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: title, content: content })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('Announcement published successfully!', 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to create announcement', 'error');
  });
}

// Add Points
function addPoints() {
  let studentId = document.getElementById('pointsStudentId').value;
  let points = parseInt(document.getElementById('pointsToAdd').value);
  let reason = document.getElementById('pointsReason').value;
  
  if (!studentId) {
    showToast('Please enter a student ID', 'error');
    return;
  }
  
  if (isNaN(points) || points <= 0) {
    showToast('Please enter valid points', 'error');
    return;
  }
  
  fetch('api/add_points_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ student_id: studentId, points: points, reason: reason })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(`Added ${points} points to student!`, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to add points', 'error');
  });
}

// Generate Reports
function generateReport(format) {
  if (format === 'csv') {
    exportToCSV();
  } else if (format === 'pdf') {
    window.print();
  } else if (format === 'docx') {
    showToast('DOCX export will be implemented soon', 'error');
  }
}

function exportToCSV() {
  let table = document.querySelector('#sit-in-record-table') || document.querySelector('.table-card table');
  if (!table) {
    showToast('No data to export', 'error');
    return;
  }
  let rows = table.querySelectorAll('tr');
  let csv = [];
  for (let i = 0; i < rows.length; i++) {
    let row = [], cols = rows[i].querySelectorAll('td, th');
    for (let j = 0; j < cols.length; j++) {
      row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
    }
    csv.push(row.join(','));
  }
  let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
  let link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'sit_in_report_<?php echo date('Y-m-d'); ?>.csv';
  link.click();
  showToast('Report exported successfully!', 'success');
}

// Show Toast Message
function showToast(message, type) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = `toast ${type} show`;
  setTimeout(() => toast.classList.remove('show'), 3000);
}

<?php if(isset($success_msg)) echo "showToast('".addslashes($success_msg)."','success');"; ?>
<?php if(isset($error_msg)) echo "showToast('".addslashes($error_msg)."','error');"; ?>
</script>
</body>
</html>