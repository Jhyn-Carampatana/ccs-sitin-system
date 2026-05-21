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

// Get all students
$students_query = $conn->query("SELECT id_number, first_name, middle_name, last_name, course, year_level, sessions, total_points FROM students ORDER BY last_name ASC");

// Get point history
$point_history = $conn->query("SELECT ph.*, CONCAT(s.first_name, ' ', s.last_name) as student_name 
                                FROM point_history ph 
                                LEFT JOIN students s ON ph.student_id = s.id_number 
                                ORDER BY ph.created_at DESC LIMIT 20");

// Handle add points
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_points') {
        $student_id = $conn->real_escape_string($_POST['student_id']);
        $points = intval($_POST['points']);
        $reason = $conn->real_escape_string($_POST['reason']);
        
        // Update student points
        $update = $conn->query("UPDATE students SET total_points = total_points + $points WHERE id_number = '$student_id'");
        
        if ($update) {
            // Insert into point history
            $insert = $conn->query("INSERT INTO point_history (student_id, points_added, reason, admin_name) 
                                     VALUES ('$student_id', $points, '$reason', '$admin_name')");
            $message = "Successfully added $points points to student!";
            $messageType = "success";
        } else {
            $message = "Error adding points: " . $conn->error;
            $messageType = "error";
        }
    } elseif ($_POST['action'] === 'deduct_points') {
        $student_id = $conn->real_escape_string($_POST['student_id']);
        $points = intval($_POST['points']);
        $reason = $conn->real_escape_string($_POST['reason']);
        
        $update = $conn->query("UPDATE students SET total_points = total_points - $points WHERE id_number = '$student_id' AND total_points >= $points");
        
        if ($update && $conn->affected_rows > 0) {
            $insert = $conn->query("INSERT INTO point_history (student_id, points_added, reason, admin_name) 
                                     VALUES ('$student_id', -$points, '$reason', '$admin_name')");
            $message = "Successfully deducted $points points from student!";
            $messageType = "success";
        } else {
            $message = "Error: Insufficient points or student not found";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Admin - Add Perusal/Points</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; color: #1E293B; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; left: 0; top: 0; bottom: 0; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; padding-left: 8px; }
        .logo-image { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 700; display: none; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; text-decoration: none; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .nav-item:hover { background: #F1F5F9; color: #1E293B; }
        .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 22px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .page-header p { color: #6C7A91; margin-top: 4px; }
        
        /* Cards */
        .card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; margin-bottom: 32px; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #F0F2F5; background: #FAFBFF; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #3B82F6; }
        .card-body { padding: 24px; }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        label { font-size: 13px; font-weight: 600; color: #1E293B; display: block; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 13px; transition: all 0.2s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        
        /* Buttons */
        button { background: #3B82F6; color: white; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        button:hover { background: #2563EB; transform: translateY(-1px); }
        .btn-danger { background: #EF4444; }
        .btn-danger:hover { background: #DC2626; }
        
        /* Messages */
        .message { padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; }
        .message.success { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
        .message.error { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }
        
        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; background: #F8FAFE; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6C7A91; border-bottom: 1px solid #EDF2F7; }
        td { padding: 14px 16px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
        tr:hover td { background: #F8FAFE; }
        
        /* Badges */
        .badge { padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-positive { background: #DCFCE7; color: #15803D; }
        .badge-negative { background: #FEE2E2; color: #DC2626; }
        
        /* Layout */
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
        
        /* Responsive */
        @media (max-width: 900px) { 
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .two-columns { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR - Consistent with Admin Dashboard -->
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
        <a href="leaderboard.php" class="nav-item"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="add_points.php" class="nav-item active"><i class="fas fa-plus-circle"></i> Add Perusal/Point</a>
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

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <h1>Add Perusal / Points</h1>
        <p>Manage student points for perusal and performance tracking</p>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="two-columns">
        <!-- Add Points Form -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Add Points to Student</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_points">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Select Student</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php 
                            $students_query->data_seek(0);
                            while ($std = $students_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $std['id_number']; ?>"><?php echo $std['id_number'] . ' - ' . $std['first_name'] . ' ' . $std['last_name'] . ' (Current: ' . $std['total_points'] . ' pts)'; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Points to Add</label>
                        <input type="number" name="points" min="1" max="100" value="5" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Reason / Perusal Type</label>
                        <select name="reason" required>
                            <option value="Sit-in Completion">Sit-in Completion (+5 pts)</option>
                            <option value="Lab Assistance">Lab Assistance (+10 pts)</option>
                            <option value="Event Participation">Event Participation (+15 pts)</option>
                            <option value="Outstanding Performance">Outstanding Performance (+25 pts)</option>
                            <option value="Perusal Request">Perusal Request (Academic)</option>
                            <option value="Perfect Attendance">Perfect Attendance (+20 pts)</option>
                            <option value="Project Submission">Project Submission (+10 pts)</option>
                        </select>
                    </div>
                    <button type="submit"><i class="fas fa-plus"></i> Add Points</button>
                </form>
            </div>
        </div>
        
        <!-- Deduct Points Form -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-minus-circle"></i> Deduct Points</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="deduct_points">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Select Student</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php 
                            $students_query->data_seek(0);
                            while ($std = $students_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $std['id_number']; ?>"><?php echo $std['id_number'] . ' - ' . $std['first_name'] . ' ' . $std['last_name'] . ' (Current: ' . $std['total_points'] . ' pts)'; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Points to Deduct</label>
                        <input type="number" name="points" min="1" max="50" value="5" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Reason for Deduction</label>
                        <select name="reason" required>
                            <option value="Violation of Lab Rules">Violation of Lab Rules</option>
                            <option value="Incomplete Requirements">Incomplete Requirements</option>
                            <option value="Misconduct">Misconduct</option>
                            <option value="No Show for Reservation">No Show for Reservation</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-danger" style="background:#EF4444;"><i class="fas fa-minus"></i> Deduct Points</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Point History -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-history"></i> Recent Point Transactions</h3></div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Points</th>
                        <th>Reason</th>
                        <th>Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($point_history && $point_history->num_rows > 0): ?>
                        <?php while ($row = $point_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><span class="badge <?php echo $row['points_added'] > 0 ? 'badge-positive' : 'badge-negative'; ?>"><?php echo $row['points_added'] > 0 ? '+' : ''; ?><?php echo $row['points_added']; ?></span></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 48px;">
                            <i class="fas fa-exchange-alt" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                            No point transactions yet
                         </d>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>