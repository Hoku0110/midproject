<?php
session_start();
include 'db.php';

// 確認用戶是否已登入
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
if (!$user_id) {
    header("Location: index.php");
    exit();
}

// 獲取用戶收藏的商品
$stmt = $conn->prepare("
    SELECT items.id, items.name, items.price, items.item_condition, items.image, users.username AS uploader_name 
    FROM favorites 
    JOIN items ON favorites.item_id = items.id 
    JOIN users ON items.user_id = users.id 
    WHERE favorites.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>我的收藏</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .card:hover { transform: scale(1.05); }
        .card img { border-top-left-radius: 15px; border-top-right-radius: 15px; height: 200px; object-fit: cover; }
        .card-body { text-align: center; }
        .favorite-btn { position: absolute; top: 10px; right: 10px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">我的收藏</h1>
        <div class="row justify-content-center mt-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card position-relative">
                            <img src="img/<?php echo htmlspecialchars($row['image']); ?>" class="card-img-top" alt="商品圖片">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                                <p class="card-text text-muted">價格：<strong class="text-danger">$<?php echo number_format($row['price'], 2); ?></strong></p>
                                <p class="card-text text-muted">狀態：<?php echo htmlspecialchars($row['item_condition']); ?></p>
                                <div class="d-flex align-items-center mt-3">
                                    <img src="profile_pictures/<?php echo htmlspecialchars(!empty($row['user_avatar']) ? $row['user_avatar'] : 'default.png'); ?>" 
                                         alt="上傳者頭像" class="rounded-circle me-2" style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #ddd;">
                                    <span class="text-muted"><?php echo htmlspecialchars($row['uploader_name']); ?></span>
                                </div>
                                <a href="item_details.php?item_id=<?php echo $row['id']; ?>" class="btn btn-primary mt-3">查看詳情</a>
                            </div>
                            <!-- 收藏按鈕 -->
                            <button class="btn btn-<?php echo in_array($row['id'], getUserFavorites($conn, $user_id)) ? 'warning' : 'outline-warning'; ?> favorite-btn"
                                    onclick="toggleFavorite(<?php echo $row['id']; ?>, this)">
                                <?php echo in_array($row['id'], getUserFavorites($conn, $user_id)) ? '✅ 已收藏' : '⭐ 收藏'; ?>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted">您尚未收藏任何商品。</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleFavorite(itemId, button) {
            fetch('favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'item_id=' + itemId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.is_favorited) {
                        button.classList.remove('btn-outline-warning');
                        button.classList.add('btn-warning');
                        button.textContent = '✅ 已收藏';
                    } else {
                        button.classList.remove('btn-warning');
                        button.classList.add('btn-outline-warning');
                        button.textContent = '⭐ 收藏';
                    }
                } else {
                    alert(data.message || '操作失敗，請稍後再試');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();

// 獲取用戶收藏的商品 ID
function getUserFavorites($conn, $user_id) {
    $favorites = [];
    $stmt = $conn->prepare("SELECT item_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $favorites[] = $row['item_id'];
    }
    return $favorites;
}
?>