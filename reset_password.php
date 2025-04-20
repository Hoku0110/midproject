<?php
session_start();
include 'db.php';

$error = $success = null;
$user = null; // 初始為空

// 檢查是否有傳入 token
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 檢查 token 是否有效並匹配使用者
    $stmt = $conn->prepare("SELECT id, username, avatar, token_expiry FROM users WHERE reset_token = ?");
    if ($stmt === false) {
        die("SQL錯誤: " . $conn->error);  // 顯示資料庫錯誤訊息
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    // 如果找到該用戶
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $token_expiry = $user['token_expiry'];

        // 檢查 token 是否過期
        if (strtotime($token_expiry) > time()) {
            // 如果是有效 token，允許進行密碼重設
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                // 檢查新密碼與確認密碼是否一致
                if ($new_password !== $confirm_password) {
                    $error = "兩次密碼不一致！";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // 更新密碼並清除重設 token
                    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ?");
                    if ($stmt === false) {
                        die("SQL錯誤: " . $conn->error);  // 顯示資料庫錯誤訊息
                    }

                    $stmt->bind_param("ss", $hashed_password, $token);
                    $stmt->execute();

                    $success = "密碼已成功重設！您現在可以使用新密碼登入。";
                }
            }
        } else {
            // Token 過期時跳出錯誤訊息
            $error = "此重設連結已過期。";
        }
    } else {
        // 無效的 token 時跳出錯誤訊息
        $error = "無效的重設連結。";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>重設密碼</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .avatar-preview {
            width: 80px;  /* 調整為 80px */
            height: 80px; /* 調整為 80px */
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;  /* 增加右邊的間距 */
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <script>
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
    </script>
</head>
<body>
        

<div class="container mt-5">
    <div class="col-md-6 mx-auto">
        <h2 class="text-center">重設密碼</h2>

        <?php if (isset($error)): ?>
            <!-- 在錯誤時顯示 SweetAlert -->
            <script>
                Swal.fire({
                    title: '錯誤！',
                    text: '<?php echo $error; ?>',
                    icon: 'error',
                    confirmButtonText: '確認'
                });
            </script>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <!-- 成功時顯示 SweetAlert -->
            <script>
                Swal.fire({
                    title: '成功！',
                    text: '<?php echo $success; ?>',
                    icon: 'success',
                    confirmButtonText: '確認'
                }).then(function() {
                    window.location = 'login.php';  // 跳轉至登入頁
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error) && $error === "兩次密碼不一致！"): ?>
            <!-- 在密碼不一致時顯示 SweetAlert -->
            <script>
                Swal.fire({
                    title: '錯誤！',
                    text: '<?php echo $error; ?>',
                    icon: 'error',
                    confirmButtonText: '確認'
                });
            </script>
        <?php endif; ?>

        <!-- 只在有效 token 時顯示密碼重設表單 -->
        <?php if ($user): ?>
            <form method="POST">
                <!-- 顯示使用者名稱和頭像 -->
                <div class="mb-3 d-flex align-items-center">
                    <img src="profile_pictures/<?php echo $user['avatar'] ?: 'default.png'; ?>" class="avatar-preview" alt="頭像">
                    <span class="form-label"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>

                <!-- 新密碼 -->
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="新密碼" required>
                    <label for="new_password">新密碼</label>
                    <i class="fas fa-eye-slash password-toggle" id="new_password-toggle" onclick="togglePasswordVisibility('new_password')"></i>
                </div>

                <!-- 確認密碼 -->
                <div class="form-floating position-relative mt-3">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="確認密碼" required>
                    <label for="confirm_password">確認密碼</label>
                </div>

                <button type="submit" class="btn btn-primary mt-3">重設密碼</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
