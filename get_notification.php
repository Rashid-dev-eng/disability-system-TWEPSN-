<?php
session_start();
require 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
$count = 0;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    echo json_encode(['success' => true, 'count' => $count]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0]);
}
?>