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

// ========== MIGRATE OLD LAB NAMES TO NEW ONES ==========
// This converts existing records from old lab names to new ones
$conn->query("UPDATE sit_in_sessions SET laboratory = 'Lab 544' WHERE laboratory = 'Lab 1'");
$conn->query("UPDATE sit_in_sessions SET laboratory = 'Lab 524' WHERE laboratory = 'Lab 2'");
$conn->query("UPDATE sit_in_sessions SET laboratory = 'Lab 526' WHERE laboratory = 'Lab 3'");
$conn->query("UPDATE sit_in_sessions SET laboratory = 'Lab 528' WHERE laboratory = 'Lab 4'");

// ========== AUTO-FIX DATABASE STRUCTURE ==========
// Check and add missing columns automatically
$columns_check = $conn->query("SHOW COLUMNS FROM sit_in_sessions");
$existing_columns = [];
while ($col = $columns_check->fetch_assoc()) {
    $existing_columns[] = $col['Field'];
}

if (!in_array('duration_minutes', $existing_columns)) {
    $conn->query("ALTER TABLE sit_in_sessions ADD COLUMN duration_minutes INT DEFAULT 0 AFTER time_out");
}
if (!in_array('ended_by', $existing_columns)) {
    $conn->query("ALTER TABLE sit_in_sessions ADD COLUMN ended_by VARCHAR(50) DEFAULT NULL AFTER status");
}

// Check and update status enum
$status_check = $conn->query("SHOW COLUMNS FROM sit_in_sessions LIKE 'status'");
$status_row = $status_check->fetch_assoc();
if (strpos($status_row['Type'], 'cancelled') === false) {
    $conn->query("ALTER TABLE sit_in_sessions MODIFY COLUMN status ENUM('active', 'completed', 'cancelled') DEFAULT 'active'");
}

$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Get active sessions count per lab for real-time occupancy
$lab_occupancy = [];
$lab_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions WHERE status = 'active' GROUP BY laboratory");
while ($row = $lab_query->fetch_assoc()) {
    $lab_occupancy[$row['laboratory']] = $row['count'];
}

// Start new sit-in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_sit_in'])) {
    $id_number = trim($_POST['id_number']);
    $purpose = trim($_POST['purpose']);
    $laboratory = trim($_POST['laboratory']);
    
    // Check lab capacity (max 30 per lab)
    $current_lab_count = $lab_occupancy[$laboratory] ?? 0;
    $lab_capacity = 30;
    
    if ($current_lab_count >= $lab_capacity) {
        $error_msg = "Lab $laboratory is at full capacity (30/30). Please try another lab.";
    } else {
        $sql = "SELECT id, first_name, middle_name, last_name, sessions, course, year_level FROM students WHERE id_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $full_name = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']);
            
            if ($student['sessions'] > 0) {
                $check_stmt = $conn->prepare("SELECT id FROM sit_in_sessions WHERE student_id = ? AND status = 'active'");
                $check_stmt->bind_param("i", $student['id']);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows == 0) {
                    $new_sessions = $student['sessions'] - 1;
                    $update_stmt = $conn->prepare("UPDATE students SET sessions = ? WHERE id = ?");
                    $update_stmt->bind_param("ii", $new_sessions, $student['id']);
                    $update_stmt->execute();
                    
                    $time_in = date('Y-m-d H:i:s');
                    $insert_stmt = $conn->prepare("INSERT INTO sit_in_sessions (student_id, id_number, student_name, purpose, laboratory, time_in, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $insert_stmt->bind_param("isssss", $student['id'], $id_number, $full_name, $purpose, $laboratory, $time_in);
                    
                    if ($insert_stmt->execute()) {
                        $lab_occupancy[$laboratory] = ($lab_occupancy[$laboratory] ?? 0) + 1;
                        $success_msg = "Sit-in started for " . htmlspecialchars($full_name) . " in $laboratory";
                    } else {
                        $error_msg = "Error starting session";
                    }
                    $insert_stmt->close();
                    $update_stmt->close();
                } else {
                    $error_msg = "Student already has an active session!";
                }
                $check_stmt->close();
            } else {
                $error_msg = "No remaining sessions! Please reset sessions for this student.";
            }
        } else {
            $error_msg = "Student ID not found!";
        }
        $stmt->close();
    }
}

