<?php
session_start();
include 'db.php'; // 連接資料庫

if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo "<p>請先登入以發送訊息</p>";
    exit;
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$user_id = null;

if (isset($username)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;
}

if ($user_id) {
    $conversation_id = $_POST['conversation_id'] ?? null;
    $message_content = trim($_POST['content'] ?? '');
    $uploaded_image = $_FILES['image'] ?? null;

    if ($conversation_id && ($message_content !== '' || ($uploaded_image && $uploaded_image['error'] === UPLOAD_ERR_OK))) {
        // 根據 conversation_id 抓出接收者 ID
        $stmt = $conn->prepare("SELECT user1_id, user2_id FROM conversations WHERE id = ?");
        $stmt->bind_param("i", $conversation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $conversation = $result->fetch_assoc();

        if ($conversation) {
            $receiver_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];

            // 處理圖片上傳
            $image_path = null;
            if ($uploaded_image && $uploaded_image['error'] === UPLOAD_ERR_OK) {
                $image_name = uniqid('chat_', true) . '.' . pathinfo($uploaded_image['name'], PATHINFO_EXTENSION);
                $image_path = 'messages/' . $image_name;
                move_uploaded_file($uploaded_image['tmp_name'], $image_path);
            }

            // 插入訊息
            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, content, image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $conversation_id, $user_id, $receiver_id, $message_content, $image_path);
            $stmt->execute();
        }
    } else {
        header("Location: chat?conversation_id=$conversation_id&error=empty_message");
        exit;
    }
}

header("Location: chat?conversation_id=$conversation_id");
exit;
