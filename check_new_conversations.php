<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// 驗證登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo json_encode(['new_conversations' => false]);
    exit;
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'] ?? 0;

// 檢查是否有新對話或新訊息
$stmt = $conn->prepare("
    SELECT c.id AS conversation_id,
           CASE WHEN c.user1_id = 0 THEN '已刪除的帳號' ELSE IFNULL(u1.username, '已刪除的帳號') END AS user1_name,
           CASE WHEN c.user2_id = 0 THEN '已刪除的帳號' ELSE IFNULL(u2.username, '已刪除的帳號') END AS user2_name,
           CASE WHEN c.user1_id = 0 THEN 'default.png' ELSE IFNULL(u1.avatar, 'default.png') END AS user1_avatar,
           CASE WHEN c.user2_id = 0 THEN 'default.png' ELSE IFNULL(u2.avatar, 'default.png') END AS user2_avatar,
           COUNT(m.id) AS unread_messages
    FROM conversations c
    LEFT JOIN users u1 ON c.user1_id = u1.id
    LEFT JOIN users u2 ON c.user2_id = u2.id
    LEFT JOIN messages m ON c.id = m.conversation_id AND m.is_read = 0 AND m.receiver_id = ?
    WHERE c.user1_id = ? OR c.user2_id = ?
    GROUP BY c.id
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = [
        'conversation_id' => $row['conversation_id'],
        'user1_name' => $row['user1_name'],
        'user2_name' => $row['user2_name'],
        'user1_avatar' => $row['user1_avatar'],
        'user2_avatar' => $row['user2_avatar'],
        'unread_messages' => $row['unread_messages']
    ];
}

echo json_encode(['new_conversations' => !empty($conversations), 'conversations' => $conversations]);
?>