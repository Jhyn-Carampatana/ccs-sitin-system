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

// Create announcements table if it doesn't exist
$create_table_sql = "
CREATE TABLE IF NOT EXISTS announcements (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
)";
$conn->query($create_table_sql);

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// ==================== ANNOUNCEMENT MANAGEMENT LOGIC ====================

// Create new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $created_by = $admin_name;
    
    if (!empty($title) && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $title, $content, $created_by);
            if ($stmt->execute()) {
                $success_msg = "Announcement created successfully!";
                header("Location: announcement_management.php?success=1");
                exit();
            } else {
                $error_msg = "Error creating announcement: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    } else {
        $error_msg = "Please fill in both title and content.";
    }
}

// Edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    $announcement_id = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (!empty($title) && !empty($content)) {
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $title, $content, $announcement_id);
            if ($stmt->execute()) {
                $success_msg = "Announcement updated successfully!";
                header("Location: announcement_management.php?success=1");
                exit();
            } else {
                $error_msg = "Error updating announcement.";
            }
            $stmt->close();
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    } else {
        $error_msg = "Please fill in both title and content.";
    }
}

// Deactivate/Activate announcement
if (isset($_GET['toggle_status'])) {
    $announcement_id = (int)$_GET['toggle_status'];
    $new_status = $_GET['status'] ?? 'inactive';
    
    $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $announcement_id);
        if ($stmt->execute()) {
            $success_msg = "Announcement " . ($new_status == 'active' ? "activated" : "deactivated") . " successfully!";
        } else {
            $error_msg = "Error updating announcement status.";
        }
        $stmt->close();
    } else {
        $error_msg = "Database error: " . $conn->error;
    }
}

// Delete announcement
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success_msg = "Announcement deleted successfully!";
        } else {
            $error_msg = "Error deleting announcement.";
        }
        $stmt->close();
    } else {
        $error_msg = "Database error: " . $conn->error;
    }
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
    $search_term = $conn->real_escape_string($search);
    $where_conditions[] = "(title LIKE '%$search_term%' OR content LIKE '%$search_term%')";
}
if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $where_conditions[] = "status = '$status_filter'";
}
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get statistics
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$total_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
FROM announcements $where_clause";
$stats_result = $conn->query($total_query);
if ($stats_result && $stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
}

// Get total records for pagination
$total_rows = 0;
$total_query = "SELECT COUNT(*) as total FROM announcements $where_clause";
$total_result = $conn->query($total_query);
if ($total_result && $total_result->num_rows > 0) {
    $total_rows = (int)$total_result->fetch_assoc()['total'];
}
$total_pages = $total_rows > 0 ? ceil($total_rows / $entries_per_page) : 1;

// Get announcements for current page
$query = "SELECT * FROM announcements $where_clause ORDER BY created_at DESC LIMIT $offset, $entries_per_page";
$announcements = $conn->query($query);

// Get announcement for editing
$edit_announcement = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $edit_stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    if ($edit_stmt) {
        $edit_stmt->bind_param("i", $edit_id);
        $edit_stmt->execute();
        $result = $edit_stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $edit_announcement = $result->fetch_assoc();
        }
        $edit_stmt->close();
    }
}

