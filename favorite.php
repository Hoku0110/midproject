<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => '未登入']);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $item_id = intval($_POST['item_id']);

    // 檢查是否已收藏
    $stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND item_id = ?");
    $stmt->bind_param("ii", $user_id, $item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 已收藏，執行取消收藏
        $delete_stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND item_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $item_id);
        $delete_stmt->execute();
        echo json_encode(['success' => true, 'is_favorited' => false]);
    } else {
        // 未收藏，執行新增收藏
        $insert_stmt = $conn->prepare("INSERT INTO favorites (user_id, item_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $item_id);
        $insert_stmt->execute();
        echo json_encode(['success' => true, 'is_favorited' => true]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => '無效的請求']);