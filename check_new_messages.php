<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// 驗證登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo json_encode(['new_messages' => 0]);
    exit;
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'] ?? 0;

$conversation_id = $_GET['conversation_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count 
    FROM messages 
    WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
");
$stmt->bind_param("ii", $conversation_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['new_messages' => $row['unread_count']]);
?>
