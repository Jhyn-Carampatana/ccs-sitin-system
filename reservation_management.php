<?php
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

// Create reservations table if it doesn't exist
$create_reservations_table = "
CREATE TABLE IF NOT EXISTS reservations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    id_number VARCHAR(20) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    course VARCHAR(20) NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    purpose VARCHAR(50) NOT NULL,
    laboratory VARCHAR(20) NOT NULL,
    reservation_date DATE NOT NULL,
    time_in TIME NOT NULL,
    sessions_used INT(11) DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_reservation_date (reservation_date)
)";
$conn->query($create_reservations_table);

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'CCS Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// ==================== RESERVATION MANAGEMENT LOGIC ====================

// Handle status update (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $new_status = $_POST['new_status'];
    
    // Get student info before updating
    $get_stmt = $conn->prepare("SELECT student_id, sessions_used FROM reservations WHERE id = ?");
    $get_stmt->bind_param("i", $reservation_id);
    $get_stmt->execute();
    $reservation = $get_stmt->get_result()->fetch_assoc();
    
    if ($reservation && $new_status === 'approved') {
        // Check if student has enough sessions
        $student_stmt = $conn->prepare("SELECT sessions FROM students WHERE id = ?");
        $student_stmt->bind_param("i", $reservation['student_id']);
        $student_stmt->execute();
        $student = $student_stmt->get_result()->fetch_assoc();
        
        if ($student && $student['sessions'] >= $reservation['sessions_used']) {
            // Deduct sessions
            $new_sessions = $student['sessions'] - $reservation['sessions_used'];
            $update_sessions = $conn->prepare("UPDATE students SET sessions = ? WHERE id = ?");
            $update_sessions->bind_param("ii", $new_sessions, $reservation['student_id']);
            $update_sessions->execute();
            $update_sessions->close();
        } else {
            $error_msg = "Student doesn't have enough remaining sessions!";
            $new_status = 'rejected';
        }
        $student_stmt->close();
    }
    
    $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $reservation_id);
    if ($stmt->execute()) {
        $success_msg = "Reservation " . ucfirst($new_status) . " successfully!";
    } else {
        $error_msg = "Error updating reservation status.";
    }
    $stmt->close();
    $get_stmt->close();
}

// Handle delete reservation
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_msg = "Reservation deleted successfully!";
    } else {
        $error_msg = "Error deleting reservation.";
    }
    $stmt->close();
}

