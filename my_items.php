<?php
session_start();
include 'db.php';

// 檢查是否登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: index");
    exit;
}

$loggedIn = isset($_SESSION['user']) || isset($_COOKIE['user']);
$username = $_SESSION['user'] ?? $_COOKIE['user'] ?? null;

// 取得使用者 ID
$user_id = null;
if ($loggedIn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;
}

// 確認使用者 ID 是否有效
if (!$user_id) {
    header("Location: index");
    exit;
}

// 取得使用者上架的商品
$query = "SELECT name, price, item_condition, image, id, is_published, ad_priority FROM items WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 重置結果指標，確保可以正確迴圈
$result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>我的上架商品 - EasyTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4">我的上架商品</h1>
        <?php if ($result->num_rows > 0): ?>
            <div class="row justify-content-center">
                <?php
                // 重置結果指標，重新迴圈顯示商品
                $result->data_seek(0);
                while ($row = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4 text-start">
                        <div class="card position-relative">
                            <img src="<?php echo file_exists('img/' . ($row['image'] ?? '')) ? 'img/' . htmlspecialchars($row['image']) : 'img/default.png'; ?>" 
                                 class="card-img-top img-fluid" style="height: 200px; object-fit: cover;" alt="商品圖片">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['name'] ?? '未命名商品'); ?></h5>
                                <p class="card-text text-muted">價格：<strong class="text-danger">$<?php echo number_format($row['price'] ?? 0, 2); ?></strong></p>
                                <p class="card-text text-muted">狀態：<?php echo htmlspecialchars($row['item_condition'] ?? '未知'); ?></p>
                                <p class="card-text text-muted">目前廣告優先級：<strong><?php echo $row['ad_priority']; ?></strong></p>
                                
                                <!-- 表單：提升廣告優先級 -->
                                <form method="POST" action="update_ad_priority.php">
                                    <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                    <div class="mb-3">
                                        <label for="ad_priority_<?php echo $row['id']; ?>" class="form-label">提升廣告優先級 (支付金額)</label>
                                        <input type="number" class="form-control" id="ad_priority_<?php echo $row['id']; ?>" name="ad_priority" value="<?php echo $row['ad_priority']; ?>" step="0.01" min="0">
                                    </div>
                                    <button type="submit" class="btn btn-success">提交</button>
                                </form>
                                <a href="edit_item?id=<?php echo $row['id']; ?>" class="btn btn-primary">編輯</a>
                                <a href="delete_item.php?id=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('確定要刪除這個商品嗎？');">刪除</a>
                                <a href="toggle_publish.php?id=<?php echo $row['id']; ?>" class="btn btn-<?php echo $row['is_published'] ? 'warning' : 'success'; ?>">
                                    <?php echo $row['is_published'] ? '下架' : '上架'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">目前沒有上架的商品。</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php $conn->close(); ?>