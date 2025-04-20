<?php
session_start();
if (isset($_SESSION['user']) || isset($_COOKIE['user'])) {
    header("Location: admin");
    exit();
}

// 連接資料庫
$servername = "localhost";
$username = "root";
$password = "";
$database = "second_hand_market";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("資料庫連接失敗: " . $conn->connect_error);
}

// 獲取搜尋參數
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// 查詢商品
$query = "SELECT items.*, users.avatar AS user_avatar, users.username AS uploader_name 
          FROM items 
          JOIN users ON items.user_id = users.id 
          WHERE items.is_published = 1";

if (!empty($search)) {
    // 搜尋商品名稱或描述中包含搜尋字串的商品
    $query .= " AND (items.name LIKE ? OR items.description LIKE ?)";
}

$query .= " ORDER BY items.ad_priority DESC, items.created_at DESC LIMIT 10";

$stmt = $conn->prepare($query);

if (!empty($search)) {
    $searchTerm = '%' . $search . '%';
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
}

if (!$stmt->execute()) {
    die("查詢執行失敗: " . $stmt->error);
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>EasyTrade 二手交易平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .navbar { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .search-bar { width: 400px; }
        .card { border-radius: 15px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .card:hover { transform: scale(1.05); }
        .card img { border-top-left-radius: 15px; border-top-right-radius: 15px; height: 200px; object-fit: cover; }
        .card-body { text-align: center; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4 text-start">
                        <div class="card position-relative">
                            <?php if ($row['ad_priority'] > 0): ?>
                                <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2">廣告</span>
                            <?php endif; ?>
                            <img src="img/<?php echo htmlspecialchars($row['image']); ?>" class="card-img-top img-fluid" style="height: 200px; object-fit: cover;" alt="商品圖片">
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
                            <!-- 如果需要收藏按鈕，可以取消註解以下代碼 -->
                            <!--
                            <button class="btn btn-outline-warning position-absolute top-0 end-0 m-2" data-bs-toggle="modal" data-bs-target="#loginModal">⭐</button>
                            -->
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted">無符合條件的商品</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">需要登入</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">請先登入後才能收藏商品。</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                </div>
            </div>
        </div>
    </div>
                
    <!-- 回到頂部按鈕 -->
    <button id="backToTop" class="btn btn-primary" style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 1000;">
        ↑ 回到頂部
    </button>

    <script>
        // 監聽滾動事件
        window.addEventListener('scroll', function () {
            const backToTopButton = document.getElementById('backToTop');
            if (window.scrollY > 200) { // 滾動超過 200px 顯示按鈕
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        // 點擊按鈕回到頂部
        document.getElementById('backToTop').addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth' // 平滑滾動
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
