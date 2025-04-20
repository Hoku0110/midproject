<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

// 確保用戶已登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: index");
    exit();
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$stmt = $conn->prepare("SELECT id, username, avatar, email FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: index");
}

$user_id = $user['id'];
$avatar = !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : "default.png";

// 初始化驗證碼
if (!isset($_SESSION['verification_code'])) {
    $_SESSION['verification_code'] = null;
}

// 驗證驗證碼並儲存變更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $input_code = $_POST['verification_code'] ?? '';
    if ($input_code != $_SESSION['verification_code']) {
        $error = "驗證碼不正確！";
    } else {
        // 更新用戶資料
        $new_username = $_POST['username'];
        $new_email = $_POST['email'];
        $new_password = $_POST['new_password'];
        $avatar_file = $_FILES['avatar'] ?? null;

        // 處理圖片上傳
        if ($avatar_file && $avatar_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'profile_pictures/';
            $file_ext = pathinfo($avatar_file['name'], PATHINFO_EXTENSION);
            $new_avatar_name = uniqid('avatar_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_avatar_name;

            // 移動上傳的檔案
            if (move_uploaded_file($avatar_file['tmp_name'], $upload_path)) {
                $avatar = $new_avatar_name; // 只存檔案名稱
            } else {
                $error = "圖片上傳失敗！";
            }
        }

        // 更新資料庫時只存檔案名稱
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET username = ?, email = ?, password = ?, avatar = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssi", $new_username, $new_email, $hashed_password, $avatar, $user_id);
        } else {
            $update_query = "UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssi", $new_username, $new_email, $avatar, $user_id);
        }

        // 執行更新語句並檢查結果
        if ($stmt->execute()) {
            $message = "資料已成功更新！";
            $_SESSION['verification_code'] = null; // 清除驗證碼

            // 更新頁面上的用戶資料
            $user['username'] = $new_username;
            $user['email'] = $new_email;
            $user['avatar'] = $avatar; // 更新為檔案名稱
        } else {
            $error = "更新失敗，請稍後再試！";
        }

        // 關閉語句
        $stmt->close();
    }
}

// 顯示頭像時添加完整路徑
$avatar_path = "profile_pictures/" . htmlspecialchars($user['avatar']);

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>個人資料</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <script>
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const toggleIcon = document.getElementById(id + '-toggle');
            if (input.type === "password") {
                input.type = "text";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            } else {
                input.type = "password";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            }
        }

        function confirmSave() {
            return confirm("確定要儲存變更嗎？");
        }

        let canSendCode = true;

        function sendVerificationCode() {
            if (!canSendCode) {
                alert("請稍後再試！");
                return;
            }

            const email = document.querySelector('input[name="email"]').value;
            if (!email) {
                alert("請輸入電子郵件地址！");
                return;
            }

            const formData = new FormData();
            formData.append('email', email);

            fetch('send_verification_code.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if (result === 'success') {
                    alert("驗證碼已發送到您的電子郵件！");
                    canSendCode = false;
                    const button = document.getElementById('send-code-btn');
                    button.disabled = true;
                    let countdown = 60;
                    const interval = setInterval(() => {
                        button.textContent = `請稍後 ${countdown--} 秒`;
                        if (countdown < 0) {
                            clearInterval(interval);
                            button.textContent = "發送驗證碼";
                            button.disabled = false;
                            canSendCode = true;
                        }
                    }, 1000);
                } else {
                    alert(result);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("發送驗證碼時發生錯誤！");
            });
        }
    </script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="admin">
            <img src="img/logo.png" width="100" height="100" alt="平台Logo" class="me-2">
            <span class="fw-bold text-dark">EasyTrade 二手交易</span>
        </a>
        <a href="admin" class="btn btn-secondary">返回</a>
    </div>
</nav>

<div class="container profile-container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white text-center">
            <h3 class="mb-0">個人資料</h3>
        </div>
        <div class="card-body">
            <!-- 顯示訊息 -->
            <?php if (isset($message)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 頭像區域 -->
            <form action="profile.php" method="POST" enctype="multipart/form-data" onsubmit="return confirmSave();">
                <div class="text-center mb-4">
                    <img id="avatar-preview" src="<?php echo htmlspecialchars($avatar_path); ?>?t=<?php echo time(); ?>" class="avatar-preview mb-3 shadow">
                    <input type="file" name="avatar" class="form-control" accept="image/*" onchange="previewAvatar(event)">
                </div>

                <!-- 帳號名稱 -->
                <div class="mb-3">
                    <label class="form-label">帳戶名稱</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <!-- 電子郵件更改 -->
                <div class="mb-3">
                    <label class="form-label">電子郵件</label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        <button type="button" id="send-code-btn" class="btn btn-secondary" onclick="sendVerificationCode()">發送驗證碼</button>
                    </div>
                </div>

                <!-- 驗證碼 -->
                <div class="mb-3">
                    <label class="form-label">驗證碼</label>
                    <input type="text" class="form-control" id="verification_code" name="verification_code" placeholder="驗證碼">
                </div>

                <!-- 更改密碼 -->
                <div class="mb-3">
                    <label class="form-label">新密碼（留空則不更改）</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="新密碼">
                        <i class="fas fa-eye-slash password-toggle" id="new_password-toggle" onclick="togglePasswordVisibility('new_password')"></i>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">確認新密碼</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="確認新密碼">
                        <i class="fas fa-eye-slash password-toggle" id="confirm_password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary w-100 mt-3">儲存變更</button>
            </form>

            <!-- 刪除帳號 -->
            <form action="delete_account" method="POST" onsubmit="return confirm('確定要刪除帳號嗎？此動作無法復原！');">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <button type="submit" class="btn btn-danger w-100 mt-3">刪除帳號</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
