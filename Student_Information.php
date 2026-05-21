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

// ========== HANDLE ALL POST ACTIONS ==========

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $user_name = trim($_POST['user_name']);
    $year_level = trim($_POST['year_level']);
    $course = trim($_POST['course']);
    $email = trim($_POST['email']);
    $password = password_hash($id_number, PASSWORD_DEFAULT);
    $sessions = trim($_POST['sessions']);
    
    // Check if ID already exists
    $check = $conn->prepare("SELECT id FROM students WHERE id_number = ?");
    $check->bind_param("s", $id_number);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error_msg = "Student ID already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO students (id_number, user_name, first_name, middle_name, last_name, year_level, course, email, password, sessions, total_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssssssssi", $id_number, $user_name, $first_name, $middle_name, $last_name, $year_level, $course, $email, $password, $sessions);
        if ($stmt->execute()) {
            $success_msg = "Student added successfully!";
        } else {
            $error_msg = "Error adding student: " . $conn->error;
        }
        $stmt->close();
    }
    $check->close();
}

// Handle Bulk Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import'])) {
    $import_data = json_decode($_POST['import_data'], true);
    $imported_count = 0;
    $error_count = 0;
    
    foreach ($import_data as $student) {
        $id_number = trim($student['id_number']);
        $first_name = trim($student['first_name']);
        $middle_name = trim($student['middle_name'] ?? '');
        $last_name = trim($student['last_name']);
        $user_name = trim($student['user_name'] ?? $id_number);
        $year_level = trim($student['year_level']);
        $course = trim($student['course']);
        $email = trim($student['email']);
        $sessions = intval($student['sessions'] ?? 30);
        $password = password_hash($id_number, PASSWORD_DEFAULT);
        
        $check = $conn->prepare("SELECT id FROM students WHERE id_number = ?");
        $check->bind_param("s", $id_number);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO students (id_number, user_name, first_name, middle_name, last_name, year_level, course, email, password, sessions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssi", $id_number, $user_name, $first_name, $middle_name, $last_name, $year_level, $course, $email, $password, $sessions);
            if ($stmt->execute()) {
                $imported_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        } else {
            $error_count++;
        }
        $check->close();
    }
    
    $success_msg = "Bulk import complete: $imported_count students added, $error_count errors/skipped";
}

// Handle Delete Student
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Delete related records first
    $conn->query("DELETE FROM sit_in_sessions WHERE id_number = (SELECT id_number FROM students WHERE id = $delete_id)");
    $conn->query("DELETE FROM reservations WHERE id_number = (SELECT id_number FROM students WHERE id = $delete_id)");
    $conn->query("DELETE FROM point_history WHERE student_id = (SELECT id_number FROM students WHERE id = $delete_id)");
    
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_msg = "Student deleted successfully!";
    }
    $stmt->close();
}

// Handle Reset All Sessions
if (isset($_POST['reset_sessions'])) {
    $stmt = $conn->prepare("UPDATE students SET sessions = 30");
    if ($stmt->execute()) {
        $success_msg = "All student sessions have been reset to 30!";
    }
    $stmt->close();
}

// Handle Add Sessions to Multiple Students
if (isset($_POST['add_sessions_bulk']) && isset($_POST['selected_students']) && isset($_POST['sessions_to_add'])) {
    $selected = json_decode($_POST['selected_students'], true);
    $sessions_to_add = intval($_POST['sessions_to_add']);
    $ids = implode(',', array_map('intval', $selected));
    $update = $conn->query("UPDATE students SET sessions = sessions + $sessions_to_add WHERE id IN ($ids)");
    if ($update) {
        $success_msg = "Added $sessions_to_add sessions to " . count($selected) . " students!";
    }
}

