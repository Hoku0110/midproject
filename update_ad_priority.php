<?php
session_start();
include 'db.php';

// 檢查是否登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: index");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id']);
    $ad_priority = floatval($_POST['ad_priority']);

    // 更新廣告優先級
    $stmt = $conn->prepare("UPDATE items SET ad_priority = ? WHERE id = ?");
    $stmt->bind_param("di", $ad_priority, $item_id);

    if ($stmt->execute()) {
        header("Location: my_items.php?success=1");
    } else {
        header("Location: my_items.php?error=1");
    }
    $stmt->close();
}
$conn->close();
?>