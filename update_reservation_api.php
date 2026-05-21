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

if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$id = intval($data['id']);
$status = $conn->real_escape_string($data['status']);

$update = $conn->query("UPDATE reservations SET status = '$status' WHERE id = $id");

if ($update) {
    echo json_encode(['success' => true, 'message' => "Reservation $status successfully"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update reservation']);
}
?>