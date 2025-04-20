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

// 確認是否提供商品 ID
if (!isset($_GET['id'])) {
    header("Location: my_items.php");
    exit;
}

$item_id = intval($_GET['id']);

// 確認商品是否屬於該使用者
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo "商品不存在或您無權編輯此商品！";
    exit;
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? $item['name'];
    $price = $_POST['price'] ?? $item['price'];
    $item_condition = $_POST['item_condition'] ?? $item['item_condition'];
    $description = $_POST['description'] ?? $item['description'];

    // 處理圖片上傳
    $image = $item['image'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "img/";
        $target_file = $target_dir . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // 檢查是否為有效圖片
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = htmlspecialchars(basename($_FILES['image']['name']));
            } else {
                echo "圖片上傳失敗！";
                exit;
            }
        } else {
            echo "檔案不是有效的圖片！";
            exit;
        }
    }

    // 更新商品資訊
    $stmt = $conn->prepare("UPDATE items SET name = ?, price = ?, item_condition = ?, description = ?, image = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sdsssii", $name, $price, $item_condition, $description, $image, $item_id, $user_id);
    if ($stmt->execute()) {
        header("Location: my_items.php");
        exit;
    } else {
        echo "更新失敗：" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>編輯商品 - EasyTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4">編輯商品</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">商品名稱</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">價格</label>
                <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($item['price']); ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="item_condition" class="form-label">商品狀況</label>
                <select class="form-select" id="item_condition" name="item_condition" required>
                    <option value="全新" <?php echo $item['item_condition'] === '全新' ? 'selected' : ''; ?>>全新</option>
                    <option value="九成新" <?php echo $item['item_condition'] === '九成新' ? 'selected' : ''; ?>>九成新</option>
                    <option value="使用痕跡" <?php echo $item['item_condition'] === '使用痕跡' ? 'selected' : ''; ?>>使用痕跡</option>
                    <option value="損壞" <?php echo $item['item_condition'] === '損壞' ? 'selected' : ''; ?>>損壞</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">描述</label>
                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($item['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">商品圖片</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if (!empty($item['image'])): ?>
                    <img src="img/<?php echo htmlspecialchars($item['image']); ?>" alt="商品圖片" class="img-fluid mt-2" style="max-width: 200px;">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">更新商品</button>
            <a href="my_items.php" class="btn btn-secondary">取消</a>
        </form>
    </div>
</body>
</html>