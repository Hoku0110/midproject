<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'];
$item_id = intval($_POST['item_id']);
$rating = intval($_POST['rating']);
$review_content = trim($_POST['review_content']);

if ($rating < 1 || $rating > 5 || empty($review_content)) {
    echo "無效的輸入！";
    exit();
}

$stmt = $conn->prepare("INSERT INTO reviews (item_id, user_id, rating, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $item_id, $user_id, $rating, $review_content);

if ($stmt->execute()) {
    header("Location: item_details.php?item_id=$item_id");
} else {
    echo "提交評論失敗！";
}
?>