// Handle Edit Student via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $student_id = intval($_POST['student_id']);
    $id_number = trim($_POST['id_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $year_level = trim($_POST['year_level']);
    $course = trim($_POST['course']);
    $email = trim($_POST['email']);
    $sessions = intval($_POST['sessions']);
    $points = intval($_POST['points'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE students SET id_number=?, first_name=?, middle_name=?, last_name=?, year_level=?, course=?, email=?, sessions=?, total_points=? WHERE id=?");
    $stmt->bind_param("ssssssssii", $id_number, $first_name, $middle_name, $last_name, $year_level, $course, $email, $sessions, $points, $student_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit();
}

// ========== PAGINATION & FILTERS ==========
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $entries_per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_course = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';

// Build search conditions
$conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR course LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $search_param;
        $types .= "s";
    }
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

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total records
$total_query = "SELECT COUNT(*) as total FROM students $where_clause";
$stmt = $conn->prepare($total_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $entries_per_page);
$stmt->close();

// Get students for current page
$query = "SELECT id, id_number, first_name, middle_name, last_name, year_level, course, email, sessions, total_points FROM students $where_clause ORDER BY last_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types .= "ii";
    $params[] = $entries_per_page;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $entries_per_page, $offset);
}
$stmt->execute();
$students = $stmt->get_result();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'bsit' => $conn->query("SELECT COUNT(*) as count FROM students WHERE course = 'BSIT'")->fetch_assoc()['count'],
    'bscs' => $conn->query("SELECT COUNT(*) as count FROM students WHERE course = 'BSCS'")->fetch_assoc()['count'],
    'bsis' => $conn->query("SELECT COUNT(*) as count FROM students WHERE course = 'BSIS'")->fetch_assoc()['count'],
    'total_points' => $conn->query("SELECT SUM(total_points) as sum FROM students")->fetch_assoc()['sum'] ?? 0,
    'avg_sessions' => $conn->query("SELECT AVG(sessions) as avg FROM students")->fetch_assoc()['avg'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>CCS Admin - Student Information</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
  .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
  .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }

  /* Main Content */
  .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
  
  /* Header */
  .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
  .header-section h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
  
  /* Stats Cards */
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
  .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; }
  .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; }
  .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
  .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }

  /* Buttons */
  .btn { padding: 10px 20px; border-radius: 40px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
  .btn-primary { background: #3B82F6; color: white; }
  .btn-primary:hover { background: #2563EB; transform: translateY(-1px); }
  .btn-warning { background: #F59E0B; color: white; }
  .btn-warning:hover { background: #D97706; }
  .btn-success { background: #10B981; color: white; }
  .btn-success:hover { background: #059669; }
  .btn-danger { background: #EF4444; color: white; }
  .btn-outline { background: white; border: 1.5px solid #E2E8F0; color: #475569; }
  .btn-outline:hover { background: #F1F5F9; }

  /* Table Card */
  .table-card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; overflow: hidden; }
  
  /* Toolbar */
  .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: white; border-bottom: 1px solid #EDF2F7; flex-wrap: wrap; gap: 12px; }
  .entries-label { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #5B6E8C; }
  .entries-select { padding: 8px 32px 8px 14px; border: 1.5px solid #E2E8F0; border-radius: 10px; font-size: 13px; background: white; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236C7A91' d='M6 8L0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
  
  .search-box { display: flex; gap: 8px; align-items: center; }
  .search-box input { padding: 10px 16px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-size: 14px; width: 280px; }
  .search-box input:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
  .search-box button { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
  
  /* Filter Bar */
  .filter-bar { padding: 12px 24px; background: #FAFBFF; border-bottom: 1px solid #EDF2F7; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
  .filter-chip { padding: 6px 14px; border-radius: 40px; font-size: 12px; font-weight: 500; cursor: pointer; background: white; border: 1px solid #E2E8F0; color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
  .filter-chip.active { background: #3B82F6; color: white; border-color: #3B82F6; }
  .filter-chip .count { background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 20px; font-size: 10px; }

  /* Table */
  .table-wrapper { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 14px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6C7A91; background: #FCFDFF; border-bottom: 1px solid #EDF2F7; }
  td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #F1F5F9; }
  tr:hover td { background: #F8FAFE; }
  
  .checkbox-col { width: 40px; text-align: center; }
  .checkbox-col input { width: 18px; height: 18px; cursor: pointer; }
  
  .action-icons { display: flex; gap: 12px; }
  .action-icons i { cursor: pointer; font-size: 16px; transition: opacity 0.2s; }
  .action-icons i:hover { opacity: 0.7; }
  .fa-edit { color: #3B82F6; }
  .fa-trash-alt { color: #EF4444; }
  .fa-eye { color: #10B981; }
  .fa-history { color: #8B5CF6; }
  
  .points-badge { font-weight: 600; color: #F59E0B; }
  .sessions-warning { color: #EF4444; font-weight: 600; }
  
  /* Bulk Actions Bar */
  .bulk-actions { display: none; padding: 12px 24px; background: #EFF6FF; border-bottom: 1px solid #DBEAFE; align-items: center; gap: 16px; flex-wrap: wrap; }
  .bulk-actions.show { display: flex; }
  
  /* Pagination */
  .pagination { display: flex; gap: 6px; padding: 16px 24px; justify-content: flex-end; border-top: 1px solid #F0F2F5; flex-wrap: wrap; }
  .page-btn { width: 36px; height: 36px; border: 1.5px solid #E2E8F0; border-radius: 10px; background: white; color: #3B82F6; font-weight: 600; cursor: pointer; }
  .page-btn.active { background: #3B82F6; color: white; border-color: #3B82F6; }
  .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

  /* Modal */
  .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
  .modal-content { background: white; border-radius: 24px; width: 550px; max-width: 90%; max-height: 85vh; overflow-y: auto; padding: 28px; }
  .modal-content h3 { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
  .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 12px 14px; margin-bottom: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: 'Inter', sans-serif; }
  .modal-content input:focus, .modal-content select:focus { outline: none; border-color: #3B82F6; }
  .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
  
  /* Toast */
  .toast { position: fixed; bottom: 24px; right: 24px; background: #1E293B; color: white; padding: 12px 20px; border-radius: 12px; font-size: 14px; transform: translateY(60px); opacity: 0; transition: all 0.3s; z-index: 9999; }
  .toast.show { transform: translateY(0); opacity: 1; }
  .toast.success { background: #10B981; }
  .toast.error { background: #EF4444; }

  @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } .toolbar { flex-direction: column; align-items: stretch; } .search-box { flex-direction: column; } .search-box input { width: 100%; } }
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
    <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
    <a href="Student_Information.php" class="nav-item active"><i class="fas fa-users"></i> Students</a>
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
    <div><h4><?php echo htmlspecialchars($admin_name); ?></h4><p>Administrator</p></div>
    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="header-section">
    <h1>Students Information</h1>
    <div class="action-buttons">
      <button class="btn btn-outline" onclick="openBulkImportModal()"><i class="fas fa-upload"></i> Bulk Import</button>
      <button class="btn btn-outline" onclick="exportToCSV()"><i class="fas fa-download"></i> Export CSV</button>
      <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Student</button>
      <form method="POST" style="display: inline;" onsubmit="return confirm('Reset all student sessions to 30?')">
        <button type="submit" name="reset_sessions" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Reset All Sessions</button>
      </form>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-grid">
    <div class="stat-card"><div><h4>Total Students</h4><div class="number"><?php echo $stats['total']; ?></div></div><div class="icon"><i class="fas fa-users"></i></div></div>
    <div class="stat-card"><div><h4>BSIT / BSCS / BSIS</h4><div class="number"><?php echo $stats['bsit']; ?> / <?php echo $stats['bscs']; ?> / <?php echo $stats['bsis']; ?></div></div><div class="icon"><i class="fas fa-code"></i></div></div>
    <div class="stat-card"><div><h4>Total Points Earned</h4><div class="number">⭐ <?php echo number_format($stats['total_points']); ?></div></div><div class="icon"><i class="fas fa-star"></i></div></div>
    <div class="stat-card"><div><h4>Avg Sessions Left</h4><div class="number"><?php echo round($stats['avg_sessions']); ?></div></div><div class="icon"><i class="fas fa-clock"></i></div></div>
  </div>

  <div class="table-card">
    <div class="toolbar">
      <div class="entries-label">Show <select class="entries-select" id="entriesSelect" onchange="changeEntries()">
        <option value="10" <?php echo $entries_per_page == 10 ? 'selected' : ''; ?>>10</option>
        <option value="25" <?php echo $entries_per_page == 25 ? 'selected' : ''; ?>>25</option>
        <option value="50" <?php echo $entries_per_page == 50 ? 'selected' : ''; ?>>50</option>
        <option value="100" <?php echo $entries_per_page == 100 ? 'selected' : ''; ?>>100</option>
      </select> entries</div>
      <div class="search-box"><input type="text" id="searchInput" placeholder="Search by ID, Name, Course or Email..." value="<?php echo htmlspecialchars($search); ?>"><button onclick="searchStudents()"><i class="fas fa-search"></i> Search</button></div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_course' => '', 'filter_year' => ''])); ?>" class="filter-chip <?php echo empty($filter_course) && empty($filter_year) ? 'active' : ''; ?>">All <span class="count"><?php echo $stats['total']; ?></span></a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_course' => 'BSIT', 'filter_year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSIT' ? 'active' : ''; ?>"><i class="fas fa-laptop-code"></i> BSIT <span class="count"><?php echo $stats['bsit']; ?></span></a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_course' => 'BSCS', 'filter_year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSCS' ? 'active' : ''; ?>"><i class="fas fa-microchip"></i> BSCS <span class="count"><?php echo $stats['bscs']; ?></span></a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_course' => 'BSIS', 'filter_year' => $filter_year])); ?>" class="filter-chip <?php echo $filter_course == 'BSIS' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> BSIS <span class="count"><?php echo $stats['bsis']; ?></span></a>
      <div style="margin-left: auto; display: flex; gap: 8px;">
        <select onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['filter_year' => ''])); ?>' + this.value" style="padding: 6px 12px; border-radius: 40px; border: 1px solid #E2E8F0;">
          <option value="">All Years</option>
          <option value="Year 1" <?php echo $filter_year == 'Year 1' ? 'selected' : ''; ?>>Year 1</option>
          <option value="Year 2" <?php echo $filter_year == 'Year 2' ? 'selected' : ''; ?>>Year 2</option>
          <option value="Year 3" <?php echo $filter_year == 'Year 3' ? 'selected' : ''; ?>>Year 3</option>
          <option value="Year 4" <?php echo $filter_year == 'Year 4' ? 'selected' : ''; ?>>Year 4</option>
        </select>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulkActions" class="bulk-actions">
      <span id="selectedCount">0</span> students selected
      <button class="btn btn-success" onclick="addSessionsToSelected()"><i class="fas fa-plus-circle"></i> Add Sessions</button>
      <button class="btn btn-danger" onclick="deleteSelected()"><i class="fas fa-trash-alt"></i> Delete Selected</button>
      <button class="btn btn-outline" onclick="clearSelection()">Clear</button>
    </div>

    <div class="table-wrapper">
      <form id="bulkForm" method="POST">
        <input type="hidden" name="selected_students" id="selectedStudentsInput">
        <input type="hidden" name="sessions_to_add" id="sessionsToAdd">
        <table>
          <thead>
            <tr><th class="checkbox-col"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
              <th>ID Number</th><th>Name</th><th>Year Level</th><th>Course</th><th>Email</th><th>Sessions Left</th><th>Points</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students && $students->num_rows > 0): ?>
              <?php while ($row = $students->fetch_assoc()): ?>
                <?php $full_name = $row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']; ?>
                <tr>
                  <td class="checkbox-col"><input type="checkbox" class="student-checkbox" value="<?php echo $row['id']; ?>" onchange="updateBulkActions()"></td>
                  <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                  <td><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                  <td><span class="badge" style="background:#EFF6FF; padding:4px 10px; border-radius:20px;"><?php echo htmlspecialchars($row['course']); ?></span></td>
                  <td><?php echo htmlspecialchars($row['email']); ?></td>
                  <td class="<?php echo $row['sessions'] < 5 ? 'sessions-warning' : ''; ?>"><?php echo $row['sessions']; ?> left</d>
                  <td class="points-badge">⭐ <?php echo $row['total_points'] ?? 0; ?></td>
                  <td class="action-icons">
                    <i class="fas fa-eye" onclick="viewStudent(<?php echo $row['id']; ?>)" title="View Details"></i>
                    <i class="fas fa-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit"></i>
                    <i class="fas fa-history" onclick="viewHistory('<?php echo $row['id_number']; ?>')" title="Point History"></i>
                    <i class="fas fa-trash-alt" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete"></i>
                   </d>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" style="text-align:center; padding:48px;"><i class="fas fa-user-graduate" style="font-size:48px; margin-bottom:16px; display:block;"></i>No students found</d></td>
            <?php endif; ?>
          </tbody>
        70
      </form>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <button class="page-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>←</button>
      <?php for ($i = 1; $i <= min($total_pages, 7); $i++): ?>
        <button class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
      <?php endfor; ?>
      <button class="page-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>→</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addModal" class="modal"><div class="modal-content"><h3><i class="fas fa-user-plus"></i> Add New Student</h3>
<form method="POST" id="addForm"><input type="text" name="id_number" placeholder="ID Number" required><input type="text" name="user_name" placeholder="Username" required><input type="text" name="first_name" placeholder="First Name" required><input type="text" name="middle_name" placeholder="Middle Name"><input type="text" name="last_name" placeholder="Last Name" required><select name="year_level" required><option value="">Select Year Level</option><option>Year 1</option><option>Year 2</option><option>Year 3</option><option>Year 4</option></select><select name="course" required><option value="">Select Course</option><option>BSIT</option><option>BSCS</option><option>BSIS</option></select><input type="email" name="email" placeholder="Email Address" required><input type="number" name="sessions" placeholder="Initial Sessions" value="30" required><div class="modal-buttons"><button type="submit" name="add_student" class="btn btn-primary">Add Student</button><button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button></div></form></div></div>

<!-- Edit Student Modal -->
<div id="editModal" class="modal"><div class="modal-content"><h3><i class="fas fa-user-edit"></i> Edit Student</h3><form id="editForm"><input type="hidden" name="student_id" id="edit_id"><input type="text" name="id_number" id="edit_id_number" placeholder="ID Number" required><input type="text" name="first_name" id="edit_first_name" placeholder="First Name" required><input type="text" name="middle_name" id="edit_middle_name" placeholder="Middle Name"><input type="text" name="last_name" id="edit_last_name" placeholder="Last Name" required><select name="year_level" id="edit_year_level" required><option>Year 1</option><option>Year 2</option><option>Year 3</option><option>Year 4</option></select><select name="course" id="edit_course" required><option>BSIT</option><option>BSCS</option><option>BSIS</option></select><input type="email" name="email" id="edit_email" placeholder="Email Address" required><input type="number" name="sessions" id="edit_sessions" placeholder="Sessions" required><input type="number" name="points" id="edit_points" placeholder="Points" required><div class="modal-buttons"><button type="button" class="btn btn-primary" onclick="submitEditForm()">Save Changes</button><button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button></div></form></div></div>

<!-- Bulk Import Modal -->
<div id="bulkImportModal" class="modal"><div class="modal-content"><h3><i class="fas fa-upload"></i> Bulk Import Students</h3><p style="color:#6C7A91; margin-bottom:16px;">Paste CSV data (ID Number, First Name, Middle Name, Last Name, Year Level, Course, Email, Sessions)</p><textarea id="importData" rows="8" placeholder='21478755,Jhyun,Libaton,Carampatana,Year 1,BSIS,carampatana@email.com,30&#10;25116633,Mark,Chester,Villamero,Year 1,BSIT,mark@email.com,30' style="width:100%; padding:12px; border:1.5px solid #E2E8F0; border-radius:12px; font-family:monospace;"></textarea><div class="modal-buttons"><button class="btn btn-primary" onclick="processBulkImport()">Import</button><button class="btn btn-outline" onclick="closeModal('bulkImportModal')">Cancel</button></div></div></div>

<!-- Add Sessions Modal -->
<div id="addSessionsModal" class="modal"><div class="modal-content"><h3><i class="fas fa-plus-circle"></i> Add Sessions to Selected Students</h3><div style="padding:16px; background:#F8FAFE; border-radius:12px; margin-bottom:16px;"><span id="bulkSelectedCount"></span> students selected</div><input type="number" id="sessionsToAddValue" placeholder="Number of sessions to add" min="1" max="30" value="5" style="width:100%; padding:12px; margin-bottom:20px;"><div class="modal-buttons"><button class="btn btn-primary" onclick="executeAddSessions()">Add Sessions</button><button class="btn btn-outline" onclick="closeModal('addSessionsModal')">Cancel</button></div></div></div>

<!-- View Student Details Modal -->
<div id="viewModal" class="modal"><div class="modal-content"><h3><i class="fas fa-user-circle"></i> Student Details</h3><div id="viewDetails"></div><div class="modal-buttons"><button class="btn btn-outline" onclick="closeModal('viewModal')">Close</button></div></div></div>

<div id="toast" class="toast"></div>

<script>
let selectedStudents = [];

function changeEntries() { const entries = document.getElementById('entriesSelect').value; const search = document.getElementById('searchInput').value; window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}&filter_course=<?php echo $filter_course; ?>&filter_year=<?php echo $filter_year; ?>`; }
function searchStudents() { const search = document.getElementById('searchInput').value; const entries = document.getElementById('entriesSelect').value; window.location.href = `?entries=${entries}&search=${encodeURIComponent(search)}&filter_course=<?php echo $filter_course; ?>&filter_year=<?php echo $filter_year; ?>`; }
function goToPage(page) { const entries = document.getElementById('entriesSelect').value; const search = document.getElementById('searchInput').value; window.location.href = `?page=${page}&entries=${entries}&search=${encodeURIComponent(search)}&filter_course=<?php echo $filter_course; ?>&filter_year=<?php echo $filter_year; ?>`; }
function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function openBulkImportModal() { document.getElementById('bulkImportModal').style.display = 'flex'; }
function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    selectedStudents = Array.from(checkboxes).map(cb => cb.value);
    const bulkDiv = document.getElementById('bulkActions');
    const countSpan = document.getElementById('selectedCount');
    
    if (selectedStudents.length > 0) {
        bulkDiv.classList.add('show');
        countSpan.innerHTML = selectedStudents.length;
    } else {
        bulkDiv.classList.remove('show');
    }
}

function clearSelection() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActions();
}

function addSessionsToSelected() {
    if (selectedStudents.length === 0) return;
    document.getElementById('bulkSelectedCount').innerHTML = selectedStudents.length;
    document.getElementById('addSessionsModal').style.display = 'flex';
}

function executeAddSessions() {
    const sessions = document.getElementById('sessionsToAddValue').value;
    if (!sessions || sessions < 1) return;
    
    document.getElementById('selectedStudentsInput').value = JSON.stringify(selectedStudents);
    document.getElementById('sessionsToAdd').value = sessions;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input name="add_sessions_bulk" value="1"><input name="selected_students" value='${JSON.stringify(selectedStudents)}'><input name="sessions_to_add" value="${sessions}">`;
    document.body.appendChild(form);
    form.submit();
}

function deleteSelected() {
    if (selectedStudents.length === 0) return;
    if (confirm(`Delete ${selectedStudents.length} selected students?`)) {
        const ids = selectedStudents.join(',');
        window.location.href = `?bulk_delete=${ids}&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>`;
    }
}

function confirmDelete(id) { if (confirm('Delete this student?')) { window.location.href = `?delete_id=${id}&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>`; } }

function openEditModal(student) {
    document.getElementById('edit_id').value = student.id;
    document.getElementById('edit_id_number').value = student.id_number;
    document.getElementById('edit_first_name').value = student.first_name || '';
    document.getElementById('edit_middle_name').value = student.middle_name || '';
    document.getElementById('edit_last_name').value = student.last_name || '';
    document.getElementById('edit_year_level').value = student.year_level;
    document.getElementById('edit_course').value = student.course;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_sessions').value = student.sessions;
    document.getElementById('edit_points').value = student.total_points || 0;
    document.getElementById('editModal').style.display = 'flex';
}

function submitEditForm() {
    const formData = new FormData(document.getElementById('editForm'));
    formData.append('edit_student', '1');
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => { if (data.success) { showToast('Student updated!', 'success'); setTimeout(() => location.reload(), 1000); } else { showToast('Error!', 'error'); } });
    closeModal('editModal');
}

function viewStudent(id) {
    fetch(`api/get_student_api.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('viewDetails').innerHTML = `
                    <div style="margin-bottom:12px;"><strong>ID Number:</strong> ${data.student.id_number}</div>
                    <div style="margin-bottom:12px;"><strong>Full Name:</strong> ${data.student.first_name} ${data.student.middle_name || ''} ${data.student.last_name}</div>
                    <div style="margin-bottom:12px;"><strong>Course & Year:</strong> ${data.student.course} - ${data.student.year_level}</div>
                    <div style="margin-bottom:12px;"><strong>Email:</strong> ${data.student.email}</div>
                    <div style="margin-bottom:12px;"><strong>Sessions Left:</strong> ${data.student.sessions}</div>
                    <div style="margin-bottom:12px;"><strong>Total Points:</strong> ⭐ ${data.student.total_points || 0}</div>
                    <div style="margin-bottom:12px;"><strong>Registered:</strong> ${data.student.created_at || 'N/A'}</div>`;
                document.getElementById('viewModal').style.display = 'flex';
            }
        });
}

function viewHistory(studentId) { window.location.href = `add_points.php?student=${studentId}`; }

function processBulkImport() {
    const text = document.getElementById('importData').value;
    const lines = text.split('\n');
    const students = [];
    for (let line of lines) {
        const parts = line.split(',');
        if (parts.length >= 7) {
            students.push({
                id_number: parts[0].trim(),
                first_name: parts[1].trim(),
                middle_name: parts[2].trim(),
                last_name: parts[3].trim(),
                year_level: parts[4].trim(),
                course: parts[5].trim(),
                email: parts[6].trim(),
                sessions: parts[7] ? parseInt(parts[7].trim()) : 30,
                user_name: parts[0].trim()
            });
        }
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input name="bulk_import" value="1"><input name="import_data" value='${JSON.stringify(students)}'>`;
    document.body.appendChild(form);
    form.submit();
}

function exportToCSV() {
    let csv = [['ID Number', 'First Name', 'Middle Name', 'Last Name', 'Year Level', 'Course', 'Email', 'Sessions Left', 'Points']];
    <?php 
    $all_students = $conn->query("SELECT * FROM students ORDER BY last_name ASC");
    while ($row = $all_students->fetch_assoc()): 
    ?>
    csv.push(['<?php echo addslashes($row['id_number']); ?>', '<?php echo addslashes($row['first_name']); ?>', '<?php echo addslashes($row['middle_name'] ?? ''); ?>', '<?php echo addslashes($row['last_name']); ?>', '<?php echo addslashes($row['year_level']); ?>', '<?php echo addslashes($row['course']); ?>', '<?php echo addslashes($row['email']); ?>', <?php echo $row['sessions']; ?>, <?php echo $row['total_points'] ?? 0; ?>]);
    <?php endwhile; ?>
    const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { type: 'text/csv' });
    const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'students_export_<?php echo date('Y-m-d'); ?>.csv'; link.click();
}

function showToast(message, type) { const toast = document.getElementById('toast'); toast.textContent = message; toast.className = `toast ${type} show`; setTimeout(() => toast.classList.remove('show'), 3000); }

window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = 'none'; }
document.getElementById('searchInput').addEventListener('keypress', function(e) { if (e.key === 'Enter') searchStudents(); });

<?php if (isset($success_msg)): ?>showToast('<?php echo addslashes($success_msg); ?>', 'success');<?php endif; ?>
<?php if (isset($error_msg)): ?>showToast('<?php echo addslashes($error_msg); ?>', 'error');<?php endif; ?>
</script>
</body>
</html>