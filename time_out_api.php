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

if (!isset($data['session_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit();
}

$session_id = intval($data['session_id']);

$update = $conn->query("UPDATE sit_in_sessions SET status = 'completed', time_out = NOW() WHERE id = $session_id");

if ($update) {
    echo json_encode(['success' => true, 'message' => 'Student timed out successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to time out student']);
}
?>