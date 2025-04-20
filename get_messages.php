<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo json_encode([]);
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
    SELECT m.content, m.created_at, m.sender_id, u.username, u.avatar, m.image 
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'content' => htmlspecialchars($row['content']),
        'created_at' => date("H:i", strtotime($row['created_at'])),
        'sender_id' => $row['sender_id'],
        'username' => $row['username'],
        'avatar' => $row['avatar'],
        'image' => !empty($row['image']) ? htmlspecialchars($row['image']) : null
    ];
}

// ✅ 更新已讀狀態
$update = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
$update->bind_param("ii", $conversation_id, $user_id);
$update->execute();

echo json_encode($messages);
?>
