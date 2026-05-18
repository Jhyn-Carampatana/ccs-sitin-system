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

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// ==================== FEEDBACK MANAGEMENT LOGIC ====================

// Handle status update (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $feedback_id);
    if ($stmt->execute()) {
        $success_msg = "Feedback " . ucfirst($new_status) . " successfully!";
        header("Location: feedback_reports.php?success=1");
        exit();
    } else {
        $error_msg = "Error updating feedback status.";
    }
    $stmt->close();
}

// Handle delete feedback
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_msg = "Feedback deleted successfully!";
        header("Location: feedback_reports.php?success=1");
        exit();
    } else {
        $error_msg = "Error deleting feedback.";
    }
    $stmt->close();
}

// Get statistics
$stats = [
    'total' => 0, 
    'avg_rating' => 0, 
    'rating_5' => 0, 
    'rating_4' => 0, 
    'rating_3' => 0, 
    'rating_2' => 0, 
    'rating_1' => 0
];

$total_query = "SELECT 
    COUNT(*) as total,
    IFNULL(AVG(rating), 0) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
FROM feedback";
$stats_result = $conn->query($total_query);
if ($stats_result && $stats_result->num_rows > 0) {
    $data = $stats_result->fetch_assoc();
    $stats['total'] = (int)$data['total'];
    $stats['avg_rating'] = round($data['avg_rating'], 1);
    $stats['rating_5'] = (int)$data['rating_5'];
    $stats['rating_4'] = (int)$data['rating_4'];
    $stats['rating_3'] = (int)$data['rating_3'];
    $stats['rating_2'] = (int)$data['rating_2'];
    $stats['rating_1'] = (int)$data['rating_1'];
}

// Get max rating for distribution bars
$max_rating = max($stats['rating_5'], $stats['rating_4'], $stats['rating_3'], $stats['rating_2'], $stats['rating_1']);
$max_width = $max_rating > 0 ? 100 : 1;

// Pagination variables
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $entries_per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search query
$where_clause = "";
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where_clause = "WHERE id_number LIKE '%$search_term%' OR student_name LIKE '%$search_term%' OR message LIKE '%$search_term%'";
}

// Get total records for pagination
$total_rows = 0;
$total_query = "SELECT COUNT(*) as total FROM feedback $where_clause";
$total_result = $conn->query($total_query);
if ($total_result && $total_result->num_rows > 0) {
    $total_rows = (int)$total_result->fetch_assoc()['total'];
}
$total_pages = $total_rows > 0 ? ceil($total_rows / $entries_per_page) : 1;

// Adjust page if out of range
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $entries_per_page;
}

// Get feedback for current page
$query = "SELECT * FROM feedback $where_clause ORDER BY created_at DESC LIMIT $offset, $entries_per_page";
$feedback = $conn->query($query);

// Get success message from URL
if (isset($_GET['success'])) {
    $success_msg = "Operation completed successfully!";
}