// End session with points awarding
if (isset($_GET['end_session'])) {
    $session_id = (int)$_GET['end_session'];
    $time_out = date('Y-m-d H:i:s');
    
    $get_stmt = $conn->prepare("SELECT student_id, time_in, laboratory FROM sit_in_sessions WHERE id = ? AND status = 'active'");
    $get_stmt->bind_param("i", $session_id);
    $get_stmt->execute();
    $session = $get_stmt->get_result()->fetch_assoc();
    
    if ($session) {
        $time_in = new DateTime($session['time_in']);
        $time_out_dt = new DateTime($time_out);
        $interval = $time_in->diff($time_out_dt);
        $duration_minutes = ($interval->h * 60) + $interval->i;
        $points_earned = 5 + floor($duration_minutes / 30) * 5;
        
        $stmt = $conn->prepare("UPDATE sit_in_sessions SET time_out = ?, status = 'completed', duration_minutes = ?, points_earned = ?, ended_by = ? WHERE id = ?");
        $ended_by = $admin_name;
        $stmt->bind_param("siisi", $time_out, $duration_minutes, $points_earned, $ended_by, $session_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $update_points = $conn->prepare("UPDATE students SET total_points = COALESCE(total_points, 0) + ? WHERE id = ?");
            $update_points->bind_param("ii", $points_earned, $session['student_id']);
            $update_points->execute();
            $update_points->close();
            
            $lab = $session['laboratory'];
            $lab_occupancy[$lab] = max(0, ($lab_occupancy[$lab] ?? 1) - 1);
            
            $success_msg = "Session ended! Duration: " . floor($duration_minutes/60) . "h " . ($duration_minutes%60) . "m | Points earned: $points_earned";
        } else {
            $error_msg = "Error ending session.";
        }
        $stmt->close();
    }
    $get_stmt->close();
}

// Cancel session (without awarding points)
if (isset($_GET['cancel_session'])) {
    $session_id = (int)$_GET['cancel_session'];
    
    $get_stmt = $conn->prepare("SELECT student_id, laboratory FROM sit_in_sessions WHERE id = ? AND status = 'active'");
    $get_stmt->bind_param("i", $session_id);
    $get_stmt->execute();
    $session = $get_stmt->get_result()->fetch_assoc();
    
    if ($session) {
        $stmt = $conn->prepare("UPDATE sit_in_sessions SET status = 'cancelled', ended_by = ? WHERE id = ?");
        $ended_by = $admin_name;
        $stmt->bind_param("si", $ended_by, $session_id);
        
        if ($stmt->execute()) {
            $conn->query("UPDATE students SET sessions = sessions + 1 WHERE id = {$session['student_id']}");
            
            $lab = $session['laboratory'];
            $lab_occupancy[$lab] = max(0, ($lab_occupancy[$lab] ?? 1) - 1);
            
            $success_msg = "Session cancelled successfully.";
        } else {
            $error_msg = "Error cancelling session.";
        }
        $stmt->close();
    }
    $get_stmt->close();
}

// Get filter parameters
$filter_lab = isset($_GET['filter_lab']) ? $_GET['filter_lab'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for active sessions
$conditions = [];
if ($filter_lab) {
    $conditions[] = "laboratory = '$filter_lab'";
}
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $conditions[] = "(id_number LIKE '%$search_escaped%' OR student_name LIKE '%$search_escaped%')";
}
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$active_sessions = $conn->query("SELECT * FROM sit_in_sessions WHERE status = 'active' $where_clause ORDER BY time_in ASC");

// Get today's completed sessions
$today_sessions = $conn->query("SELECT * FROM sit_in_sessions WHERE status = 'completed' AND DATE(time_in) = CURDATE() ORDER BY time_out DESC LIMIT 20");

// Get statistics
$stats = [
    'active' => $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'active'")->fetch_assoc()['count'],
    'today' => $conn->query("SELECT COUNT(*) as count FROM sit_in_sessions WHERE status = 'completed' AND DATE(time_in) = CURDATE()")->fetch_assoc()['count'],
    'total_points' => 0,
    'avg_duration' => 0,
];

