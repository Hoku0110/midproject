<?php
session_start();
include 'db.php';

// 確保使用者已經登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $conn->begin_transaction();

    try {
        setcookie('user', '', time() - 3600, '/'); // 清除 cookie

        // 將 conversations 表中的 user1_id 或 user2_id 設置為 NULL
        $stmt = $conn->prepare("UPDATE conversations SET user1_id = NULL WHERE user1_id = ?");
        if (!$stmt) throw new Exception("更新 conversations.user1_id 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE conversations SET user2_id = NULL WHERE user2_id = ?");
        if (!$stmt) throw new Exception("更新 conversations.user2_id 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // 將 messages 表中的 sender_id 和 receiver_id 設置為 NULL
        $stmt = $conn->prepare("UPDATE messages SET sender_id = NULL WHERE sender_id = ?");
        if (!$stmt) throw new Exception("更新 messages.sender_id 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE messages SET receiver_id = NULL WHERE receiver_id = ?");
        if (!$stmt) throw new Exception("更新 messages.receiver_id 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // 刪除收藏紀錄
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ?");
        if (!$stmt) throw new Exception("刪除 favorites 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // 刪除商品
        $stmt = $conn->prepare("DELETE FROM items WHERE user_id = ?");
        if (!$stmt) throw new Exception("刪除 items 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // 最後刪除使用者
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) throw new Exception("刪除 users 錯誤：" . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // 提交
        $conn->commit();

        // 清除登入資訊
        session_unset();
        session_destroy();
        header("Location: login");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "<div style='color: red;'>發生錯誤：{$e->getMessage()}</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>刪除帳號</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <h2 class="mt-5">確認刪除帳號</h2>
    <p class="text-danger">您確定要刪除帳號嗎？所有資料將永久刪除且無法復原。</p>
    <form method="POST">
        <button type="submit" name="confirm_delete" class="btn btn-danger">永久刪除帳號</button>
        <a href="profile" class="btn btn-secondary">取消</a>
    </form>
</div>
</body>
</html>
