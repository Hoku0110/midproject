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

// 取得使用者 ID 及頭像
$user_id = null;
$user_avatar = 'profile_pictures/default_avatar.png';
if ($loggedIn) {
    $stmt = $conn->prepare("SELECT id, avatar FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;

    // 確保將 user_id 存入 $_SESSION
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $user_id;
    }

    if (!empty($user['avatar'])) {
        $user_avatar = 'profile_pictures/' . htmlspecialchars($user['avatar']);
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$query = "SELECT items.*, users.avatar AS user_avatar, users.username AS uploader_name 
          FROM items 
          JOIN users ON items.user_id = users.id 
          WHERE items.is_published = 1";
if (!empty($search)) {
    $query .= " AND items.name LIKE '%" . $conn->real_escape_string($search) . "%'";
}
// 根據 ad_priority 排序，支付金額越高越前面，然後再按建立時間排序
$query .= " ORDER BY items.ad_priority DESC, items.created_at DESC LIMIT 10";
$result = $conn->query($query);

// 取得已收藏的商品
$favorited_items = [];
if ($loggedIn && $user_id) {
    $fav_query = "SELECT item_id FROM favorites WHERE user_id = ?";
    $stmt = $conn->prepare($fav_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $fav_result = $stmt->get_result();
    while ($row = $fav_result->fetch_assoc()) {
        $favorited_items[] = $row['item_id'];
    }
}

// 包含 navbar.php

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyTrade 二手交易平台</title>
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleFavorite(itemId, button) {
            fetch("favorite.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "item_id=" + itemId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新按鈕狀態
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
                    alert("操作失敗，請稍後再試");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("發生錯誤，請稍後再試");
            });
        }
    </script>

    <style>
        

        .chat-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1050;
        }
        .contact-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .contact-name {
            font-weight: bold;
        }
        

        .message-preview.read {
            color: gray;
            font-weight: normal;
        }

        .message-preview.unread {
            color: black;
            font-weight: bold;
        }
        #backToTop {
            position: fixed;
            top: 20px; /* 距離底部 20px */
            left: 50%; /* 水平置中 */
            transform: translateX(-50%); /* 將按鈕水平置中 */
            z-index: 1000; /* 確保按鈕在最上層 */
            width: 50px; /* 固定寬度 */
            height: 50px; /* 固定高度 */
            padding: 0; /* 移除內邊距 */
            font-size: 18px; /* 調整字體大小 */
            text-align: center; /* 文字置中 */
            border-radius: 50%; /* 圓形按鈕 */
            background-color: gray;
        }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>


    <!-- 聊天按鈕 (已移至左下角) -->
    <a href="chat" class="btn btn-primary chat-button">
        💬
    </a>

    <!-- 回到頂部按鈕 -->
    <button id="backToTop" class="btn btn-primary" style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 1000;">
        ↑
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
                            <?php if ($loggedIn): ?>
                                <button class="btn <?php echo in_array($row['id'], $favorited_items) ? 'btn-warning' : 'btn-outline-warning'; ?> position-absolute top-0 end-0 m-2"
                                    onclick="toggleFavorite(<?php echo $row['id']; ?>, this)">
                                    <?php echo in_array($row['id'], $favorited_items) ? '✅ 已收藏' : '⭐ 收藏'; ?>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-warning position-absolute top-0 end-0 m-2" data-bs-toggle="modal" data-bs-target="#loginModal">⭐ 收藏</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted">無符合條件的商品</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
