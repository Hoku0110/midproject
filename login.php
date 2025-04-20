<?php
session_start();
include 'db.php';

$error = "";
if (isset($_SESSION['user']) || isset($_COOKIE['user'])) {
    header("Location: admin");
    exit();
}       

// 檢查是否提交表單
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);
    $password = trim($_POST['password']);

    if (!empty($identifier) && !empty($password)) {
        // 查詢 username 或 email 都可以登入
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // 驗證密碼
        if ($user && password_verify($password, $user['password'])) {
            $user_id = $user['id']; // 從資料庫查詢到的使用者 ID
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user'] = $user['username']; // 使用者名稱

            setcookie("user", $user['username'], time() + (7 * 24 * 60 * 60), "/");

            header("Location: admin");
            exit();
        } else {
            $error = "帳號或密碼錯誤";
        }
    } else {
        $error = "請輸入帳號與密碼";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>登入 - EasyTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" rel="stylesheet">
    <style>
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 72.5%;
            transform: translateY(-50%);
            cursor: pointer;
            color: gray;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- 登入表單 -->
    <div class="d-flex align-items-center justify-content-center vh-100" style="margin-top: -80px;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body">
                            <h3 class="text-center">登入</h3>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">帳號 / 電子郵件</label>
                                    <input type="text" name="identifier" class="form-control" required>
                                </div>
                                <div class="mb-3 password-wrapper">
                                    <label class="form-label">密碼</label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                    <i class="fas fa-eye-slash password-toggle" id="password-toggle" onclick="togglePasswordVisibility('password')"></i>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">登入</button>
                            </form>
                            <div class="text-center mt-3">
                                <a href="register">還沒有帳號？立即註冊</a><br>
                                <a href="forgot_password.php" class="text-muted">忘記密碼？</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
</body>
</html>
