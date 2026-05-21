<?php
// ============================================
// SEARCH_STUDENT.PHP - Enhanced Search and manage students
// ============================================
session_start();

// Redirect if not logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'CCS Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get statistics with proper counts
$total_students_query = "SELECT COUNT(*) as total FROM students";
$total_students_result = $conn->query($total_students_query);
$total_students = $total_students_result->fetch_assoc()['total'];

$total_sitin_query = "SELECT COUNT(*) as total FROM sit_in_sessions WHERE status = 'completed'";
$total_sitin_result = $conn->query($total_sitin_query);
$total_sitin = $total_sitin_result ? $total_sitin_result->fetch_assoc()['total'] : 0;

$current_sitin_query = "SELECT COUNT(*) as total FROM sit_in_sessions WHERE status = 'active'";
$current_sitin_result = $conn->query($current_sitin_query);
$current_sitin = $current_sitin_result ? $current_sitin_result->fetch_assoc()['total'] : 0;

// Get monthly comparison for trends
$last_month_sitins = $conn->query("SELECT COUNT(*) as total FROM sit_in_sessions WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
$prev_month_sitins = $conn->query("SELECT COUNT(*) as total FROM sit_in_sessions WHERE status = 'completed' AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
$trend_percentage = $prev_month_sitins > 0 ? round((($last_month_sitins - $prev_month_sitins) / $prev_month_sitins) * 100) : 0;

// Get active student IDs (currently sitting in)
$active_students = [];
$active_query = $conn->query("SELECT DISTINCT id_number FROM sit_in_sessions WHERE status = 'active'");
if ($active_query) {
    while ($row = $active_query->fetch_assoc()) {
        $active_students[] = $row['id_number'];
    }
}

// Handle search parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'last_name';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Build search conditions
$conditions = [];
$params = [];
$types = "";

if (!empty($search_query)) {
    $conditions[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR id_number LIKE ? OR email LIKE ? OR course LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_course)) {
    $conditions[] = "course = ?";
    $params[] = $filter_course;
    $types .= "s";
}

if (!empty($filter_year)) {
    $conditions[] = "year_level = ?";
    $params[] = $filter_year;
    $types .= "s";
}

// Status filter is applied in PHP (based on active sessions)
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get students with sorting
$valid_sort_columns = ['id_number', 'first_name', 'last_name', 'course', 'year_level', 'sessions', 'total_points'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'last_name';
}
$query = "SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM students $where_clause ORDER BY $sort_by $sort_order";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Process students with real-time status
$processed_students = [];
while ($row = $students->fetch_assoc()) {
    // Determine real status
    $is_sitting = in_array($row['id_number'], $active_students);
    $has_sessions = $row['sessions'] > 0;
    
    if ($is_sitting) {
        $status = 'sitting';
        $status_class = 'status-sitting';
        $status_text = 'Sitting-In';
        $status_icon = 'fa-chair';
    } elseif ($has_sessions) {
        $status = 'active';
        $status_class = 'status-active';
        $status_text = 'Active';
        $status_icon = 'fa-check-circle';
    } else {
        $status = 'inactive';
        $status_class = 'status-offline';
        $status_text = 'Inactive';
        $status_icon = 'fa-circle';
    }
    
    // Apply status filter
    if (!empty($filter_status) && $filter_status != $status) {
        continue;
    }
    
    $row['status'] = $status;
    $row['status_class'] = $status_class;
    $row['status_text'] = $status_text;
    $row['status_icon'] = $status_icon;
    $row['initials'] = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
    $processed_students[] = $row;
}

$student_count = count($processed_students);

// Get course distribution for filter counts
$course_counts = [];
$course_count_query = $conn->query("SELECT course, COUNT(*) as count FROM students GROUP BY course");
if ($course_count_query) {
    while ($row = $course_count_query->fetch_assoc()) {
        $course_counts[$row['course']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>CCS Admin - Search Student</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    background: #F5F7FB;
    font-family: 'Inter', sans-serif;
    color: #1E293B;
    display: flex;
    min-height: 100vh;
  }

  /* ========= SIDEBAR ========= */
  .sidebar {
    width: 260px;
    background: #FFFFFF;
    border-right: 1px solid #E9EEF3;
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 10;
    padding: 28px 20px;
  }

  .logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 40px;
    padding-left: 8px;
  }
  
  .logo-image {
    width: 38px;
    height: 38px;
    object-fit: contain;
    border-radius: 10px;
  }
  
  .logo-icon {
    background: #3B82F6;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    font-weight: 700;
    display: none;
  }
  
  .logo-text {
    font-weight: 800;
    font-size: 20px;
    color: #0F172A;
  }
  .logo-text span {
    color: #3B82F6;
  }

  .nav-menu {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .nav-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 12px;
    color: #5B6E8C;
    font-weight: 500;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s;
  }
  .nav-item i {
    width: 22px;
  }
  .nav-item:hover {
    background: #F1F5F9;
    color: #1E293B;
  }
  .nav-item.active {
    background: #EFF6FF;
    color: #3B82F6;
  }
  .nav-item.active i {
    color: #3B82F6;
  }

  .bottom-user {
    margin-top: auto;
    border-top: 1px solid #EDF2F7;
    padding-top: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .user-avatar {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
  }
  .user-details h4 {
    font-size: 14px;
    font-weight: 700;
  }
  .user-details p {
    font-size: 12px;
    color: #6C7A91;
  }
  .logout-icon {
    margin-left: auto;
    color: #EF4444;
    text-decoration: none;
  }

  /* ========= MAIN CONTENT ========= */
  .main-content {
    margin-left: 260px;
    flex: 1;
    padding: 28px 36px;
  }

  .top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
  }
  .page-breadcrumb h1 {
    font-size: 26px;
    font-weight: 700;
  }
  .breadcrumb-links {
    display: flex;
    gap: 8px;
    margin-top: 6px;
    font-size: 13px;
    color: #6C7A91;
  }
  .breadcrumb-links span:last-child {
    color: #3B82F6;
    font-weight: 500;
  }

  /* Stats Row */
  .stats-row {
    display: flex;
    gap: 24px;
    margin-bottom: 32px;
  }
  .stat-card {
    background: white;
    border-radius: 24px;
    padding: 20px 24px;
    flex: 1;
    border: 1px solid #EFF3F8;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
  }
  .stat-title {
    font-size: 12px;
    font-weight: 600;
    color: #6C7A91;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .stat-number {
    font-size: 34px;
    font-weight: 800;
    color: #0F172A;
  }
  .stat-trend {
    font-size: 12px;
    margin-top: 8px;
  }
  .stat-trend.positive {
    color: #10B981;
  }
  .stat-trend.negative {
    color: #EF4444;
  }
  .stat-icon {
    width: 48px;
    height: 48px;
    background: #EFF6FF;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3B82F6;
    font-size: 22px;
  }

  /* Search Section */
  .search-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    padding: 24px;
    margin-bottom: 24px;
  }
  .search-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
  }
  .search-subtitle {
    font-size: 13px;
    color: #6C7A91;
    margin-bottom: 20px;
  }
  .search-input-group {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
  }
  .search-input-group input {
    flex: 1;
    padding: 14px 18px;
    border: 1.5px solid #E2E8F0;
    border-radius: 14px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    outline: none;
    transition: all 0.2s;
  }
  .search-input-group input:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
  }
  .search-input-group button {
    background: #3B82F6;
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
  }
  .search-input-group button:hover {
    background: #2563EB;
  }
  .clear-search {
    background: #F1F5F9;
    color: #475569;
  }
  .clear-search:hover {
    background: #E2E8F0;
  }

  /* Filter Chips */
  .filter-section {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #F0F2F5;
  }
  .filter-chip {
    padding: 8px 18px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    background: #F1F5F9;
    color: #475569;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .filter-chip i {
    font-size: 12px;
  }
  .filter-chip:hover {
    background: #E2E8F0;
  }
  .filter-chip.active {
    background: #3B82F6;
    color: white;
  }
  .filter-chip .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
  }
  .filter-chip.active .count {
    background: rgba(255,255,255,0.2);
  }

  /* Sorting Bar */
  .sorting-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
  }
  .results-count {
    font-size: 14px;
    font-weight: 600;
    color: #3B82F6;
    background: #EFF6FF;
    padding: 6px 14px;
    border-radius: 40px;
  }
  .sort-options {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .sort-label {
    font-size: 12px;
    color: #6C7A91;
  }
  .sort-link {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    text-decoration: none;
    color: #6C7A91;
    background: #F1F5F9;
  }
  .sort-link.active {
    background: #3B82F6;
    color: white;
  }

  /* Table */
  .table-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    overflow: hidden;
  }
  .table-wrapper {
    overflow-x: auto;
  }
  table {
    width: 100%;
    border-collapse: collapse;
  }
  th {
    text-align: left;
    padding: 16px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #6C7A91;
    background: #FCFDFF;
    border-bottom: 1px solid #EDF2F7;
    letter-spacing: 0.5px;
  }
  td {
    padding: 16px 20px;
    font-size: 13px;
    color: #1E293B;
    border-bottom: 1px solid #F1F5F9;
  }
  tr:hover td {
    background: #F8FAFE;
  }
  
  .student-info {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .student-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    color: #3B82F6;
  }
  .student-details {
    display: flex;
    flex-direction: column;
  }
  .student-name {
    font-weight: 700;
    margin-bottom: 2px;
  }
  .student-email {
    font-size: 11px;
    color: #6C7A91;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
  }
  .status-active {
    background: #DCFCE7;
    color: #15803D;
  }
  .status-sitting {
    background: #FEF3C7;
    color: #B45309;
  }
  .status-offline {
    background: #F1F5F9;
    color: #475569;
  }
  
  .action-icons {
    display: flex;
    gap: 14px;
  }
  .action-icons a {
    text-decoration: none;
  }
  .action-icons i {
    cursor: pointer;
    font-size: 18px;
    transition: transform 0.1s;
  }
  .action-icons i:hover {
    transform: scale(1.1);
  }
  .fa-edit { color: #3B82F6; }
  .fa-trash-alt { color: #EF4444; }
  .fa-eye { color: #10B981; }
  .fa-clock { color: #F59E0B; }

  .empty-row td {
    text-align: center;
    padding: 60px !important;
  }
  .empty-icon {
    font-size: 48px;
    color: #CBD5E1;
    margin-bottom: 16px;
    display: block;
  }

  /* Pagination */
  .pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 20px;
    border-top: 1px solid #F0F2F5;
  }
  .page-btn {
    padding: 8px 14px;
    border-radius: 10px;
    background: #F1F5F9;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
  }
  .page-btn.active {
    background: #3B82F6;
    color: white;
  }
  .page-btn:hover:not(.active) {
    background: #E2E8F0;
  }

  /* Quick Action Modal */
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
  }
  .modal-content {
    background: white;
    border-radius: 24px;
    width: 90%;
    max-width: 450px;
    padding: 28px;
  }
  .modal-content h3 {
    margin-bottom: 20px;
  }
  .modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
  }
  .modal-buttons button {
    padding: 10px 20px;
    border-radius: 40px;
    border: none;
    font-weight: 600;
    cursor: pointer;
  }
  .btn-confirm {
    background: #3B82F6;
    color: white;
  }
  .btn-cancel {
    background: #F1F5F9;
    color: #475569;
  }

  @media (max-width: 1000px) {
    .main-content { margin-left: 0; padding: 20px; }
    .stats-row { flex-direction: column; }
    .search-input-group { flex-direction: column; }
    .sorting-bar { flex-direction: column; align-items: flex-start; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
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
    <a href="Search_Student.php" class="nav-item active"><i class="fas fa-search"></i> Search Student</a>
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

<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb">
      <h1>Student Directory</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i>
        <span>Search Student</span>
      </div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title"><i class="fas fa-clock"></i> Total Sit-in</div>
        <div class="stat-number"><?php echo $total_sitin; ?></div>
        <div class="stat-trend <?php echo $trend_percentage >= 0 ? 'positive' : 'negative'; ?>">
          <i class="fas fa-arrow-<?php echo $trend_percentage >= 0 ? 'up' : 'down'; ?>"></i>
          <?php echo abs($trend_percentage); ?>% since last month
        </div>
      </div>
      <div class="stat-icon"><i class="fas fa-history"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title"><i class="fas fa-chair"></i> Currently Sit-in</div>
        <div class="stat-number"><?php echo $current_sitin; ?></div>
        <div class="stat-trend">Active sessions right now</div>
      </div>
      <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title"><i class="fas fa-user-graduate"></i> Total Students</div>
        <div class="stat-number"><?php echo $total_students; ?></div>
        <div class="stat-trend">+<?php echo $total_students; ?> this semester</div>
      </div>
      <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
    </div>
  </div>

  <!-- Search Section -->
  <div class="search-card">
    <div class="search-title">Find a Student</div>
    <div class="search-subtitle">Search by name, ID number, email, or course — manage sit-in records</div>
    
    <form method="GET" action="Search_Student.php" id="searchForm">
      <div class="search-input-group">
        <input type="text" name="search" id="searchInput" placeholder="Search by name, ID, email, or course..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_course)): ?>
          <a href="Search_Student.php" class="clear-search" style="background:#F1F5F9; color:#475569; padding:14px 28px; border-radius:14px; text-decoration:none; font-weight:600;"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Filter Chips -->
    <div class="filter-section">
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'course' => $filter_course, 'year' => $filter_year])); ?>" class="filter-chip <?php echo empty($filter_status) ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> All Students
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => 'active', 'course' => $filter_course, 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_status == 'active' ? 'active' : ''; ?>">
        <i class="fas fa-check-circle"></i> Active
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => 'sitting', 'course' => $filter_course, 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_status == 'sitting' ? 'active' : ''; ?>">
        <i class="fas fa-chair"></i> Sitting-In <span class="count"><?php echo $current_sitin; ?></span>
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => 'inactive', 'course' => $filter_course, 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_status == 'inactive' ? 'active' : ''; ?>">
        <i class="fas fa-circle"></i> Inactive
      </a>
    </div>

    <!-- Course Filters -->
    <div class="filter-section" style="border-bottom: none; padding-bottom: 0;">
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => $filter_status, 'year' => $filter_year])); ?>" class="filter-chip <?php echo empty($filter_course) ? 'active' : ''; ?>">
        <i class="fas fa-book"></i> All Courses
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => $filter_status, 'course' => 'BSIT', 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSIT' ? 'active' : ''; ?>">
        <i class="fas fa-laptop-code"></i> BSIT <span class="count"><?php echo $course_counts['BSIT'] ?? 0; ?></span>
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => $filter_status, 'course' => 'BSCS', 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSCS' ? 'active' : ''; ?>">
        <i class="fas fa-microchip"></i> BSCS <span class="count"><?php echo $course_counts['BSCS'] ?? 0; ?></span>
      </a>
      <a href="?<?php echo http_build_query(array_filter(['search' => $search_query, 'status' => $filter_status, 'course' => 'BSIS', 'year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSIS' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i> BSIS <span class="count"><?php echo $course_counts['BSIS'] ?? 0; ?></span>
      </a>
    </div>
  </div>

  <!-- Sorting & Results -->
  <div class="sorting-bar">
    <div class="results-count">
      <i class="fas fa-users"></i> <?php echo $student_count; ?> student<?php echo $student_count != 1 ? 's' : ''; ?> found
    </div>
    <div class="sort-options">
      <span class="sort-label"><i class="fas fa-sort"></i> Sort by:</span>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'last_name', 'order' => $sort_by == 'last_name' && $sort_order == 'ASC' ? 'desc' : 'asc'])); ?>" class="sort-link <?php echo $sort_by == 'last_name' ? 'active' : ''; ?>">
        Name <?php if ($sort_by == 'last_name') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
      </a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'id_number', 'order' => $sort_by == 'id_number' && $sort_order == 'ASC' ? 'desc' : 'asc'])); ?>" class="sort-link <?php echo $sort_by == 'id_number' ? 'active' : ''; ?>">
        ID <?php if ($sort_by == 'id_number') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
      </a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total_points', 'order' => $sort_by == 'total_points' && $sort_order == 'ASC' ? 'desc' : 'asc'])); ?>" class="sort-link <?php echo $sort_by == 'total_points' ? 'active' : ''; ?>">
        Points <?php if ($sort_by == 'total_points') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
      </a>
    </div>
  </div>

  <!-- Results Table -->
  <div class="table-card">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>ID Number</th>
            <th>Course & Year</th>
            <th>Sessions Left</th>
            <th>Points</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($student_count > 0): ?>
            <?php foreach ($processed_students as $row): ?>
              <tr>
                <td>
                  <div class="student-info">
                    <div class="student-avatar"><?php echo $row['initials']; ?></div>
                    <div class="student-details">
                      <div class="student-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                      <div class="student-email"><?php echo htmlspecialchars($row['email'] ?? 'No email'); ?></div>
                    </div>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?> - <?php echo htmlspecialchars($row['year_level']); ?></td>
                <td><?php echo $row['sessions']; ?> sessions</d>
                <td>⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                <td>
                  <span class="status-badge <?php echo $row['status_class']; ?>">
                    <i class="fas <?php echo $row['status_icon']; ?>"></i>
                    <?php echo $row['status_text']; ?>
                  </span>
                 </d>
                <td class="action-icons">
                  <a href="Student_Information.php?edit=<?php echo $row['id_number']; ?>" title="Edit Student">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="javascript:void(0)" onclick="showQuickActions('<?php echo $row['id_number']; ?>', '<?php echo htmlspecialchars($row['full_name']); ?>')" title="Quick Actions">
                    <i class="fas fa-bolt" style="color:#8B5CF6;"></i>
                  </a>
                  <a href="Student_Information.php?view=<?php echo $row['id_number']; ?>" title="View Details">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="javascript:void(0)" onclick="confirmDelete('<?php echo $row['id_number']; ?>', '<?php echo htmlspecialchars($row['full_name']); ?>')" title="Delete">
                    <i class="fas fa-trash-alt"></i>
                  </a>
                 </d>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="7">
                <i class="fas fa-user-graduate empty-icon"></i>
                No students found matching your criteria
               </d>
             </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    <?php
    $items_per_page = 15;
    $total_pages = ceil($student_count / $items_per_page);
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($total_pages > 1):
    ?>
    <div class="pagination">
      <?php if ($current_page > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="page-btn"><i class="fas fa-chevron-left"></i> Prev</a>
      <?php endif; ?>
      
      <?php for ($i = 1; $i <= min(5, $total_pages); $i++): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
      
      <?php if ($total_pages > 5): ?>
        <span class="page-btn">...</span>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn"><?php echo $total_pages; ?></a>
      <?php endif; ?>
      
      <?php if ($current_page < $total_pages): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="page-btn">Next <i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Actions Modal -->
<div id="quickActionsModal" class="modal">
  <div class="modal-content">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <p id="modalStudentName" style="margin-bottom: 20px; color: #6C7A91;"></p>
    <div style="display: flex; flex-direction: column; gap: 12px;">
      <button onclick="startSitIn()" style="padding: 12px; background: #3B82F6; color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600;">
        <i class="fas fa-chair"></i> Start Sit-in Session
      </button>
      <button onclick="addPoints()" style="padding: 12px; background: #10B981; color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600;">
        <i class="fas fa-plus-circle"></i> Add Points
      </button>
      <button onclick="viewReservations()" style="padding: 12px; background: #8B5CF6; color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600;">
        <i class="fas fa-calendar-alt"></i> View Reservations
      </button>
    </div>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <h3><i class="fas fa-trash-alt" style="color: #EF4444;"></i> Delete Student</h3>
    <p id="deleteMessage" style="margin: 20px 0;"></p>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn-confirm" style="background: #EF4444;" onclick="executeDelete()">Delete</button>
    </div>
  </div>
</div>

<script>
// Store current student ID for actions
let currentStudentId = null;
let currentStudentName = '';

// Quick Actions Modal
function showQuickActions(studentId, studentName) {
  currentStudentId = studentId;
  currentStudentName = studentName;
  document.getElementById('modalStudentName').innerHTML = `<strong>${studentName}</strong><br><span style="font-size: 12px;">ID: ${studentId}</span>`;
  document.getElementById('quickActionsModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('quickActionsModal').style.display = 'none';
  currentStudentId = null;
}

// Start Sit-in
function startSitIn() {
  if (!currentStudentId) return;
  
  // Get purpose and lab from prompt or use defaults
  const purpose = prompt('Enter purpose (Programming, Thesis, Research, etc.):', 'Programming');
  const lab = prompt('Enter laboratory (Lab 544, Lab 524, Lab 526, Lab 528, Lab 530):', 'Lab 544');
  
  if (purpose && lab) {
    fetch('api/start_sit_in_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        student_id: currentStudentId, 
        purpose: purpose, 
        laboratory: lab 
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Sit-in session started successfully!');
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to start sit-in session');
    });
  }
  closeModal();
}

// Add Points
function addPoints() {
  if (!currentStudentId) return;
  
  const points = prompt('Enter points to add (1-100):', '5');
  const reason = prompt('Reason for points:', 'Sit-in Completion');
  
  if (points && reason) {
    fetch('api/add_points_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        student_id: currentStudentId, 
        points: parseInt(points), 
        reason: reason 
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert(`Added ${points} points to ${currentStudentName}!`);
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to add points');
    });
  }
  closeModal();
}

