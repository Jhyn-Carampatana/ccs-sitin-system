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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = $conn->query("SELECT * FROM students WHERE id = $id");
if ($query && $query->num_rows > 0) {
    echo json_encode(['success' => true, 'student' => $query->fetch_assoc()]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}
?>s