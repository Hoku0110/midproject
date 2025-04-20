<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// 驗證登入
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

if (isset($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];

    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<p>您無權查看此對話</p>";
        exit;
    }
}

// 查詢所有其他使用者
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id != ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
?>