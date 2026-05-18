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

$create_table_sql = "
CREATE TABLE IF NOT EXISTS sit_in_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    id_number VARCHAR(20) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    purpose VARCHAR(50) NOT NULL,
    laboratory VARCHAR(20) NOT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME DEFAULT NULL,
    points_earned INT DEFAULT 0,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

$admin_name = $_SESSION['admin_name'] ?? 'CCS Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 2));

// Start new sit-in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_sit_in'])) {
    $id_number = trim($_POST['id_number']);
    $purpose = trim($_POST['purpose']);
    $laboratory = trim($_POST['laboratory']);
    
    $sql = "SELECT id, first_name, middle_name, last_name, sessions FROM students WHERE id_number = ?";
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
                    $success_msg = "Sit-in started for " . htmlspecialchars($full_name);
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
            $error_msg = "No remaining sessions!";
        }
    } else {
        $error_msg = "Student ID not found!";
    }
    $stmt->close();
}

// End session with points awarding
if (isset($_GET['end_session'])) {
    $session_id = (int)$_GET['end_session'];
    $time_out = date('Y-m-d H:i:s');
    
    $get_stmt = $conn->prepare("SELECT student_id, time_in FROM sit_in_sessions WHERE id = ? AND status = 'active'");
    $get_stmt->bind_param("i", $session_id);
    $get_stmt->execute();
    $session = $get_stmt->get_result()->fetch_assoc();
    
    if ($session) {
        $time_in = new DateTime($session['time_in']);
        $time_out_dt = new DateTime($time_out);
        $duration_minutes = ($time_in->diff($time_out_dt)->h * 60) + $time_in->diff($time_out_dt)->i;
        $points_earned = max(5, floor($duration_minutes / 30) * 5);
        
        $stmt = $conn->prepare("UPDATE sit_in_sessions SET time_out = ?, status = 'completed', points_earned = ? WHERE id = ?");
        $stmt->bind_param("sii", $time_out, $points_earned, $session_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $update_points = $conn->prepare("UPDATE students SET total_points = COALESCE(total_points, 0) + ? WHERE id = ?");
            $update_points->bind_param("ii", $points_earned, $session['student_id']);
            $update_points->execute();
            $update_points->close();
            $success_msg = "Session ended! Student earned $points_earned points.";
        } else {
            $error_msg = "Error ending session.";
        }
        $stmt->close();
    }
    $get_stmt->close();
}

$active_sessions = $conn->query("SELECT * FROM sit_in_sessions WHERE status = 'active' ORDER BY time_in DESC");
$today = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Admin - Sit-in Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
  
  /* Sidebar */
  .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
  .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
  .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
  .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
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
  .start-card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #EFF3F8; }
  .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
  .form-group { flex: 1; min-width: 180px; }
  .form-group input, .form-group select { width: 100%; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: 'Inter', sans-serif; }
  .form-group input:focus, .form-group select:focus { outline: none; border-color: #3B82F6; }
  .btn-start { background: #3B82F6; color: white; padding: 12px 28px; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
  .btn-start:hover { background: #2563EB; transform: translateY(-1px); }
  .table-card { background: white; border-radius: 24px; overflow: hidden; border: 1px solid #EFF3F8; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #F1F5F9; }
  th { background: #F8FAFC; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6C7A91; }
  tr:hover td { background: #F8FAFE; }
  .status-badge { background: #DCFCE7; color: #15803D; padding: 4px 12px; border-radius: 40px; font-size: 12px; font-weight: 600; display: inline-block; }
  .btn-end { background: #EF4444; color: white; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
  .btn-end:hover { background: #DC2626; }
  .toast { position: fixed; bottom: 24px; right: 24px; background: #1E293B; color: white; padding: 12px 20px; border-radius: 12px; transform: translateY(60px); opacity: 0; transition: all 0.3s; z-index: 9999; }
  .toast.show { transform: translateY(0); opacity: 1; }
  .toast.success { background: #10B981; }
  .toast.error { background: #EF4444; }
  
  @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } .form-row { flex-direction: column; } .btn-start { width: 100%; justify-content: center; } }
</style>
</head>
<body>

<!-- UNIFIED SIDEBAR - Same on all pages -->
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
  <h1>Sit-in Management</h1>
  
  <div class="start-card">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle"></i> Start New Session</h3>
    <form method="POST">
      <div class="form-row">
        <div class="form-group"><input type="text" name="id_number" placeholder="Student ID Number" required></div>
        <div class="form-group">
          <select name="purpose" required>
            <option value="">Select Purpose</option>
            <option>Course Related</option>
            <option>Research</option>
            <option>Thesis</option>
            <option>Programming</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <select name="laboratory" required>
            <option value="">Select Laboratory</option>
            <option>Lab 1</option>
            <option>Lab 2</option>
            <option>Lab 3</option>
            <option>Lab 4</option>
          </select>
        </div>
        <div class="form-group"><button type="submit" name="start_sit_in" class="btn-start"><i class="fas fa-door-open"></i> Start Sit-in</button></div>
      </div>
    </form>
  </div>

  <div class="table-card">
    <h3 style="padding: 20px 24px;"><i class="fas fa-users"></i> Current Sit-in Sessions</h3>
    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Student Name</th>
            <th>Purpose</th>
            <th>Laboratory</th>
            <th>Time In</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($active_sessions && $active_sessions->num_rows > 0): ?>
            <?php while ($s = $active_sessions->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($s['id_number']); ?></td>
                <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                <td><?php echo htmlspecialchars($s['purpose']); ?></td>
                <td><?php echo htmlspecialchars($s['laboratory']); ?></td>
                <td><?php echo date('g:i A', strtotime($s['time_in'])); ?></td>
                <td><span class="status-badge"><i class="fas fa-circle" style="font-size: 8px; margin-right: 6px;"></i> Active</span></td>
                <td><a href="?end_session=<?php echo $s['id']; ?>" class="btn-end" onclick="return confirm('End this sit-in session?')"><i class="fas fa-sign-out-alt"></i> Time Out</a></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:48px;">
                <i class="fas fa-chair" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                No active sessions
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>
<script>
function showToast(msg, type) { 
    let t = document.getElementById('toast'); 
    t.textContent = msg; 
    t.className = `toast ${type} show`; 
    setTimeout(() => t.classList.remove('show'), 3000); 
}
<?php if(isset($success_msg)) echo "showToast('".addslashes($success_msg)."','success');"; ?>
<?php if(isset($error_msg)) echo "showToast('".addslashes($error_msg)."','error');"; ?>
</script>
</body>
</html>