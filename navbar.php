<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loggedIn = isset($_SESSION['user']) || isset($_COOKIE['user']);
$username = $_SESSION['user'] ?? $_COOKIE['user'] ?? '';
$user_avatar = 'default.png'; // 預設頭像

// 檢查使用者是否已登入且有資料庫連接
if ($loggedIn && isset($conn)) {
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $navbar_result = $stmt->get_result(); // 使用不同的變數名稱
    $user = $navbar_result->fetch_assoc();

    // 若使用者有自訂頭像，更新頭像變數
    if (!empty($user['avatar'])) {
        $user_avatar = htmlspecialchars($user['avatar']);
    }
}
?>
<style>
    .upload-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        font-size: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index">
            <img src="img/logo.png" width="100" height="100" alt="平台Logo" class="me-2">
            <span class="fw-bold text-dark">EasyTrade 二手交易</span>
        </a>
        <form class="d-flex mx-auto search-bar" role="search" method="GET" action="<?php echo $loggedIn ? 'admin.php' : 'index.php'; ?>">
            <input class="form-control me-2" type="search" name="search" placeholder="搜尋商品..." aria-label="Search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button class="btn btn-outline-success" type="submit">搜尋</button>
        </form>

        <?php if ($loggedIn): ?>
            <!-- 上傳按鈕 -->
            <a href="upload" class="btn btn-primary ms-3">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> 上傳
            </a>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="profile_pictures/<?php echo $user_avatar; ?>" alt="使用者頭像" class="rounded-circle" width="40" height="40">
                    <?php echo htmlspecialchars($username); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile">👤 個人資料</a></li>
                    <li><a class="dropdown-item" href="favorites">⭐ 我的收藏</a></li>
                    <li><a class="dropdown-item" href="my_items">📦 我的上架商品</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout">🚪 登出</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="login" class="btn btn-primary">登入</a>
        <?php endif; ?>
    </div>
</nav>
