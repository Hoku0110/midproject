<?php
session_start();
include 'db.php';
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null; // 確保取得用戶 ID
// 確認是否提供物品 ID
if (!isset($_GET['item_id'])) {
    header("Location: index");
    exit();
}

$item_id = intval($_GET['item_id']);
$stmt = $conn->prepare("SELECT items.name, items.price, items.item_condition, items.image, items.description, users.username AS uploader, 
                        COALESCE(NULLIF(users.avatar, ''), 'default.png') AS uploader_avatar 
                        FROM items 
                        JOIN users ON items.user_id = users.id 
                        WHERE items.id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo "物品不存在！";
    exit();
}

$is_logged_in = isset($_SESSION['user']) || isset($_COOKIE['user']);

$is_favorited = false;

if ($is_logged_in) {
    $fav_stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND item_id = ?");
    $fav_stmt->bind_param("ii", $user_id, $item_id);
    $fav_stmt->execute();
    $fav_result = $fav_stmt->get_result();
    $is_favorited = $fav_result->num_rows > 0;
}

// 獲取商品的平均評分
$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating FROM reviews WHERE item_id = ?");
$rating_stmt->bind_param("i", $item_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$average_rating = $rating_result->fetch_assoc()['average_rating'] ?? 0;

// 獲取評論列表
$reviews_stmt = $conn->prepare("SELECT users.username, users.avatar, reviews.rating, reviews.content, reviews.created_at FROM reviews JOIN users ON reviews.user_id = users.id WHERE reviews.item_id = ? ORDER BY reviews.created_at DESC");
$reviews_stmt->bind_param("i", $item_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();


// 獲取賣家所有商品的平均評分
$seller_rating_stmt = $conn->prepare("
    SELECT AVG(r.rating) AS seller_average_rating
    FROM reviews r
    JOIN items i ON r.item_id = i.id
    WHERE i.user_id = (SELECT id FROM users WHERE username = ?)
");
$seller_rating_stmt->bind_param("s", $item['uploader']);
$seller_rating_stmt->execute();
$seller_rating_result = $seller_rating_stmt->get_result();
$seller_average_rating = $seller_rating_result->fetch_assoc()['seller_average_rating'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>物品詳情</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .star-rating {
            display: inline-block;
            font-size: 1.5rem;
            color: #FFD700; /* 金色 */
        }
        .review-content {
            white-space: pre-wrap; /* 保留換行符號 */
            word-wrap: break-word; /* 長字換行 */
        }
        .review-textarea {
            height: 50px; /* 調整高度 */
            resize: none; /* 禁止調整大小 */
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <!-- 左側：商品圖片與詳情 -->
        <div class="col-md-8">
            <div class="row">
                <!-- 商品圖片 -->
                <div class="col-md-6 text-center">
                    <img src="img/<?php echo htmlspecialchars($item['image']); ?>" 
                        alt="物品圖片" 
                        class="img-fluid" 
                        style="max-width: 100%; height: auto; max-height: 600px; object-fit: cover; cursor: pointer;"
                        data-bs-toggle="modal" 
                        data-bs-target="#imageModal"
                        onclick="openImageModal('img/<?php echo htmlspecialchars($item['image']); ?>')">
                </div>
                <!-- 商品詳情 -->
                <div class="col-md-6">
                    <h1 class="text-start"><?php echo htmlspecialchars($item['name']); ?></h1>
                    <p class="mt-3"><strong>描述：</strong> <?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    <p><strong>價格：</strong> $<?php echo htmlspecialchars($item['price']); ?></p>
                    <p><strong>狀況：</strong> <?php echo htmlspecialchars($item['item_condition']); ?></p>
                    <p><strong>賣家：</strong> 
                        <img src="profile_pictures/<?php echo htmlspecialchars($item['uploader_avatar']); ?>" 
                            alt="賣家頭像" 
                            class="rounded-circle" 
                            style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                        <?php echo htmlspecialchars($item['uploader']); ?>
                        <span class="ms-3">
                            <strong>商品總分：</strong>
                            <span class="star-rating">
                                <?php
                                $full_stars = floor($seller_average_rating);
                                $half_star = ($seller_average_rating - $full_stars) >= 0.5 ? 1 : 0;
                                for ($i = 0; $i < $full_stars; $i++) echo "★";
                                if ($half_star) echo "★";
                                for ($i = $full_stars + $half_star; $i < 5; $i++) echo "☆";
                                ?>
                            </span>
                            (<?php echo number_format($seller_average_rating, 1); ?> / 5)
                        </span>
                    </p>
                    <?php if ($is_logged_in): ?>
                        <form id="favorite-form" style="display: inline;">
                            <input type="hidden" id="item_id" value="<?php echo $item_id; ?>">
                            <button type="button" id="favorite-button" class="btn <?php echo $is_favorited ? 'btn-secondary' : 'btn-primary'; ?>">
                                <?php echo $is_favorited ? '已收藏' : '收藏'; ?>
                            </button>
                        </form>
                        <script>
                            document.getElementById('favorite-button').addEventListener('click', function () {
                                const itemId = document.getElementById('item_id').value;
                                const button = this;

                                fetch('favorite.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `item_id=${itemId}`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // 更新按鈕狀態
                                        if (data.is_favorited) {
                                            button.classList.remove('btn-primary');
                                            button.classList.add('btn-secondary');
                                            button.textContent = '已收藏';
                                        } else {
                                            button.classList.remove('btn-secondary');
                                            button.classList.add('btn-primary');
                                            button.textContent = '收藏';
                                        }
                                    } else {
                                        alert('操作失敗，請稍後再試！');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('發生錯誤，請稍後再試！');
                                });
                            });
                        </script>
                        <?php if ($item['uploader'] !== ($_SESSION['user'] ?? $_COOKIE['user'])): ?>
                            <a href="chat.php?user=<?php echo urlencode($item['uploader']); ?>" class="btn btn-secondary">與賣家聊天</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-danger">請登入以收藏或與賣家聊天。</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 評分區 -->
            <div class="mt-4">
                <h3>商品總評價</h3>
                <p>平均評分：
                    <span class="star-rating">
                        <?php
                        $full_stars = floor($average_rating);
                        $half_star = ($average_rating - $full_stars) >= 0.5 ? 1 : 0;
                        for ($i = 0; $i < $full_stars; $i++) echo "★";
                        if ($half_star) echo "★";
                        for ($i = $full_stars + $half_star; $i < 5; $i++) echo "☆";
                        ?>
                    </span>
                    (<?php echo number_format($average_rating, 1); ?> / 5)
                </p>
            </div>

            <!-- 評論區 -->
            <div class="mt-4">
                <h4>撰寫評論</h4>
                <?php if ($is_logged_in): ?>
                    <form action="submit_review.php" method="POST" class="d-flex align-items-start">
                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                        <div class="me-3">
                            <label for="rating" class="form-label">評分</label>
                            <select name="rating" id="rating" class="form-select" required>
                                <option value="5">5 星</option>
                                <option value="4">4 星</option>
                                <option value="3">3 星</option>
                                <option value="2">2 星</option>
                                <option value="1">1 星</option>
                            </select>
                        </div>
                        <div class="flex-grow-1 me-3">
                            <label for="review_content" class="form-label">評論內容</label>
                            <textarea name="review_content" id="review_content" class="form-control review-textarea" placeholder="按 Shift+Enter 換行" required></textarea>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success mt-4">提交評論</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-danger">請登入以撰寫評論。</p>
                <?php endif; ?>
                
                <h4 class="mt-4">評論列表</h4>
                <!-- 篩選按鈕 -->
                <div class="mb-3">
                    <button class="btn btn-outline-primary filter-btn" data-rating="5">5 星</button>
                    <button class="btn btn-outline-primary filter-btn" data-rating="4">4 星</button>
                    <button class="btn btn-outline-primary filter-btn" data-rating="3">3 星</button>
                    <button class="btn btn-outline-primary filter-btn" data-rating="2">2 星</button>
                    <button class="btn btn-outline-primary filter-btn" data-rating="1">1 星</button>
                    <button class="btn btn-outline-secondary filter-btn" data-rating="all">全部</button>
                </div>
                <div class="list-group">
                    <div id="no-results" class="text-center text-muted my-3" style="display: none;">
                        沒有找到符合條件的內容
                    </div>
                    <?php if ($reviews_result->num_rows > 0): ?>
                        <?php while ($review = $reviews_result->fetch_assoc()): ?>
                            <div class="card mb-3 review-item" data-rating="<?php echo htmlspecialchars($review['rating']); ?>">
                                <div class="card-body d-flex">
                                    <!-- 左側：頭像 -->
                                    <div class="me-3 text-center">
                                        <img src="profile_pictures/<?php echo htmlspecialchars($review['avatar'] ?? 'default.png'); ?>" 
                                            alt="使用者頭像" 
                                            class="rounded-circle" 
                                            style="width: 60px; height: 60px; object-fit: cover;">
                                    </div>
                                    <!-- 中間：名稱與留言 -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($review['username']); ?></p>
                                            <span class="ms-2 text-muted">
                                                <?php echo intval($review['rating']); ?> 星
                                            </span>
                                            <small class="text-muted ms-2"><?php echo htmlspecialchars($review['created_at']); ?></small>
                                        </div>
                                        <p class="review-content mb-2"><?php echo htmlspecialchars($review['content']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center text-muted">
                                目前尚無評論。
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 右側：其他商品 -->
        <div class="col-md-4">
            <h4>其他商品</h4>
            <div class="list-group">
                <?php
                $other_items_stmt = $conn->prepare("SELECT id, name, image, price FROM items WHERE id != ? AND is_published = 1 ORDER BY created_at DESC LIMIT 5");
                $other_items_stmt->bind_param("i", $item_id);
                $other_items_stmt->execute();
                $other_items_result = $other_items_stmt->get_result();
                while ($other_item = $other_items_result->fetch_assoc()):
                ?>
                    <a href="item_details.php?item_id=<?php echo $other_item['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <img src="img/<?php echo htmlspecialchars($other_item['image']); ?>" alt="商品圖片" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div>
                                <p class="mb-1 fw-bold"><?php echo htmlspecialchars($other_item['name']); ?></p>
                                <small class="text-muted">價格：$<?php echo htmlspecialchars($other_item['price']); ?></small>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- 商品圖片 Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">商品圖片</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="商品圖片" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const reviewItems = document.querySelectorAll('.review-item');
        const noResults = document.getElementById('no-results');

        filterButtons.forEach(button => {
            button.addEventListener('click', function () {
                const rating = this.getAttribute('data-rating');
                let hasResults = false;

                reviewItems.forEach(item => {
                    if (rating === 'all' || item.getAttribute('data-rating') === rating) {
                        item.style.display = 'block'; // 顯示符合條件的評論
                        hasResults = true;
                    } else {
                        item.style.display = 'none'; // 隱藏不符合條件的評論
                    }
                });

                noResults.style.display = hasResults ? 'none' : 'block';
            });
        });
    });

    function openImageModal(imageSrc) {
        const modalImage = document.getElementById('modalImage');
        modalImage.src = imageSrc; // 設置 Modal 中的圖片來源
    }
</script>
</body>
</html>