// Get success/error messages from URL
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
<title>CCS Admin - Announcement Management</title>
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

  /* Create Announcement Card */
  .create-card {
    background: white;
    border-radius: 24px;
    border: 1px solid #EFF3F8;
    padding: 24px;
    margin-bottom: 32px;
  }
  .card-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .card-title i {
    color: #3B82F6;
  }
  .form-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
  }
  .form-group {
    flex: 1;
    min-width: 200px;
  }
  .form-group.full-width {
    flex: 100%;
  }
  .form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6C7A91;
    margin-bottom: 6px;
  }
  .form-group input, .form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    transition: all 0.2s;
  }
  .form-group input:focus, .form-group textarea:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
  }
  .form-group textarea {
    resize: vertical;
    min-height: 100px;
  }
  .btn-create {
    background: #3B82F6;
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    margin-top: 24px;
  }
  .btn-create:hover {
    background: #2563EB;
    transform: translateY(-1px);
  }

  /* Stats Cards */
  .stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
  .stat-icon.active { background: #DCFCE7; color: #10B981; }
  .stat-icon.inactive { background: #FEE2E2; color: #EF4444; }

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

  /* Announcements List - Card Style */
  .announcements-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .announcement-item {
    background: white;
    border-radius: 20px;
    border: 1px solid #EFF3F8;
    padding: 20px;
    transition: all 0.2s;
  }
  .announcement-item:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
  }
  .announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }
  .announcement-title {
    font-size: 18px;
    font-weight: 700;
    color: #0F172A;
  }
  .announcement-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 12px;
    color: #6C7A91;
  }
  .announcement-meta i {
    margin-right: 4px;
  }
  .announcement-content {
    font-size: 14px;
    color: #1E293B;
    line-height: 1.5;
    margin-bottom: 16px;
  }
  .announcement-actions {
    display: flex;
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid #F0F2F5;
  }
  .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 11px;
    font-weight: 600;
  }
  .status-active {
    background: #DCFCE7;
    color: #15803D;
  }
  .status-inactive {
    background: #FEE2E2;
    color: #DC2626;
  }
  .btn-edit {
    background: #F59E0B;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-deactivate {
    background: #EF4444;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-activate {
    background: #10B981;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-delete {
    background: #6C7A91;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-edit:hover, .btn-deactivate:hover, .btn-activate:hover, .btn-delete:hover {
    opacity: 0.8;
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
    background: white;
    border-radius: 24px 24px 0 0;
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

  /* Footer */
  .footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #F0F2F5;
    flex-wrap: wrap;
    gap: 12px;
    background: white;
    border-radius: 0 0 24px 24px;
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

  /* Modal */
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
    width: 500px;
    max-width: 90%;
    padding: 24px;
  }
  .modal-header {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
  }
  .modal input, .modal textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    margin-bottom: 12px;
    font-family: 'Inter', sans-serif;
  }
  .modal textarea {
    min-height: 100px;
    resize: vertical;
  }
  .modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
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
    <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-pie"></i> Reports
    </a>
    <a href="leaderboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-trophy"></i> Leaderboard
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

