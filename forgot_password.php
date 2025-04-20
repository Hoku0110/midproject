<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

session_start();
include 'db.php';

if (!$conn) {
    die("資料庫連線失敗：" . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';

    // 檢查 email 是否有效
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '無效的電子郵件地址';
    } else {
        // 檢查電子郵件是否存在於資料庫中
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt === false) {
            die("SQL錯誤: " . $conn->error);  // 顯示資料庫錯誤訊息
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // 生成重設密碼的唯一 token
            $token = bin2hex(random_bytes(10)); // 隨機生成50字節的token

            // 將 token 儲存到資料庫（可設置有效期，例如：1小時）
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            if ($stmt === false) {
                die("SQL錯誤: " . $conn->error);  // 顯示資料庫錯誤訊息
            }

            $stmt->bind_param("ss", $token, $email);
            $stmt->execute();

            // 發送重設密碼的郵件
            $reset_link = "http://localhost/project1/reset_password.php?token=$token";  // 更新為project1
            $mail = new PHPMailer(true);
            try {
                // 設定SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'easytrade0111@gmail.com'; // 使用你的 Gmail 郵箱
                $mail->Password = 'agxl opyt gyhe rzhr';  // 使用你的 Gmail 應用程式密碼
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                // 設定寄信
                $mail->setFrom('easytrade0111@gmail.com', 'EasyTrade 管理員');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8'; 
                $mail->Subject = '重設密碼請求';
                $mail->Body = "您收到此郵件是因為您請求重設密碼。<br><br>請點擊以下連結來重設您的密碼：<br><a href='$reset_link'>$reset_link</a>";

                $mail->send();
                $success = '重設密碼的連結已經發送到您的郵箱！';
            } catch (Exception $e) {
                $error = '發送郵件失敗，錯誤訊息：' . $mail->ErrorInfo;
            }
        } else {
            $error = "該電子郵件地址未註冊";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-5">
    <div class="col-md-6 mx-auto">
        <h2 class="text-center">忘記密碼</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">電子郵件地址</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">發送重設連結</button>
        </form>
    </div>
</div>

</body>
</html>
