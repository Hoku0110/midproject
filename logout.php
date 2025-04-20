<?php
session_start();

// 清除 session 資料
session_unset();
session_destroy();

// 清除 cookie
setcookie('user', '', time() - 3600, '/'); // 設定過期時間為一小時前

// 重定向到首頁
header("Location: index");
exit();
?>