$points_result = $conn->query("SELECT SUM(points_earned) as sum FROM sit_in_sessions WHERE status = 'completed'");
if ($points_result) {
    $stats['total_points'] = $points_result->fetch_assoc()['sum'] ?? 0;
}

$duration_result = $conn->query("SELECT AVG(duration_minutes) as avg FROM sit_in_sessions WHERE status = 'completed' AND duration_minutes > 0");
if ($duration_result) {
    $stats['avg_duration'] = $duration_result->fetch_assoc()['avg'] ?? 0;
}

$today_date = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CCS Admin - Sit-in Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
  
  /* Sidebar */
  .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
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
  
  /* Stats Grid */
  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
  .stat-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #EFF3F8; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
  .stat-card h4 { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-card .number { font-size: 28px; font-weight: 800; color: #0F172A; }
  .stat-card .icon { width: 48px; height: 48px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3B82F6; font-size: 24px; }
  
  /* Cards */
  .start-card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #EFF3F8; }
  .start-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
  .form-group { flex: 1; min-width: 200px; }
  .form-group label { font-size: 12px; font-weight: 600; color: #6C7A91; margin-bottom: 6px; display: block; }
  .form-group input, .form-group select { width: 100%; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.2s; }
  .form-group input:focus, .form-group select:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
  .btn-start { background: #3B82F6; color: white; padding: 12px 28px; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-top: 8px; transition: all 0.2s; }
  .btn-start:hover { background: #2563EB; transform: translateY(-1px); }
  
  /* Toolbar */
  .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #FAFBFF; border-bottom: 1px solid #EDF2F7; flex-wrap: wrap; gap: 12px; }
  .search-box { display: flex; gap: 8px; align-items: center; }
  .search-box input { padding: 10px 16px; border: 1.5px solid #E2E8F0; border-radius: 12px; width: 280px; font-size: 14px; }
  .search-box input:focus { outline: none; border-color: #3B82F6; }
  .filter-chips { display: flex; gap: 8px; flex-wrap: wrap; }
  .filter-chip { padding: 6px 16px; border-radius: 40px; font-size: 13px; background: #F1F5F9; color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
  .filter-chip:hover { background: #E2E8F0; }
  .filter-chip.active { background: #3B82F6; color: white; }
  
  /* Table */
  .table-card { background: white; border-radius: 24px; overflow: hidden; border: 1px solid #EFF3F8; margin-bottom: 32px; }
  .section-header { padding: 20px 24px; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
  .section-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .table-wrapper { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #F1F5F9; }
  th { background: #F8FAFC; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6C7A91; letter-spacing: 0.5px; }
  tr:hover td { background: #F8FAFE; }
  .status-badge { background: #DCFCE7; color: #15803D; padding: 4px 12px; border-radius: 40px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
  .timer { font-family: monospace; font-weight: 600; color: #3B82F6; }
  .points-badge { font-weight: 600; color: #F59E0B; }
  .btn-end { background: #EF4444; color: white; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
  .btn-end:hover { background: #DC2626; }
  .btn-cancel { background: #F1F5F9; color: #475569; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin-right: 8px; transition: all 0.2s; }
  .btn-cancel:hover { background: #E2E8F0; }
  .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
  .empty-row td { text-align: center; padding: 48px !important; color: #8A99B0; }
  .empty-icon { font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5; }
  
  /* Export Button */
  .export-btn { background: #10B981; color: white; border: none; padding: 8px 20px; border-radius: 40px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
  .export-btn:hover { background: #059669; }
  
  /* Refresh Button */
  .refresh-btn { background: none; border: none; color: #3B82F6; cursor: pointer; font-size: 16px; padding: 8px; border-radius: 40px; transition: all 0.2s; }
  .refresh-btn:hover { background: #EFF6FF; }
  
  /* Toast */
  .toast { position: fixed; bottom: 24px; right: 24px; background: #1E293B; color: white; padding: 12px 20px; border-radius: 12px; transform: translateY(60px); opacity: 0; transition: all 0.3s; z-index: 9999; font-size: 14px; }
  .toast.show { transform: translateY(0); opacity: 1; }
  .toast.success { background: #10B981; }
  .toast.error { background: #EF4444; }
  
  /* Responsive */
  @media (max-width: 1000px) { 
    .main-content { margin-left: 0; padding: 20px; } 
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .stats-grid { grid-template-columns: repeat(2,1fr); }
    .form-row { flex-direction: column; }
    .btn-start { width: 100%; justify-content: center; }
    .toolbar { flex-direction: column; align-items: stretch; }
    .search-box { flex-direction: column; }
    .search-box input { width: 100%; }
    .filter-chips { justify-content: center; }
  }
  
  @media print {
    .sidebar, .stats-grid, .start-card, .toolbar, .action-buttons, .btn-end, .btn-cancel, .export-btn, .refresh-btn { display: none; }
    .main-content { margin-left: 0; padding: 0; }
    .table-card { border: none; }
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
    <a href="Search_Student.php" class="nav-item"><i class="fas fa-search"></i> Search Student</a>
    <a href="Student_Information.php" class="nav-item"><i class="fas fa-users"></i> Students</a>
    <a href="sit_in_management.php" class="nav-item active"><i class="fas fa-chair"></i> Sit-in</a>
    <a href="reservation_management.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
    <a href="announcement_management.php" class="nav-item"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="reports.php" class="nav-item"><i class="fas fa-chart-pie"></i> Reports</a>
    <a href="leaderboard.php" class="nav-item"><i class="fas fa-trophy"></i> Leaderboard</a>
    <a href="add_points.php" class="nav-item"><i class="fas fa-plus-circle"></i> Add Perusal/Point</a>
    <a href="view_performance.php" class="nav-item"><i class="fas fa-chart-simple"></i> View Performance</a>
  </div>
  <div class="bottom-user">
    <div class="user-avatar"><?php echo $admin_initial; ?></div>
    <div>
      <h4><?php echo htmlspecialchars($admin_name); ?></h4>
      <p>Administrator</p>
    </div>
    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<div class="main-content">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h1 style="font-size: 26px; font-weight: 700;">Sit-in Management</h1>
    <button class="refresh-btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div><h4><i class="fas fa-clock"></i> Active Sessions</h4><div class="number"><?php echo $stats['active']; ?></div></div>
      <div class="icon"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card">
      <div><h4><i class="fas fa-calendar-day"></i> Today's Sessions</h4><div class="number"><?php echo $stats['today']; ?></div></div>
      <div class="icon"><i class="fas fa-chart-line"></i></div>
    </div>
    <div class="stat-card">
      <div><h4><i class="fas fa-star"></i> Points Earned</h4><div class="number">⭐ <?php echo number_format($stats['total_points']); ?></div></div>
      <div class="icon"><i class="fas fa-trophy"></i></div>
    </div>
    <div class="stat-card">
      <div><h4><i class="fas fa-hourglass-half"></i> Avg Duration</h4><div class="number"><?php echo round($stats['avg_duration']); ?> min</div></div>
      <div class="icon"><i class="fas fa-chart-bar"></i></div>
    </div>
  </div>

  <!-- Start New Session Card -->
  <div class="start-card">
    <h3><i class="fas fa-plus-circle"></i> Start New Session</h3>
    <form method="POST" id="startSessionForm">
      <div class="form-row">
        <div class="form-group">
          <label><i class="fas fa-id-card"></i> Student ID Number</label>
          <input type="text" name="id_number" id="studentIdInput" placeholder="Enter student ID" list="studentList" autocomplete="off" required>
          <datalist id="studentList">
            <?php 
            $student_list = $conn->query("SELECT id_number, first_name, last_name FROM students WHERE sessions > 0 ORDER BY last_name ASC LIMIT 50");
            while ($s = $student_list->fetch_assoc()): 
            ?>
              <option value="<?php echo $s['id_number']; ?>"><?php echo $s['first_name'] . ' ' . $s['last_name']; ?></option>
            <?php endwhile; ?>
          </datalist>
        </div>
        <div class="form-group">
          <label><i class="fas fa-tasks"></i> Select Purpose</label>
          <select name="purpose" required>
            <option value="">Select Purpose</option>
            <option value="Programming">💻 Programming</option>
            <option value="Thesis">📚 Thesis</option>
            <option value="Research">🔬 Research</option>
            <option value="C Programming">⚙️ C Programming</option>
            <option value="Web Development">🌐 Web Development</option>
            <option value="Database Design">🗄️ Database Design</option>
            <option value="Networking">🌍 Networking</option>
            <option value="Multimedia">🎨 Multimedia</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-microscope"></i> Select Laboratory</label>
          <select name="laboratory" id="labSelect" required>
            <option value="">Select Laboratory</option>
            <option value="Lab 544">Lab 544</option>
            <option value="Lab 524">Lab 524</option>
            <option value="Lab 526">Lab 526</option>
            <option value="Lab 528">Lab 528</option>
            <option value="Lab 530">Lab 530</option>
          </select>
        </div>
      </div>
      <button type="submit" name="start_sit_in" class="btn-start"><i class="fas fa-door-open"></i> Start Sit-in</button>
    </form>
  </div>

  <!-- Current Active Sessions Table -->
  <div class="table-card">
    <div class="section-header">
      <h3><i class="fas fa-users"></i> Current Sit-in Sessions</h3>
    </div>
    <div class="toolbar">
      <div class="search-box">
        <input type="text" id="activeSearch" placeholder="Search by ID or Name..." onkeyup="filterActiveSessions()">
        <i class="fas fa-search" style="margin-left: -30px; color: #94A3B8;"></i>
      </div>
      <div class="filter-chips">
        <a href="?filter_lab=" class="filter-chip <?php echo empty($filter_lab) ? 'active' : ''; ?>">All Labs</a>
        <a href="?filter_lab=Lab%20544" class="filter-chip <?php echo $filter_lab == 'Lab 544' ? 'active' : ''; ?>">Lab 544</a>
        <a href="?filter_lab=Lab%20524" class="filter-chip <?php echo $filter_lab == 'Lab 524' ? 'active' : ''; ?>">Lab 524</a>
        <a href="?filter_lab=Lab%20526" class="filter-chip <?php echo $filter_lab == 'Lab 526' ? 'active' : ''; ?>">Lab 526</a>
        <a href="?filter_lab=Lab%20528" class="filter-chip <?php echo $filter_lab == 'Lab 528' ? 'active' : ''; ?>">Lab 528</a>
        <a href="?filter_lab=Lab%20530" class="filter-chip <?php echo $filter_lab == 'Lab 530' ? 'active' : ''; ?>">Lab 530</a>
      </div>
    </div>
    <div class="table-wrapper">
      <table id="activeSessionsTable">
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Student Name</th>
            <th>Purpose</th>
            <th>Laboratory</th>
            <th>Time In</th>
            <th>Duration</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($active_sessions && $active_sessions->num_rows > 0): ?>
            <?php while ($s = $active_sessions->fetch_assoc()): 
              $time_in = new DateTime($s['time_in']);
              $now = new DateTime();
              $duration = $time_in->diff($now);
              $duration_str = ($duration->h > 0 ? $duration->h . 'h ' : '') . $duration->i . 'm';
            ?>
              <tr>
                <td><?php echo htmlspecialchars($s['id_number']); ?></td>
                <td><strong><?php echo htmlspecialchars($s['student_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($s['purpose']); ?></td>
                <td><span style="background:#EFF6FF; padding:4px 10px; border-radius:20px;"><?php echo htmlspecialchars($s['laboratory']); ?></span></td>
                <td><?php echo date('g:i A', strtotime($s['time_in'])); ?></td>
                <td class="timer" data-time="<?php echo $s['time_in']; ?>"><?php echo $duration_str; ?></td>
                <td><span class="status-badge"><i class="fas fa-circle" style="font-size: 8px; margin-right: 6px; color: #10B981;"></i> Active</span></td>
                <td class="action-buttons">
                  <a href="?end_session=<?php echo $s['id']; ?>" class="btn-end" onclick="return confirm('End this sit-in session? Student will earn points based on duration.')"><i class="fas fa-sign-out-alt"></i> Time Out</a>
                  <a href="?cancel_session=<?php echo $s['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this session? No points will be awarded and session will be returned to student.')"><i class="fas fa-times"></i> Cancel</a>
                 </d>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="8">
                <i class="fas fa-chair empty-icon"></i>
                No active sessions
               </d>
             </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Today's Completed Sessions -->
  <div class="table-card">
    <div class="section-header">
      <h3><i class="fas fa-history"></i> Today's Completed Sessions - <?php echo $today_date; ?></h3>
      <button class="export-btn" onclick="exportTodaySessions()"><i class="fas fa-download"></i> Export CSV</button>
    </div>
    <div class="table-wrapper">
      <table id="todaySessionsTable">
        <thead>
          <tr>
            <th>Time Out</th>
            <th>ID Number</th>
            <th>Student Name</th>
            <th>Lab</th>
            <th>Duration</th>
            <th>Points</th>
          </table>
        </thead>
        <tbody>
          <?php if ($today_sessions && $today_sessions->num_rows > 0): ?>
            <?php while ($s = $today_sessions->fetch_assoc()): ?>
              <tr>
                <td><?php echo date('g:i A', strtotime($s['time_out'])); ?></td>
                <td><?php echo htmlspecialchars($s['id_number']); ?></td>
                <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                <td><?php echo htmlspecialchars($s['laboratory']); ?></td>
                <td><?php echo floor($s['duration_minutes']/60) . 'h ' . ($s['duration_minutes']%60) . 'm'; ?></td>
                <td class="points-badge">⭐ <?php echo $s['points_earned']; ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row">
              <td colspan="6">
                <i class="fas fa-calendar-day empty-icon"></i>
                No completed sessions today
               </d>
             </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
// Real-time timer update
function updateTimers() {
    const timers = document.querySelectorAll('.timer');
    timers.forEach(timer => {
        const timeInStr = timer.getAttribute('data-time');
        if (timeInStr) {
            const timeIn = new Date(timeInStr);
            const now = new Date();
            const diff = Math.floor((now - timeIn) / 1000);
            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            timer.textContent = (hours > 0 ? hours + 'h ' : '') + minutes + 'm';
        }
    });
}
setInterval(updateTimers, 60000);
updateTimers();

// Filter active sessions
function filterActiveSessions() {
    const searchTerm = document.getElementById('activeSearch').value.toLowerCase();
    const table = document.getElementById('activeSessionsTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    const rows = tbody.getElementsByTagName('tr');
    
    for (let row of rows) {
        if (row.classList.contains('empty-row')) continue;
        const idNumber = row.cells[0]?.innerText.toLowerCase() || '';
        const studentName = row.cells[1]?.innerText.toLowerCase() || '';
        if (idNumber.includes(searchTerm) || studentName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Export today's sessions to CSV
function exportTodaySessions() {
    const table = document.getElementById('todaySessionsTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    let csv = [['Time Out', 'ID Number', 'Student Name', 'Laboratory', 'Duration', 'Points']];
    const rows = tbody.getElementsByTagName('tr');
    
    for (let row of rows) {
        if (row.classList.contains('empty-row')) continue;
        if (row.cells.length >= 6) {
            csv.push([
                row.cells[0]?.innerText || '',
                row.cells[1]?.innerText || '',
                row.cells[2]?.innerText || '',
                row.cells[3]?.innerText || '',
                row.cells[4]?.innerText || '',
                row.cells[5]?.innerText || ''
            ]);
        }
    }
    
    if (csv.length === 1) {
        showToast('No data to export', 'error');
        return;
    }
    
    const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'sit_in_sessions_<?php echo date('Y-m-d'); ?>.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Show toast message
function showToast(msg, type) { 
    const t = document.getElementById('toast'); 
    t.textContent = msg; 
    t.className = `toast ${type} show`; 
    setTimeout(() => t.classList.remove('show'), 3000); 
}

// Validate student before form submission
document.getElementById('startSessionForm')?.addEventListener('submit', function(e) {
    const studentId = document.getElementById('studentIdInput').value;
    const labSelect = document.getElementById('labSelect');
    
    if (!studentId) {
        e.preventDefault();
        showToast('Please enter a student ID', 'error');
        return;
    }
    
    if (!labSelect.value) {
        e.preventDefault();
        showToast('Please select a laboratory', 'error');
        return;
    }
});

// Press Enter to search
document.getElementById('activeSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        filterActiveSessions();
    }
});

<?php if(isset($success_msg)) echo "showToast('".addslashes($success_msg)."','success');"; ?>
<?php if(isset($error_msg)) echo "showToast('".addslashes($error_msg)."','error');"; ?>
</script>
</body>
</html>