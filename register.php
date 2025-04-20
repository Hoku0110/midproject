<?php
session_start();
include 'db.php';

if (isset($_SESSION['user']) || isset($_COOKIE['user'])) {
    header("Location: admin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $verification_code = $_POST['verification_code'] ?? ''; // 用戶輸入的驗證碼
    $avatar = '';  // 預設無頭像

    // 驗證碼檢查
    if ($verification_code != $_SESSION['verification_code']) {
        $error = "驗證碼錯誤，請再試一次！";
        $_POST['verification_code'] = ''; // 清空驗證碼欄位
    } else {
        if ($password !== $confirm_password) {
            $error = "密碼不一致，請再確認一次！";
        } else {
            // 檢查使用者名稱是否已存在
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "此帳號名稱已被註冊，請更換！";
            } else {
                // 檢查電子郵件是否已存在
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error = "此電子郵件已被使用，請使用其他信箱！";
                } else {
                    // 處理頭像上傳
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                        $targetDir = "profile_pictures/";
                        $targetFile = basename($_FILES["avatar"]["name"]);
                        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetDir . $targetFile)) {
                                $avatar = $targetFile;
                            } else {
                                $error = "檔案上傳失敗！";
                            }
                        } else {
                            $error = "只允許上傳 JPG, JPEG, PNG 或 GIF 檔案！";
                        }
                    }

                    if (!isset($error)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password, avatar) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $username, $email, $hashed_password, $avatar);
                        if ($stmt->execute()) {
                            header("Location: login");
                            exit;
                        } else {
                            $error = "註冊失敗：" . $conn->error;
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊帳號</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" rel="stylesheet">
    <style>
        .register-container {
            max-width: 500px;
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 120px auto 50px;
        }
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        .password-wrapper {
            position: relative;
        }
        #password-toggle, #confirm-password-toggle {
            position: absolute;
            top: 72.5%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <script>
        function previewImage() {
            var file = document.getElementById('avatar').files[0];
            var reader = new FileReader();
            reader.onloadend = function () {
                document.getElementById('avatarPreview').src = reader.result;
            }
            if (file) {
                reader.readAsDataURL(file);
            }
        }

        function togglePasswordVisibility(id) {
            var passwordField = document.getElementById(id);
            var toggleIcon = document.getElementById(id + '-toggle');
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        let countdownTimer;

        function startCountdown() {
            let button = document.getElementById("send-verification-code");
            let countdown = 60;
            button.disabled = true;
            button.textContent = `請等 ${countdown} 秒`;

            countdownTimer = setInterval(() => {
                countdown--;
                button.textContent = `請等 ${countdown} 秒`;

                if (countdown <= 0) {
                    clearInterval(countdownTimer);
                    button.disabled = false;
                    button.textContent = "發送驗證碼";
                }
            }, 1000);
        }

        function sendVerificationCode() {
            let email = document.getElementById("email").value;

            if (email === '') {
                alert('請輸入電子郵件地址！');
                return;
            }

            let xhr = new XMLHttpRequest();
            xhr.open("POST", "send_verification_code.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    let response = xhr.responseText;

                    if (response === 'success') {
                        alert('驗證碼已發送到您的郵箱！');
                        startCountdown();
                    } else {
                        alert('發送失敗: ' + response);
                    }
                }
            };
            xhr.send("email=" + encodeURIComponent(email));
        }
    </script>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="register-container">
    <h2 class="text-center mb-4">註冊帳號</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="username" class="form-label">帳戶名稱</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">電子郵件</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required>
            <button type="button" id="send-verification-code" class="btn btn-secondary mt-2" onclick="sendVerificationCode()">發送驗證碼</button>
        </div>

        <div class="mb-3">
            <label for="verification_code" class="form-label">驗證碼</label>
            <input type="text" class="form-control" id="verification_code" name="verification_code" value="<?php echo htmlspecialchars($_POST['verification_code'] ?? '', ENT_QUOTES); ?>" required>
        </div>

        <div class="mb-3 password-wrapper">
            <label for="password" class="form-label">密碼</label>
            <input type="password" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES); ?>" required>
            <i id="password-toggle" class="fas fa-eye-slash" onclick="togglePasswordVisibility('password')"></i>
        </div>

        <div class="mb-3 password-wrapper">
            <label for="confirm_password" class="form-label">確認密碼</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? '', ENT_QUOTES); ?>" required>
        </div>

        <div class="mb-3">
            <label for="avatar" class="form-label">上傳頭像</label>
            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*" onchange="previewImage()">
        </div>

        <div class="mb-3">
            <label class="form-label">頭像預覽</label><br>
            <img id="avatarPreview" src="profile_pictures/default.png" alt="頭像預覽" class="avatar-preview">
        </div>

        <button type="submit" class="btn btn-primary">註冊</button>
    </form>

    <div class="text-center mt-3">
        已有帳號? <a href="login">進行登入</a>
    </div>
</div>

</body>
</html>