// View Reservations
function viewReservations() {
  window.location.href = `reservation_management.php?student_id=${currentStudentId}`;
}

// Delete Student
let deleteStudentId = null;
let deleteStudentName = '';

function confirmDelete(studentId, studentName) {
  deleteStudentId = studentId;
  deleteStudentName = studentName;
  document.getElementById('deleteMessage').innerHTML = `Are you sure you want to delete <strong>${studentName}</strong> (ID: ${studentId})? This action cannot be undone.`;
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
  deleteStudentId = null;
}

function executeDelete() {
  if (!deleteStudentId) return;
  
  fetch('api/delete_student_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: deleteStudentId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(`Student ${deleteStudentName} deleted successfully!`);
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to delete student');
  });
  closeDeleteModal();
}

// Close modals when clicking outside
window.onclick = function(event) {
  const quickModal = document.getElementById('quickActionsModal');
  const deleteModal = document.getElementById('deleteModal');
  if (event.target === quickModal) closeModal();
  if (event.target === deleteModal) closeDeleteModal();
}

// Real-time search (debounced)
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    const searchValue = this.value;
    if (searchValue.length >= 2 || searchValue.length === 0) {
      const url = new URL(window.location.href);
      if (searchValue) {
        url.searchParams.set('search', searchValue);
      } else {
        url.searchParams.delete('search');
      }
      window.location.href = url.toString();
    }
  }, 500);
});

// Export functionality
function exportToCSV() {
  let csv = [];
  csv.push(['ID Number', 'Full Name', 'Course', 'Year Level', 'Sessions Left', 'Points', 'Status']);
  
  <?php foreach ($processed_students as $row): ?>
    csv.push([
      '<?php echo addslashes($row['id_number']); ?>',
      '<?php echo addslashes($row['full_name']); ?>',
      '<?php echo addslashes($row['course']); ?>',
      '<?php echo addslashes($row['year_level']); ?>',
      <?php echo $row['sessions']; ?>,
      <?php echo $row['total_points'] ?? 0; ?>,
      '<?php echo $row['status_text']; ?>'
    ]);
  <?php endforeach; ?>
  
  const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { type: 'text/csv' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `students_export_<?php echo date('Y-m-d'); ?>.csv`;
  link.click();
}

// Keyboard shortcut for search (Ctrl+K)
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    document.getElementById('searchInput').focus();
  }
});
</script>

</body>
</html>