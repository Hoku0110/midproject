<?php
session_start();
include 'db.php';

// 確保用戶已登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    die("未登入");
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
if (!$stmt) {
    die("SQL 錯誤：無法準備查詢");
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];

// 初始化更新項目陣列
$updatedItems = [];

// 處理頭像上傳
if (!empty($_FILES["avatar"]["name"])) {
    $target_dir = "profile_pictures/";
    $avatar_name = $user_id . "_" . basename($_FILES["avatar"]["name"]);
    $target_file = $target_dir . $avatar_name;

    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if (!$stmt) {
            die("SQL 錯誤：無法準備查詢");
        }
        $stmt->bind_param("si", $avatar_name, $user_id);
        $stmt->execute();

        // 記錄更新的項目
        $updatedItems[] = "頭像";
    } else {
        die("頭像上傳失敗");
    }
}

// 更新帳號名稱
if (!empty($_POST['username'])) {
    $new_username = $_POST['username'];
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    if (!$stmt) {
        die("SQL 錯誤：無法準備查詢");
    }
    $stmt->bind_param("si", $new_username, $user_id);
    $stmt->execute();
    $_SESSION['user'] = $new_username;

    // 記錄更新的項目
    $updatedItems[] = "帳戶名稱";
}

// 更新密碼
if (!empty($_POST['password']) && $_POST['password'] === $_POST['confirm_password']) {
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if (!$stmt) {
        die("SQL 錯誤：無法準備查詢");
    }
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();

    // 記錄更新的項目
    $updatedItems[] = "密碼";
} elseif (!empty($_POST['password']) && $_POST['password'] !== $_POST['confirm_password']) {
    die("密碼不匹配，請重新確認");
}

// 將更新項目傳遞到 profile.php
if (!empty($updatedItems)) {
    $updatedText = implode(", ", $updatedItems);
    header("Location: profile.php?updated=" . urlencode($updatedText));
    exit();
}

// 若沒有更新任何內容，跳轉到 profile.php
header("Location: profile.php");
exit();
?>
