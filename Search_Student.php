<?php
// ============================================
// SEARCH_STUDENT.PHP - Search and manage students
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
$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get statistics
$total_students_query = "SELECT COUNT(*) as total FROM students";
$total_students_result = $conn->query($total_students_query);
$total_students = $total_students_result->fetch_assoc()['total'];

$total_sitin_query = "SELECT COUNT(*) as total FROM sit_in_sessions";
$total_sitin_result = $conn->query($total_sitin_query);
$total_sitin = $total_sitin_result ? $total_sitin_result->fetch_assoc()['total'] : 0;

$current_sitin_query = "SELECT COUNT(*) as total FROM sit_in_sessions WHERE status = 'active'";
$current_sitin_result = $conn->query($current_sitin_query);
$current_sitin = $current_sitin_result ? $current_sitin_result->fetch_assoc()['total'] : 0;

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';

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

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get students
$query = "SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM students $where_clause ORDER BY first_name ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();
$student_count = $students->num_rows;
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
  .notif-btn {
    background: white;
    border-radius: 40px;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    border: 1px solid #E9EEF3;
    cursor: pointer;
    color: #4B5565;
    transition: all 0.2s;
    position: relative;
  }
  .notif-btn:hover {
    background: #F8FAFE;
    border-color: #CBD5E1;
  }
  .notif-dot {
    position: absolute;
    top: 10px;
    right: 12px;
    width: 8px;
    height: 8px;
    background: #EF4444;
    border-radius: 50%;
    border: 1px solid white;
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

  /* ========= STATS ROW ========= */
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
  }
  .stat-left {
    flex: 1;
  }
  .stat-title {
    font-size: 13px;
    font-weight: 600;
    color: #5B6E8C;
    text-transform: uppercase;
    margin-bottom: 12px;
  }
  .stat-number {
    font-size: 34px;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.2;
  }
  .stat-trend {
    font-size: 12px;
    color: #10B981;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
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

  /* ========= SEARCH SECTION ========= */
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
    color: #0F172A;
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
    padding: 12px 16px;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    outline: none;
  }
  .search-input-group input:focus {
    border-color: #3B82F6;
  }
  .search-input-group button {
    background: #3B82F6;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
  }

  /* Filter chips */
  .filter-section {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .filter-chip {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    background: #F1F5F9;
    color: #475569;
    transition: all 0.2s;
    text-decoration: none;
  }
  .filter-chip:hover {
    background: #E2E8F0;
  }
  .filter-chip.active {
    background: #3B82F6;
    color: white;
  }

  /* Results */
  .results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }
  .results-count {
    font-size: 14px;
    font-weight: 600;
    color: #3B82F6;
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
    padding: 14px 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6C7A91;
    background: #FCFDFF;
    border-bottom: 1px solid #EDF2F7;
  }
  td {
    padding: 14px 20px;
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
    gap: 12px;
  }
  .student-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #3B82F6;
  }
  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 11px;
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
    gap: 12px;
  }
  .action-icons i {
    cursor: pointer;
    font-size: 16px;
  }
  .fa-edit { color: #3B82F6; }
  .fa-trash-alt { color: #EF4444; }
  .fa-eye { color: #10B981; }

  @media (max-width: 1000px) {
    .main-content { margin-left: 0; padding: 20px; }
    .stats-row { flex-direction: column; }
    .search-input-group { flex-direction: column; }
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
      <h1>Student Directory</h1>
      <div class="breadcrumb-links">
        <span>Home</span> <i class="fas fa-chevron-right"></i>
        <span>Search Student</span>
      </div>
    </div>
    <div class="header-actions">
      <div class="notif-btn">
        <i class="far fa-bell"></i>
        <div class="notif-dot"></div>
      </div>
      <div class="admin-chip"><i class="fas fa-user-shield"></i> Admin · <strong>CCS</strong></div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Total Sit-in</div>
        <div class="stat-number"><?php echo $total_sitin; ?></div>
        <div class="stat-trend"><i class="fas fa-arrow-up"></i> +12% since last month</div>
      </div>
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Currently Sit-in</div>
        <div class="stat-number"><?php echo $current_sitin; ?></div>
        <div class="stat-trend">Active sessions right now</div>
      </div>
      <div class="stat-icon"><i class="fas fa-chair"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-left">
        <div class="stat-title">Total Students</div>
        <div class="stat-number"><?php echo $total_students; ?></div>
        <div class="stat-trend">+<?php echo $total_students; ?> this semester</div>
      </div>
      <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
  </div>

  <!-- Search Section -->
  <div class="search-card">
    <div class="search-title">Find a Student</div>
    <div class="search-subtitle">Search by name, ID number, email, or course — manage sit-in records</div>
    
    <form method="GET" action="Search_Student.php">
      <div class="search-input-group">
        <input type="text" name="search" placeholder="Juan, 2024-0803, BSCS..." value="<?php echo htmlspecialchars($search_query); ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </div>
    </form>

    <div class="filter-section">
      <a href="?search=<?php echo urlencode($search_query); ?>" class="filter-chip <?php echo empty($filter_status) && empty($filter_course) ? 'active' : ''; ?>">All Students</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&status=active" class="filter-chip <?php echo $filter_status == 'active' ? 'active' : ''; ?>">Active</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&status=sitting" class="filter-chip <?php echo $filter_status == 'sitting' ? 'active' : ''; ?>">Sitting-in</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&status=offline" class="filter-chip <?php echo $filter_status == 'offline' ? 'active' : ''; ?>">Offline</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&course=BSIT" class="filter-chip <?php echo $filter_course == 'BSIT' ? 'active' : ''; ?>">BSIT</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&course=BSCS" class="filter-chip <?php echo $filter_course == 'BSCS' ? 'active' : ''; ?>">BSCS</a>
      <a href="?search=<?php echo urlencode($search_query); ?>&course=BSIS" class="filter-chip <?php echo $filter_course == 'BSIS' ? 'active' : ''; ?>">BSIS</a>
    </div>
  </div>

  <!-- Results -->
  <div class="results-header">
    <div class="results-count"><i class="fas fa-users"></i> <?php echo $student_count; ?> students found</div>
  </div>

  <div class="table-card">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>ID Number</th>
            <th>Course & Year</th>
            <th>Sessions</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($student_count > 0): ?>
            <?php while ($row = $students->fetch_assoc()): ?>
              <?php
              $full_name = $row['first_name'] . ' ' . $row['last_name'];
              $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
              $status = 'active';
              $status_class = 'status-active';
              $status_text = 'Active';
              ?>
              <tr>
                <td>
                  <div class="student-info">
                    <div class="student-avatar"><?php echo $initials; ?></div>
                    <div><?php echo htmlspecialchars($full_name); ?></div>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?> <?php echo htmlspecialchars($row['year_level']); ?></td>
                <td><?php echo $row['sessions']; ?> sessions</td>
                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                <td class="action-icons">
                  <a href="Student_Information.php?edit=<?php echo $row['id']; ?>"><i class="fas fa-edit" title="Edit"></i></a>
                  <a href="Student_Information.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')"><i class="fas fa-trash-alt" title="Delete"></i></a>
                  <a href="Student_Information.php?view=<?php echo $row['id']; ?>"><i class="fas fa-eye" title="View"></i></a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="6" style="text-align: center; padding: 48px;">
                <i class="fas fa-user-graduate" style="font-size: 48px; color: #CBD5E1; margin-bottom: 16px; display: block;"></i>
                No students found
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>