<?php
$servername = "localhost";  // 主機名稱
$username = "root";         // MySQL 帳號（XAMPP 預設為 root）
$password = "";             // MySQL 密碼（XAMPP 預設為空）
$database = "second_hand_market"; // 資料庫名稱

// 建立資料庫連接
$conn = new mysqli($servername, $username, $password, $database);

// 檢查連接是否成功
if ($conn->connect_error) {
    die("資料庫連接失敗：" . $conn->connect_error);
}

// 設定字符集，確保支援 UTF-8
$conn->set_charset("utf8mb4");
?>
