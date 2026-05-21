<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "jhyn");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['student_id']) || !isset($data['points']) || !isset($data['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$student_id = $conn->real_escape_string($data['student_id']);
$points = intval($data['points']);
$reason = $conn->real_escape_string($data['reason']);
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Check if student exists$check = $conn->query("SELECT id_number FROM students WHERE id_number = '$student_id'");
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}

// Update points
$update = $conn->query("UPDATE students SET total_points = total_points + $points WHERE id_number = '$student_id'");

if ($update) {
    // Record history
    $insert = $conn->query("INSERT INTO point_history (student_id, points_added, reason, admin_name) 
                             VALUES ('$student_id', $points, '$reason', '$admin_name')");
    echo json_encode(['success' => true, 'message' => 'Points added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add points']);
}
?>