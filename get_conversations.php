<?php
session_start();
include 'db.php'; // 連接資料庫

// 設定時區為台北時間
date_default_timezone_set('Asia/Taipei');

// 檢查使用者是否登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo json_encode([]); // 未登入，回傳空陣列
    exit;
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$user_id = null;

// 取得使用者資料
if (isset($username)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;
}

// 取得所有對話，排除自己
$conversations = [];
if ($user_id) {
    $stmt = $conn->prepare("
    SELECT DISTINCT c.id AS conversation_id,
        IF(c.user1_id = ?, u2.username, u1.username) AS username,
        IF(c.user1_id = ?, u2.avatar, u1.avatar) AS avatar,
        (SELECT m.content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
        (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_time,
        c.user1_id, c.user2_id
    FROM conversations c
    JOIN users u1 ON u1.id = c.user1_id
    JOIN users u2 ON u2.id = c.user2_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY last_message_time DESC
    ");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (empty($row['username'])) continue;

        // 取得訊息的時間
        $last_message_time = $row['last_message_time'];

        // 動態處理聯絡人
        $contact_id = ($row['user1_id'] == $user_id) ? $row['user2_id'] : $row['user1_id'];

        // 取得聯絡人資料
        $contact_stmt = $conn->prepare("SELECT username, avatar FROM users WHERE id = ?");
        $contact_stmt->bind_param("i", $contact_id);
        $contact_stmt->execute();
        $contact_result = $contact_stmt->get_result();
        $contact = $contact_result->fetch_assoc();

        // 動態設置聯絡人資訊
        $row['contact_username'] = $contact['username'];
        $row['contact_avatar'] = $contact['avatar'];

        // 不格式化時間，直接顯示原始時間
        $row['last_message_time'] = date('Y-m-d H:i:s', strtotime($last_message_time));

        // 將每一筆資料加入對話清單
        $conversations[] = $row;
    }
}

// 回傳對話資料
echo json_encode($conversations);
?>
