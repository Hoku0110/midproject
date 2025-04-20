<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';
require 'db.php'; // 引入資料庫連接文件

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';

    // 檢查 email 是否有效
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '無效的電子郵件地址';
        exit;
    }

    // 檢查 email 是否已經註冊
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "此電子郵件已被使用，請使用其他信箱！";
        exit;
    }

    // 生成驗證碼 (6位數字)
    $verification_code = rand(100000, 999999);

    // 將驗證碼儲存到 SESSION 中
    session_start();
    $_SESSION['verification_code'] = $verification_code;

    // 設定寄件信箱（Gmail）
    $mail = new PHPMailer(true);
    try {
        // 設定SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'easytrade0111@gmail.com';
        $mail->Password = 'agxl opyt gyhe rzhr';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // 設定寄信
        $mail->setFrom('easytrade0111@gmail.com', 'EasyTrade 管理員');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '註冊驗證碼';
        $mail->Body = "您的註冊驗證碼是：<b>$verification_code</b>";

        // 發送郵件
        $mail->send();
        echo 'success';  // 發送成功
    } catch (Exception $e) {
        echo '發送失敗，錯誤訊息：' . $mail->ErrorInfo;
    }
}

?>
