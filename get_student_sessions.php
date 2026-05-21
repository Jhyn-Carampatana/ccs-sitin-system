<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$student_id = $_GET['id'] ?? '';

if (empty($student_id)) {
    echo json_encode(['error' => 'No student ID provided']);
    exit();
}

$student_id = $conn->real_escape_string($student_id);

// Check if student exists
$student_check = $conn->query("SELECT id_number, first_name, last_name FROM students WHERE id_number = '$student_id'");
if ($student_check->num_rows == 0) {
    echo json_encode(['error' => 'Student not found']);
    exit();
}

$student_info = $student_check->fetch_assoc();

// Get student totals
$student = $conn->query("SELECT 
    IFNULL(total_hours, 0) as total_hours, 
    IFNULL(total_points, 0) as total_points,
    sessions
    FROM students WHERE id_number='$student_id'")->fetch_assoc();

// Get all sessions for this student
$sessions_query = $conn->query("SELECT 
    id,
    DATE(created_at) as date,
    DATE_FORMAT(created_at, '%M %d, %Y') as formatted_date,
    purpose, 
    laboratory, 
    status, 
    TIME_FORMAT(time_in, '%h:%i %p') as time_in,
    TIME_FORMAT(time_out, '%h:%i %p') as time_out,
    IFNULL(points_earned, 0) as points_earned
    FROM sit_in_sessions 
    WHERE id_number='$student_id' 
    ORDER BY created_at DESC 
    LIMIT 10");

$session_list = [];
$completed = 0;
$total = 0;

while ($s = $sessions_query->fetch_assoc()) {
    $session_list[] = [
        'id' => $s['id'],
        'date' => $s['date'],
        'formatted_date' => $s['formatted_date'],
        'purpose' => $s['purpose'],
        'laboratory' => $s['laboratory'],
        'status' => $s['status'],
        'status_label' => $s['status'] == 'completed' ? 'Completed' : 'Active',
        'time_in' => $s['time_in'],
        'time_out' => $s['time_out'] ?? 'Still Active',
        'points_earned' => intval($s['points_earned'])
    ];
    
    $total++;
    if ($s['status'] == 'completed') $completed++;
}

// Get weekly activity data (last 4 weeks)
$weekly = [];
$week_labels = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-{$i} weeks", strtotime('monday this week')));
    $week_end = date('Y-m-d', strtotime("+6 days", strtotime($week_start)));
    $week_label = $i == 0 ? 'This Week' : ($i == 1 ? 'Last Week' : 'Week ' . (4 - $i));
    $week_labels[] = $week_label;
    
    $count_result = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions 
        WHERE id_number='$student_id' 
        AND DATE(created_at) BETWEEN '$week_start' AND '$week_end'");
    $count = $count_result ? $count_result->fetch_assoc()['c'] : 0;
    $weekly[] = intval($count);
}

// Get task/purpose breakdown
$task_breakdown = [];
$purpose_query = $conn->query("SELECT purpose, COUNT(*) as count FROM sit_in_sessions 
    WHERE id_number='$student_id' 
    GROUP BY purpose 
    ORDER BY count DESC");
while ($row = $purpose_query->fetch_assoc()) {
    $task_breakdown[$row['purpose']] = intval($row['count']);
}

// Get lab preference
$lab_preference = ['name' => 'None', 'count' => 0];
$lab_query = $conn->query("SELECT laboratory, COUNT(*) as count FROM sit_in_sessions 
    WHERE id_number='$student_id' 
    GROUP BY laboratory 
    ORDER BY count DESC 
    LIMIT 1");
if ($lab_query && $lab_query->num_rows > 0) {
    $fav_lab = $lab_query->fetch_assoc();
    $lab_preference = ['name' => $fav_lab['laboratory'], 'count' => intval($fav_lab['count'])];
}

// Calculate completion rate
$completion_rate = $total > 0 ? round(($completed / $total) * 100) : 0;

// Prepare response
$response = [
    'success' => true,
    'student' => [
        'id' => $student_id,
        'name' => $student_info['first_name'] . ' ' . $student_info['last_name'],
        'total_hours' => round(floatval($student['total_hours'] ?? 0), 1),
        'total_points' => intval($student['total_points'] ?? 0),
        'total_sessions' => intval($student['sessions'] ?? $total),
        'completed_sessions' => $completed,
        'active_sessions' => $total - $completed,
        'completion_rate' => $completion_rate,
        'favorite_lab' => $lab_preference
    ],
    'sessions' => $session_list,
    'weekly_activity' => [
        'labels' => $week_labels,
        'data' => $weekly
    ],
    'task_breakdown' => $task_breakdown,
    'stats' => [
        'most_common_purpose' => !empty($task_breakdown) ? array_key_first($task_breakdown) : 'None'
    ]
];

$conn->close();
echo json_encode($response);
?>