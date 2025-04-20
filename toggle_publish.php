<?php
session_start();
include 'db.php';

// 檢查是否登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: login");
    exit;
}

$loggedIn = isset($_SESSION['user']) || isset($_COOKIE['user']);
$username = $_SESSION['user'] ?? $_COOKIE['user'] ?? null;

// 確認商品 ID 是否存在
if (!isset($_GET['id'])) {
    header("Location: my_items");
    exit;
}

$item_id = intval($_GET['id']);

// 確認商品是否屬於目前登入的使用者
$stmt = $conn->prepare("SELECT is_published FROM items WHERE id = ? AND user_id = (SELECT id FROM users WHERE username = ?)");
$stmt->bind_param("is", $item_id, $username);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    header("Location: my_items");
    exit;
}

// 切換上架狀態
$new_status = $item['is_published'] ? 0 : 1;
$update_stmt = $conn->prepare("UPDATE items SET is_published = ? WHERE id = ?");
$update_stmt->bind_param("ii", $new_status, $item_id);
$update_stmt->execute();

header("Location: my_items");
exit;
?>