// Get today's date
$today = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>CCS Admin - Feedback Reports</title>
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

  /* Stats Cards Row */
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
  .stat-sub {
    font-size: 11px;
    color: #10B981;
    margin-top: 4px;
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
  .stat-icon.avg { background: #FEF3C7; color: #F59E0B; }
  .stat-icon.star5 { background: #DCFCE7; color: #10B981; }
  .stat-icon.star1 { background: #FEE2E2; color: #EF4444; }

  /* Rating Distribution Card */
  .distribution-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    padding: 24px;
    margin-bottom: 32px;
  }
  .distribution-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .distribution-title i {
    color: #3B82F6;
  }
  .rating-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
  }
  .rating-label {
    width: 70px;
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
  }
  .rating-bar-fill {
    flex: 1;
    height: 8px;
    background: #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
  }
  .rating-bar-fill-inner {
    height: 100%;
    border-radius: 10px;
    width: 0%;
    transition: width 0.5s ease;
  }
  .rating-bar-fill-inner.star-5 { background: #F59E0B; }
  .rating-bar-fill-inner.star-4 { background: #10B981; }
  .rating-bar-fill-inner.star-3 { background: #0284C7; }
  .rating-bar-fill-inner.star-2 { background: #EF4444; }
  .rating-bar-fill-inner.star-1 { background: #EF4444; }
  .rating-count {
    width: 40px;
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
    text-align: right;
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
    vertical-align: middle;
  }
  tr:hover td {
    background: #F8FAFE;
  }
  .rating-stars {
    color: #F59E0B;
    font-size: 14px;
    letter-spacing: 2px;
  }
  .feedback-message {
    max-width: 300px;
    word-wrap: break-word;
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
    <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Home</a>
    <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search</a>
    <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
    <a href="sit_in_management.php" class="nav-item"><i class="fas fa-chair"></i> Sit-in</a>
    <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="feedback_reports.php" class="nav-item active"><i class="fas fa-chart-pie"></i> Reports</a>
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
      <h1>Feedback Reports</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i>
        <span>Reports</span>
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
        <h4>Total Feedback</h4>
        <div class="stat-number"><?php echo $stats['total']; ?></div>
        <div class="stat-sub">All submissions</div>
      </div>
      <div class="stat-icon total">
        <i class="fas fa-comment"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Average Rating</h4>
        <div class="stat-number"><?php echo $stats['avg_rating']; ?></div>
        <div class="stat-sub">
          <?php 
            $full_stars = floor($stats['avg_rating']);
            for($i = 1; $i <= 5; $i++) {
                if($i <= $full_stars) {
                    echo '<i class="fas fa-star" style="color: #F59E0B; font-size: 11px;"></i>';
                } else {
                    echo '<i class="far fa-star" style="color: #CBD5E1; font-size: 11px;"></i>';
                }
            }
          ?>
        </div>
      </div>
      <div class="stat-icon avg">
        <i class="fas fa-star"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>5 Star Ratings</h4>
        <div class="stat-number"><?php echo $stats['rating_5']; ?></div>
        <div class="stat-sub">Excellent</div>
      </div>
      <div class="stat-icon star5">
        <i class="fas fa-star"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>1 Star Ratings</h4>
        <div class="stat-number"><?php echo $stats['rating_1']; ?></div>
        <div class="stat-sub">Needs Improvement</div>
      </div>
      <div class="stat-icon star1">
        <i class="fas fa-star"></i>
      </div>
    </div>
  </div>

  <!-- Rating Distribution Card -->
  <div class="distribution-card">
    <div class="distribution-title">
      <i class="fas fa-chart-bar"></i> Rating Distribution
    </div>
    <?php
    $percent_5 = $max_rating > 0 ? ($stats['rating_5'] / $max_rating) * 100 : 0;
    $percent_4 = $max_rating > 0 ? ($stats['rating_4'] / $max_rating) * 100 : 0;
    $percent_3 = $max_rating > 0 ? ($stats['rating_3'] / $max_rating) * 100 : 0;
    $percent_2 = $max_rating > 0 ? ($stats['rating_2'] / $max_rating) * 100 : 0;
    $percent_1 = $max_rating > 0 ? ($stats['rating_1'] / $max_rating) * 100 : 0;
    ?>
    <div class="rating-bar">
      <div class="rating-label"><i class="fas fa-star" style="color: #F59E0B;"></i> 5 Stars</div>
      <div class="rating-bar-fill">
        <div class="rating-bar-fill-inner star-5" style="width: <?php echo $percent_5; ?>%"></div>
      </div>
      <div class="rating-count"><?php echo $stats['rating_5']; ?></div>
    </div>
    <div class="rating-bar">
      <div class="rating-label"><i class="fas fa-star" style="color: #10B981;"></i> 4 Stars</div>
      <div class="rating-bar-fill">
        <div class="rating-bar-fill-inner star-4" style="width: <?php echo $percent_4; ?>%"></div>
      </div>
      <div class="rating-count"><?php echo $stats['rating_4']; ?></div>
    </div>
    <div class="rating-bar">
      <div class="rating-label"><i class="fas fa-star" style="color: #0284C7;"></i> 3 Stars</div>
      <div class="rating-bar-fill">
        <div class="rating-bar-fill-inner star-3" style="width: <?php echo $percent_3; ?>%"></div>
      </div>
      <div class="rating-count"><?php echo $stats['rating_3']; ?></div>
    </div>
    <div class="rating-bar">
      <div class="rating-label"><i class="fas fa-star" style="color: #EF4444;"></i> 2 Stars</div>
      <div class="rating-bar-fill">
        <div class="rating-bar-fill-inner star-2" style="width: <?php echo $percent_2; ?>%"></div>
      </div>
      <div class="rating-count"><?php echo $stats['rating_2']; ?></div>
    </div>
    <div class="rating-bar">
      <div class="rating-label"><i class="fas fa-star" style="color: #EF4444;"></i> 1 Star</div>
      <div class="rating-bar-fill">
        <div class="rating-bar-fill-inner star-1" style="width: <?php echo $percent_1; ?>%"></div>
      </div>
      <div class="rating-count"><?php echo $stats['rating_1']; ?></div>
    </div>
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
        <input type="text" id="searchInput" placeholder="Search by student or message..." value="<?php echo htmlspecialchars($search); ?>">
        <button onclick="searchFeedback()"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>ID NUMBER</th>
            <th>STUDENT NAME</th>
            <th>RATING</th>
            <th>MESSAGE</th>
            <th>DATE</th>
            <th>STATUS</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($feedback && $feedback->num_rows > 0): ?>
            <?php $counter = $offset + 1; ?>
            <?php while ($row = $feedback->fetch_assoc()): ?>
              <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td>
                  <div class="rating-stars">
                    <?php 
                      $rating = $row['rating'];
                      for($i = 1; $i <= 5; $i++) {
                          if($i <= $rating) {
                              echo '<i class="fas fa-star"></i>';
                          } else {
                              echo '<i class="far fa-star"></i>';
                          }
                      }
                    ?>
                  </div>
                </td>
                <td class="feedback-message"><?php echo htmlspecialchars($row['message']); ?></td>
                <td><?php echo date('M j, Y', strtotime($row['feedback_date'])); ?></td>
                <td>
                  <span class="status-badge status-<?php echo $row['status']; ?>">
                    <?php echo ucfirst($row['status']); ?>
                  </span>
                </td>
                <td class="action-buttons">
                  <?php if ($row['status'] === 'pending'): ?>
                    <form method="POST" style="display: inline-block;">
                      <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="new_status" value="approved">
                      <button type="submit" name="update_status" class="btn-approve">
                        <i class="fas fa-check"></i> Approve
                      </button>
                    </form>
                    <form method="POST" style="display: inline-block;">
                      <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="new_status" value="rejected">
                      <button type="submit" name="update_status" class="btn-reject">
                        <i class="fas fa-times"></i> Reject
                      </button>
                    </form>
                  <?php endif; ?>
                  <a href="?delete_id=<?php echo $row['id']; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn-delete" onclick="return confirm('Delete this feedback?')">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="8" style="text-align: center; padding: 48px;">
                <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5; display: block;"></i>
                No feedback found<br>
                <span style="font-size: 12px;">No feedback submissions available.</span>
              </td>
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
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}`;
  }

  function searchFeedback() {
    const search = document.getElementById('searchInput').value;
    const entries = document.getElementById('entriesSelect').value;
    window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}`;
  }

  function goToPage(page) {
    const entries = document.getElementById('entriesSelect').value;
    const search = document.getElementById('searchInput').value;
    window.location.href = `?page=${page}&entries=${entries}&search=${encodeURIComponent(search)}`;
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