// Pagination variables
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $entries_per_page;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build search and filter query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(id_number LIKE '%$search%' OR student_name LIKE '%$search%' OR purpose LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get statistics
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$total_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
FROM reservations $where_clause";
$stats_result = $conn->query($total_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}

// Get total records for pagination
$total_rows = 0;
$total_query = "SELECT COUNT(*) as total FROM reservations $where_clause";
$total_result = $conn->query($total_query);
if ($total_result) {
    $total_rows = $total_result->fetch_assoc()['total'];
}
$total_pages = $total_rows > 0 ? ceil($total_rows / $entries_per_page) : 1;

// Get reservations for current page
$query = "SELECT r.*, 
          CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as full_name,
          s.course, s.year_level, s.sessions
          FROM reservations r
          LEFT JOIN students s ON r.id_number = s.id_number
          $where_clause
          ORDER BY r.created_at DESC 
          LIMIT $offset, $entries_per_page";
$reservations = $conn->query($query);

// Get today's date
$today = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>CCS Admin - Reservation Management</title>
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
    box-shadow: 0 0 0 1px rgba(0,0,0,0.02);
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
    box-shadow: 0 6px 12px -6px rgba(59,130,246,0.25);
    display: none;
  }
  
  .logo-text {
    font-weight: 800;
    font-size: 20px;
    letter-spacing: -0.3px;
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
    transition: all 0.2s;
    cursor: pointer;
    text-decoration: none;
  }
  .nav-item i {
    width: 22px;
    font-size: 1.2rem;
    color: #7E8BA0;
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
    font-size: 16px;
  }
  .user-details h4 {
    font-size: 14px;
    font-weight: 700;
    color: #0F172A;
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
  .logout-icon:hover {
    opacity: 0.8;
  }

  /* ========= MAIN CONTENT ========= */
  .main-content {
    margin-left: 260px;
    flex: 1;
    padding: 28px 36px;
  }

  /* Top header */
  .top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
  }
  .page-breadcrumb h1 {
    font-size: 26px;
    font-weight: 700;
    color: #0F172A;
    letter-spacing: -0.4px;
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
  .header-actions {
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .date-badge {
    background: white;
    border-radius: 40px;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #E9EEF3;
    font-size: 13px;
    color: #1E293B;
  }
  .date-badge i {
    color: #3B82F6;
  }
  .admin-chip {
    background: white;
    border-radius: 40px;
    padding: 6px 18px 6px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #E9EEF3;
    font-weight: 500;
    font-size: 13px;
    color: #1E293B;
  }
  .admin-chip i {
    color: #3B82F6;
    font-size: 16px;
  }

  /* Stats Cards */
  .stats-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
  }
  .stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    border: 1px solid #EFF3F8;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
  }
  .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
  }
  .stat-info h4 {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6C7A91;
    margin-bottom: 8px;
  }
  .stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #0F172A;
  }
  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
  }
  .stat-icon.total { background: #EFF6FF; color: #3B82F6; }
  .stat-icon.pending { background: #FEF3C7; color: #F59E0B; }
  .stat-icon.approved { background: #DCFCE7; color: #10B981; }
  .stat-icon.rejected { background: #FEE2E2; color: #EF4444; }

  /* Filter Bar */
  .filter-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }
  .filter-btn {
    padding: 8px 20px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: 1.5px solid #E2E8F0;
    background: white;
    color: #5B6E8C;
    transition: all 0.2s;
  }
  .filter-btn:hover {
    border-color: #3B82F6;
    color: #3B82F6;
  }
  .filter-btn.active {
    background: #3B82F6;
    border-color: #3B82F6;
    color: white;
  }

  /* Table Card */
  .table-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    overflow: hidden;
  }

  /* Toolbar */
  .toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #F0F2F5;
    flex-wrap: wrap;
    gap: 12px;
  }
  .entries-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #6C7A91;
  }
  .entries-select {
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    padding: 8px 32px 8px 14px;
    font-size: 13px;
    font-family: 'Inter', sans-serif;
    background: white;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236C7A91' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
  }
  .search-box {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .search-box input {
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 13px;
    width: 240px;
    outline: none;
  }
  .search-box input:focus {
    border-color: #3B82F6;
  }
  .search-box button {
    background: #3B82F6;
    border: none;
    padding: 8px 16px;
    border-radius: 10px;
    color: white;
    cursor: pointer;
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
    padding: 14px 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6C7A91;
    background: #FCFDFF;
    border-bottom: 1px solid #EDF2F7;
  }
  td {
    padding: 14px 16px;
    font-size: 13px;
    color: #1E293B;
    border-bottom: 1px solid #F1F5F9;
  }
  tr:hover td {
    background: #F8FAFE;
  }
  .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 11px;
    font-weight: 600;
  }
  .status-pending {
    background: #FEF3C7;
    color: #D97706;
  }
  .status-approved {
    background: #DCFCE7;
    color: #15803D;
  }
  .status-rejected {
    background: #FEE2E2;
    color: #DC2626;
  }
  .course-year {
    font-size: 12px;
    color: #6C7A91;
  }
  .action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .btn-approve {
    background: #10B981;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
  }
  .btn-reject {
    background: #EF4444;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
  }
  .btn-delete {
    background: #6C7A91;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .btn-approve:hover, .btn-reject:hover, .btn-delete:hover {
    opacity: 0.8;
  }

  /* Footer */
  .footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #F0F2F5;
    flex-wrap: wrap;
    gap: 12px;
  }
  .showing-info {
    font-size: 12px;
    color: #6C7A91;
  }
  .pagination {
    display: flex;
    gap: 6px;
  }
  .page-btn {
    width: 36px;
    height: 36px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    background: white;
    color: #3B82F6;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .page-btn:hover:not(:disabled) {
    background: #3B82F6;
    color: white;
    border-color: #3B82F6;
  }
  .page-btn.active {
    background: #3B82F6;
    color: white;
    border-color: #3B82F6;
  }
  .page-btn:disabled {
    opacity: 0.4;
    cursor: default;
  }

  /* Toast */
  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1E293B;
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 13px;
    transform: translateY(60px);
    opacity: 0;
    transition: all 0.3s;
    z-index: 9999;
  }
  .toast.show {
    transform: translateY(0);
    opacity: 1;
  }
  .toast.success { background: #10B981; }
  .toast.error { background: #EF4444; }

  @media (max-width: 1000px) {
    .main-content { margin-left: 0; padding: 20px; }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .stats-container { grid-template-columns: repeat(2, 1fr); }
    .toolbar { flex-direction: column; align-items: stretch; }
    .search-box input { width: 100%; }
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
    <a href="admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-line"></i> Dashboard
    </a>
    <a href="Search_Student.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'Search_Student.php' ? 'active' : ''; ?>">
      <i class="fas fa-search"></i> Search Student
    </a>
    <a href="Student_Information.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'Student_Information.php' ? 'active' : ''; ?>">
      <i class="fas fa-users"></i> Students
    </a>
    <a href="sit_in_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sit_in_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-chair"></i> Sit-in
    </a>
    <a href="reservation_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-calendar-alt"></i> Reservation
    </a>
    <a href="announcement_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'announcement_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-pie"></i> Reports
    </a>
    <a href="leaderboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-trophy"></i> Leaderboard
    </a>
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
      <h1>Reservation Management</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i>
        <span>Reservation</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="date-badge">
        <i class="fas fa-calendar"></i>
        <?php echo $today; ?>
      </div>
      <div class="admin-chip"><i class="fas fa-user-shield"></i> Admin · <strong>CCS</strong></div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-info">
        <h4>Total</h4>
        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
      </div>
      <div class="stat-icon total">
        <i class="fas fa-calendar-alt"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Pending</h4>
        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
      </div>
      <div class="stat-icon pending">
        <i class="fas fa-clock"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Approved</h4>
        <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
      </div>
      <div class="stat-icon approved">
        <i class="fas fa-check-circle"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Rejected</h4>
        <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
      </div>
      <div class="stat-icon rejected">
        <i class="fas fa-times-circle"></i>
      </div>
    </div>
  </div>

  <!-- Filter Buttons -->
  <div class="filter-bar">
    <button class="filter-btn <?php echo empty($status_filter) ? 'active' : ''; ?>" onclick="filterByStatus('')">All Reservations</button>
    <button class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">Pending</button>
    <button class="filter-btn <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" onclick="filterByStatus('approved')">Approved</button>
    <button class="filter-btn <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" onclick="filterByStatus('rejected')">Rejected</button>
  </div>

  <!-- Table Card -->
  <div class="table-card">
    <div class="toolbar">
      <div class="entries-label">
        Show
        <select class="entries-select" id="entriesSelect" onchange="changeEntries()">
          <option value="5" <?php echo $entries_per_page == 5 ? 'selected' : ''; ?>>5</option>
          <option value="10" <?php echo $entries_per_page == 10 ? 'selected' : ''; ?>>10</option>
          <option value="25" <?php echo $entries_per_page == 25 ? 'selected' : ''; ?>>25</option>
          <option value="50" <?php echo $entries_per_page == 50 ? 'selected' : ''; ?>>50</option>
        </select>
        entries
      </div>
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by ID, Name, or Purpose..." value="<?php echo htmlspecialchars($search); ?>">
        <button onclick="searchReservations()"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>ID NUMBER</th>
            <th>STUDENT NAME</th>
            <th>COURSE / YEAR</th>
            <th>PURPOSE</th>
            <th>LABORATORY</th>
            <th>DATE</th>
            <th>TIME IN</th>
            <th>SESSIONS LEFT</th>
            <th>STATUS</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($reservations && $reservations->num_rows > 0): ?>
            <?php $counter = $offset + 1; ?>
            <?php while ($row = $reservations->fetch_assoc()): ?>
              <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name'] ?? $row['student_name']); ?></td>
                <td>
                  <span class="course-year">
                    <?php echo htmlspecialchars($row['course'] ?? 'N/A'); ?> · <?php echo htmlspecialchars($row['year_level'] ?? 'N/A'); ?>
                  </span>
                 </d>
                <td><?php echo htmlspecialchars($row['purpose']); ?></d>
                <td><?php echo htmlspecialchars($row['laboratory']); ?></d>
                <td><?php echo date('M j, Y', strtotime($row['reservation_date'])); ?></d>
                <td><?php echo date('g:i A', strtotime($row['time_in'])); ?></d>
                <td><?php echo $row['sessions'] ?? 'N/A'; ?></d>
                <td>
                  <span class="status-badge status-<?php echo $row['status']; ?>">
                    <?php echo ucfirst($row['status']); ?>
                  </span>
                 </d>
                <td class="action-buttons">
                  <?php if ($row['status'] === 'pending'): ?>
                    <form method="POST" style="display: inline-block;">
                      <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="new_status" value="approved">
                      <button type="submit" name="update_status" class="btn-approve">
                        <i class="fas fa-check"></i> Approve
                      </button>
                    </form>
                    <form method="POST" style="display: inline-block;">
                      <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="new_status" value="rejected">
                      <button type="submit" name="update_status" class="btn-reject">
                        <i class="fas fa-times"></i> Reject
                      </button>
                    </form>
                  <?php endif; ?>
                  <a href="?delete_id=<?php echo $row['id']; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&page=<?php echo $page; ?>" class="btn-delete" onclick="return confirm('Delete this reservation?')">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                 </d>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="11" style="text-align: center; padding: 48px;">
                <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5; display: block;"></i>
                No reservations found<br>
                <span style="font-size: 12px;">Try adjusting your search or filter criteria.</span>
               </d>
             </tr>
          <?php endif; ?>
        </tbody>
       </table>
    </div>

    <div class="footer-bar">
      <div class="showing-info">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $entries_per_page, $total_rows); ?> of <?php echo $total_rows; ?> entries
      </div>
      <div class="pagination">
        <button class="page-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>‹</button>
        <?php for ($i = 1; $i <= $total_pages && $i <= 10; $i++): ?>
          <button class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
        <?php endfor; ?>
        <?php if ($total_pages > 10): ?>
          <span style="padding: 0 8px;">...</span>
          <button class="page-btn" onclick="goToPage(<?php echo $total_pages; ?>)" style="width: auto; padding: 0 12px;"><?php echo $total_pages; ?></button>
        <?php endif; ?>
        <button class="page-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>›</button>
      </div>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
  function changeEntries() {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    const status = '<?php echo $status_filter; ?>';
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}&status=${status}`;
  }

  function searchReservations() {
    const search = document.getElementById('searchInput').value;
    const entries = document.getElementById('entriesSelect').value;
    const status = '<?php echo $status_filter; ?>';
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}&status=${status}`;
  }

  function filterByStatus(status) {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}&status=${status}`;
  }

  function goToPage(page) {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    const status = '<?php echo $status_filter; ?>';
    window.location.href = `?page=${page}&entries=${entries}&search=${encodeURIComponent(search)}&status=${status}`;
  }

  function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  <?php if (isset($success_msg)): ?>
  showToast('<?php echo addslashes($success_msg); ?>', 'success');
  <?php endif; ?>
  <?php if (isset($error_msg)): ?>
  showToast('<?php echo addslashes($error_msg); ?>', 'error');
  <?php endif; ?>
</script>
</body>
</html>