<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb">
      <h1>Manage Announcements</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i>
        <span>Announcements</span>
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

  <!-- Create New Announcement Card -->
  <div class="create-card">
    <div class="card-title">
      <i class="fas fa-plus-circle"></i> Create New Announcement
    </div>
    <form method="POST" action="">
      <div class="form-row">
        <div class="form-group full-width">
          <label><i class="fas fa-heading"></i> TITLE</label>
          <input type="text" name="title" placeholder="Enter announcement title" required>
        </div>
        <div class="form-group full-width">
          <label><i class="fas fa-align-left"></i> CONTENT</label>
          <textarea name="content" placeholder="Enter announcement content..." required></textarea>
        </div>
      </div>
      <button type="submit" name="create_announcement" class="btn-create">
        <i class="fas fa-paper-plane"></i> Publish Announcement
      </button>
    </form>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-info">
        <h4>Total</h4>
        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
      </div>
      <div class="stat-icon total">
        <i class="fas fa-bullhorn"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Active</h4>
        <div class="stat-number"><?php echo $stats['active'] ?? 0; ?></div>
      </div>
      <div class="stat-icon active">
        <i class="fas fa-check-circle"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <h4>Inactive</h4>
        <div class="stat-number"><?php echo $stats['inactive'] ?? 0; ?></div>
      </div>
      <div class="stat-icon inactive">
        <i class="fas fa-times-circle"></i>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <button class="filter-btn <?php echo empty($status_filter) ? 'active' : ''; ?>" onclick="filterByStatus('')">All Announcements</button>
    <button class="filter-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>" onclick="filterByStatus('active')">Active</button>
    <button class="filter-btn <?php echo $status_filter === 'inactive' ? 'active' : ''; ?>" onclick="filterByStatus('inactive')">Inactive</button>
  </div>

  <!-- Table Card with Announcements List -->
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
        <input type="text" id="searchInput" placeholder="Search by title or content..." value="<?php echo htmlspecialchars($search); ?>">
        <button onclick="searchAnnouncements()"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>

    <div class="announcements-list" style="padding: 24px;">
      <?php if ($announcements && $announcements->num_rows > 0): ?>
        <?php while ($row = $announcements->fetch_assoc()): ?>
          <div class="announcement-item">
            <div class="announcement-header">
              <div class="announcement-title"><?php echo htmlspecialchars($row['title']); ?></div>
              <span class="status-badge status-<?php echo $row['status']; ?>">
                <?php echo ucfirst($row['status']); ?>
              </span>
            </div>
            <div class="announcement-meta">
              <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['created_by']); ?></span>
              <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($row['created_at'])); ?></span>
              <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($row['created_at'])); ?></span>
            </div>
            <div class="announcement-content">
              <?php echo nl2br(htmlspecialchars($row['content'])); ?>
            </div>
            <div class="announcement-actions">
              <a href="?edit_id=<?php echo $row['id']; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&page=<?php echo $page; ?>" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
              </a>
              <?php if ($row['status'] === 'active'): ?>
                <a href="?toggle_status=<?php echo $row['id']; ?>&status=inactive&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&page=<?php echo $page; ?>" class="btn-deactivate" onclick="return confirm('Deactivate this announcement?')">
                  <i class="fas fa-eye-slash"></i> Deactivate
                </a>
              <?php else: ?>
                <a href="?toggle_status=<?php echo $row['id']; ?>&status=active&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&page=<?php echo $page; ?>" class="btn-activate" onclick="return confirm('Activate this announcement?')">
                  <i class="fas fa-eye"></i> Activate
                </a>
              <?php endif; ?>
              <a href="?delete_id=<?php echo $row['id']; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&page=<?php echo $page; ?>" class="btn-delete" onclick="return confirm('Delete this announcement permanently?')">
                <i class="fas fa-trash"></i> Delete
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div style="text-align: center; padding: 48px;">
          <i class="fas fa-bullhorn" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5; display: block;"></i>
          No announcements found<br>
          <span style="font-size: 12px;">Click "Create New Announcement" to add one.</span>
        </div>
      <?php endif; ?>
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

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Edit Announcement</div>
    <form method="POST" action="">
      <input type="hidden" name="announcement_id" id="edit_announcement_id">
      <input type="text" name="title" id="edit_title" placeholder="Title" required>
      <textarea name="content" id="edit_content" placeholder="Content" required></textarea>
      <div class="modal-buttons">
        <button type="submit" name="edit_announcement" class="btn-create" style="margin-top: 0;">Save Changes</button>
        <button type="button" class="btn-delete" onclick="closeModal()" style="background: #6C7A91;">Cancel</button>
      </div>
    </form>
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

  function searchAnnouncements() {
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

  function openEditModal(id, title, content) {
    document.getElementById('edit_announcement_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
    document.getElementById('editModal').style.display = 'flex';
  }

  function closeModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  // Close modal when clicking outside
  window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
      event.target.style.display = 'none';
    }
  }

  <?php if (isset($edit_announcement) && $edit_announcement): ?>
  openEditModal(<?php echo $edit_announcement['id']; ?>, '<?php echo addslashes($edit_announcement['title']); ?>', '<?php echo addslashes($edit_announcement['content']); ?>');
  <?php endif; ?>

  <?php if (isset($success_msg)): ?>
  showToast('<?php echo addslashes($success_msg); ?>', 'success');
  <?php endif; ?>

  <?php if (isset($error_msg)): ?>
  showToast('<?php echo addslashes($error_msg); ?>', 'error');
  <?php endif; ?>
</script>
</body>
</html>