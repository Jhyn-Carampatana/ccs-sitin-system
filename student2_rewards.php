<?php
session_start();

// Redirect if not logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Get student info with points
$student_query = $conn->query("SELECT * FROM students WHERE id_number = '$student_id'");
$student = $student_query->fetch_assoc();
$current_points = $student['total_points'] ?? 0;

// Get point history for this student only
$point_history = $conn->query("SELECT * FROM point_history WHERE student_id = '$student_id' ORDER BY created_at DESC LIMIT 10");

// Define rewards available for students
$rewards = [
    ['name' => 'CCS T-Shirt', 'points' => 100, 'icon' => 'fa-tshirt', 'color' => '#3B82F6'],
    ['name' => '5 Extra Sessions', 'points' => 50, 'icon' => 'fa-plus-circle', 'color' => '#10B981'],
    ['name' => 'Certificate of Excellence', 'points' => 200, 'icon' => 'fa-certificate', 'color' => '#F59E0B'],
    ['name' => 'Free Printing (10 pages)', 'points' => 25, 'icon' => 'fa-print', 'color' => '#8B5CF6'],
    ['name' => 'USB Flash Drive (8GB)', 'points' => 150, 'icon' => 'fa-usb', 'color' => '#EF4444'],
    ['name' => 'Coffee Voucher', 'points' => 75, 'icon' => 'fa-mug-hot', 'color' => '#D97706'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Student - Rewards & Points</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FB; font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E9EEF3; position: fixed; height: 100vh; padding: 28px 20px; display: flex; flex-direction: column; }
        .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .logo-icon { background: #3B82F6; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
        .logo-text { font-weight: 800; font-size: 20px; color: #0F172A; }
        .logo-text span { color: #3B82F6; }
        .nav-menu { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; color: #5B6E8C; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: #EFF6FF; color: #3B82F6; }
        .nav-item i { width: 20px; }
        .bottom-user { margin-top: auto; border-top: 1px solid #EDF2F7; padding-top: 20px; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .logout-icon { margin-left: auto; color: #EF4444; text-decoration: none; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 700; color: #0F172A; }
        .page-header p { color: #6C7A91; margin-top: 4px; }
        
        /* Points Card */
        .points-card { background: linear-gradient(135deg, #0F172A, #1E293B); border-radius: 24px; padding: 28px; margin-bottom: 28px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .points-info { flex: 1; }
        .points-label { font-size: 14px; opacity: 0.8; margin-bottom: 8px; }
        .points-value { font-size: 48px; font-weight: 800; }
        .points-value small { font-size: 16px; font-weight: 400; }
        .next-level { margin-top: 12px; font-size: 13px; opacity: 0.7; }
        .level-progress { width: 100%; max-width: 300px; margin-top: 10px; }
        .progress-bar { height: 8px; background: rgba(255,255,255,0.2); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #F59E0B; border-radius: 4px; width: 0%; }
        
        /* Rewards Grid */
        .rewards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .reward-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; padding: 20px; transition: all 0.2s; position: relative; }
        .reward-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
        .reward-card.disabled { opacity: 0.5; }
        .reward-icon { width: 60px; height: 60px; background: #EFF6FF; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px; }
        .reward-name { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .reward-points { font-size: 14px; color: #F59E0B; margin-bottom: 16px; }
        .reward-points i { margin-right: 4px; }
        .btn-redeem { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.2s; }
        .btn-redeem:hover { background: #2563EB; transform: translateY(-1px); }
        .btn-redeem.disabled { background: #CBD5E1; cursor: not-allowed; }
        
        /* Point History */
        .history-card { background: white; border-radius: 20px; border: 1px solid #EFF3F8; overflow: hidden; }
        .card-header { padding: 20px; border-bottom: 1px solid #F0F2F5; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #F1F5F9; }
        th { background: #F8FAFE; font-size: 12px; font-weight: 600; color: #6C7A91; text-transform: uppercase; }
        .badge-positive { background: #DCFCE7; color: #15803D; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; display: inline-block; }
        
        @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo-area"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div><div class="logo-text">CCS <span>Student</span></div></div>
    <div class="nav-menu">
        <a href="student_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="student_edit_profile.php" class="nav-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
        <a href="student_history.php" class="nav-item"><i class="fas fa-history"></i> History</a>
        <a href="student_reservation.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="student_rules.php" class="nav-item"><i class="fas fa-gavel"></i> Lab Rules</a>
        <a href="student_rewards.php" class="nav-item active"><i class="fas fa-gift"></i> Rewards/Points</a>
        <a href="student_notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications</a>
    </div>
    <div class="bottom-user">
        <div class="user-avatar"><?php echo strtoupper(substr($student_name, 0, 2)); ?></div>
        <div><h4><?php echo htmlspecialchars($student_name); ?></h4><p>Student</p></div>
        <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-gift"></i> Rewards & Points</h1>
        <p>Earn points by participating in lab activities and redeem exciting rewards!</p>
    </div>

    <!-- Points Card -->
    <div class="points-card">
        <div class="points-info">
            <div class="points-label"><i class="fas fa-star"></i> YOUR TOTAL POINTS</div>
            <div class="points-value">⭐ <?php echo number_format($current_points); ?> <small>pts</small></div>
            <?php 
            $next_level = $current_points < 100 ? 100 - $current_points : ($current_points < 200 ? 200 - $current_points : 0);
            $progress = min(($current_points / 200) * 100, 100);
            ?>
            <div class="next-level"><?php echo $next_level > 0 ? "$next_level more points to reach Gold level!" : "Congratulations! You've reached the highest level!"; ?></div>
            <div class="level-progress"><div class="progress-bar"><div class="progress-fill" style="width: <?php echo $progress; ?>%"></div></div></div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 14px; opacity: 0.8;">Current Level</div>
            <div style="font-size: 32px; font-weight: 800;"><?php echo $current_points >= 200 ? '🏆 Gold' : ($current_points >= 100 ? '🥈 Silver' : '🥉 Bronze'); ?></div>
        </div>
    </div>

    <!-- Rewards Grid -->
    <h3 style="margin-bottom: 16px;"><i class="fas fa-tags"></i> Available Rewards</h3>
    <div class="rewards-grid">
        <?php foreach ($rewards as $reward): 
            $can_redeem = $current_points >= $reward['points'];
        ?>
        <div class="reward-card <?php echo !$can_redeem ? 'disabled' : ''; ?>">
            <div class="reward-icon" style="background: <?php echo $reward['color']; ?>20; color: <?php echo $reward['color']; ?>;"><i class="fas <?php echo $reward['icon']; ?>"></i></div>
            <div class="reward-name"><?php echo $reward['name']; ?></div>
            <div class="reward-points"><i class="fas fa-star"></i> <?php echo $reward['points']; ?> points</div>
            <button class="btn-redeem <?php echo !$can_redeem ? 'disabled' : ''; ?>" <?php echo !$can_redeem ? 'disabled' : ''; ?> onclick="redeemReward('<?php echo $reward['name']; ?>', <?php echo $reward['points']; ?>)">
                <i class="fas fa-exchange-alt"></i> Redeem
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Point History -->
    <div class="history-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Your Point Transaction History</h3>
        </div>
        <div class="table-wrapper" style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Date</th><th>Points</th><th>Reason</th><th>Admin</th></tr>
                </thead>
                <tbody>
                    <?php if ($point_history && $point_history->num_rows > 0): ?>
                        <?php while ($row = $point_history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></td>
                            <td><span class="badge-positive"><?php echo $row['points_added'] > 0 ? '+' : ''; ?><?php echo $row['points_added']; ?> pts</span></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:40px;"><i class="fas fa-exchange-alt" style="font-size:36px; margin-bottom:12px; display:block; opacity:0.5;"></i>No point transactions yet</d></td>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function redeemReward(rewardName, points) {
    if (confirm(`Redeem ${rewardName} for ${points} points?`)) {
        alert(`Reward "${rewardName}" has been claimed! Please visit the CCS office to claim your reward.`);
        // Here you would normally send an AJAX request to record the redemption
    }
}
</script>
</body>
</html>