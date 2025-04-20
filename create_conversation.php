<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// 驗證登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '無效的使用者']);
    exit;
}

// 驗證 POST 資料是否正確
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '請求方法錯誤']);
    exit;
}

if (empty($_POST['receiver_id'])) {
    echo json_encode(['success' => false, 'message' => '接收者 ID 未提供']);
    exit;
}

$receiver_id = $_POST['receiver_id'] ?? null;

if (!$receiver_id) {
    echo json_encode(['success' => false, 'message' => '請選擇接收者']);
    exit;
}

if ($receiver_id == 0) {
    echo json_encode(['success' => false, 'message' => '無效的接收者']);
    exit;
}

// 確認接收者是否存在
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => '接收者不存在']);
    exit;
}

if ($user_id && $receiver_id) {
    // 檢查是否已有對話
    $stmt = $conn->prepare("
        SELECT id 
        FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '查詢失敗：' . $conn->error]);
        exit;
    }
    $stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existing_conversation = $result->fetch_assoc();
        echo json_encode(['success' => false, 'message' => '對話已存在', 'conversation_id' => $existing_conversation['id']]);
        exit;
    } else {
        // 創建新對話
        $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id, created_at) VALUES (?, ?, NOW())");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => '建立對話失敗：' . $conn->error]);
            exit;
        }
        $stmt->bind_param("ii", $user_id, $receiver_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $new_conversation_id = $stmt->insert_id; // 獲取新對話的 ID
            echo json_encode(['success' => true, 'conversation_id' => $new_conversation_id]);
        } else {
            echo json_encode(['success' => false, 'message' => '建立對話失敗']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的接收者']);
}
?>
