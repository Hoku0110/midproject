<?php
session_start();
$host = "localhost";
$dbname = "second_hand_market";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("連線失敗：" . $conn->connect_error);
}
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: index.php");
    exit;
}

$loggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION["user_id"] ?? null;
$search = $_GET['search'] ?? '';
$uploadSuccess = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_id) {
    $name = $_POST["name"] ?? '';
    $description = $_POST["description"] ?? '';
    $price = $_POST["price"] ?? 0;
    $item_condition = $_POST["item_condition"] ?? '';
    $image = $_FILES["image"]["name"] ?? '';

    if (!empty($image)) {
        $target_dir = "img/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($image);
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
    }

    $stmt = $conn->prepare("INSERT INTO items (name, price, item_condition, image, description, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsssi", $name, $price, $item_condition, $image, $description, $user_id);
    $stmt->execute();
    $stmt->close();

    $uploadSuccess = true;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>上傳商品</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa; /* 背景色保持柔和 */
        }

        h2 {
            font-weight: bold;
        }

        form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-control, .form-select {
            border-radius: 8px; /* 圓角 */
        }

        .btn-primary {
            border-radius: 20px; /* 圓角按鈕 */
        }

        .shadow {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* 添加陰影 */
        }
    </style>
</head>
<body>

<!-- ✅ 你的自訂 navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="img/logo.png" width="100" height="100" alt="平台Logo" class="me-2">
            <span class="fw-bold text-dark">EasyTrade 二手交易</span>
        </a>
        <form class="d-flex mx-auto search-bar" role="search" method="GET" action="admin.php">
            <input class="form-control me-2" type="search" name="search" placeholder="搜尋商品..." aria-label="Search" value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-outline-success" type="submit">搜尋</button>
        </form>

        <?php if ($loggedIn): ?>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="profile_pictures/<?php echo htmlspecialchars(!empty($user['avatar']) ? $user['avatar'] : "default.png"); ?>" alt="使用者頭像" class="rounded-circle" width="40" height="40">
                    <?php echo htmlspecialchars($_SESSION['user'] ?? $_COOKIE['user'] ?? ''); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php">👤 個人資料</a></li>
                    <li><a class="dropdown-item" href="favorites.php">⭐ 我的收藏</a></li>
                    <li><a class="dropdown-item" href="my_items.php">📦 我的上架商品</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">🚪 登出</a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>

<!-- 上傳表單 -->
<div class="container mt-5">
    <h2 class="text-center mb-4">上傳商品</h2>

    <?php if ($uploadSuccess): ?>
        <div class="alert alert-success text-center">商品已成功上傳！</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="p-4 shadow rounded bg-light">
        <!-- 圖片預覽 -->
        <div class="text-center mb-4">
            <img id="preview" src="#" alt="圖片預覽" class="img-thumbnail shadow" style="display: none; max-width: 300px; height: auto;">
        </div>

        <!-- 商品圖片上傳 -->
        <div class="mb-3">
            <label for="image" class="form-label">商品圖片</label>
            <input class="form-control" type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)" required>
        </div>

        <!-- 商品名稱與價格 -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">商品名稱</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="col-md-6">
                <label for="price" class="form-label">價格</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" required>
            </div>
        </div>

        <!-- 商品描述 -->
        <div class="mb-3">
            <label for="description" class="form-label">商品描述</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>

        <!-- 商品狀況 -->
        <div class="mb-3">
            <label for="item_condition" class="form-label">商品狀況</label>
            <select class="form-select" id="item_condition" name="item_condition" required>
                <option value="" selected disabled>請選擇</option>
                <option value="全新">全新</option>
                <option value="九成新">九成新</option>
                <option value="良好">良好</option>
                <option value="使用痕跡">有使用痕跡</option>
                <option value="損壞">損壞</option>
            </select>
        </div>

        <!-- 提交按鈕 -->
        <div class="text-center">
            <button type="submit" class="btn btn-primary px-5">上傳</button>
        </div>
    </form>
</div>

<script>
function previewImage(event) {
    const input = event.target;
    const preview = document.getElementById("preview");

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = "block";
        };

        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
