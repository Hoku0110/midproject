<?php
session_start();
include 'db.php'; // 連接資料庫

// 設定資料庫時區為 GMT+8
$conn->query("SET time_zone = '+08:00'");

// 檢查使用者是否登入
if (!isset($_SESSION['user']) && !isset($_COOKIE['user'])) {
    header("Location: index");
    exit;
}

// 設定時區為台北
date_default_timezone_set('Asia/Taipei');

$username = $_SESSION['user'] ?? $_COOKIE['user'];
$user_id = null;
$user_avatar = 'profile_pictures/default.png';  // 預設頭像

// 取得使用者資料
if (isset($username)) {
    $stmt = $conn->prepare("SELECT id, avatar FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;

    // 設定頭像路徑
    if (!empty($user['avatar'])) {
        $user_avatar = 'profile_pictures/' . htmlspecialchars($user['avatar']);
    }
}

// 取得所有對話，排除自己
$conversations = [];
if ($user_id) {
    $stmt = $conn->prepare("
    SELECT DISTINCT c.id AS conversation_id,
        c.user1_id, c.user2_id,
        IF(c.user1_id = ?, u2.username, u1.username) AS username,
        IF(c.user1_id = ?, u2.avatar, u1.avatar) AS avatar,
        (SELECT 
            CASE 
                WHEN m.image IS NOT NULL THEN '傳送了圖片'
                WHEN m.content IS NOT NULL THEN m.content
                ELSE NULL
            END
         FROM messages m 
         WHERE m.conversation_id = c.id 
         ORDER BY m.created_at DESC 
         LIMIT 1) AS last_message,
        (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_time
    FROM conversations c
    LEFT JOIN users u1 ON u1.id = c.user1_id
    LEFT JOIN users u2 ON u2.id = c.user2_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY last_message_time DESC
    ");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // 如果 user_id 為 0，設置為「已刪除的帳號」和預設頭像
        if (isset($row['user1_id']) && isset($row['user2_id'])) {
            if ($row['user1_id'] == 0 || $row['user2_id'] == 0) {
                $row['username'] = '已刪除的帳號';
                $row['avatar'] = 'default.png';
            }
        }

        if (!isset($conversations[$row['conversation_id']])) {
            $conversations[$row['conversation_id']] = $row;
        }
    }
}

if (isset($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];

    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: chat.php");
        exit;
    }
}

if (isset($_GET['user'])) {
    $chat_user = $_GET['user'];

    // 如果 user 是自己，跳轉回 chat.php
    if ($chat_user === $username) {
        header("Location: chat.php");
        exit;
    }

    // 檢查對話是否存在，若不存在則建立
    $stmt = $conn->prepare("
        SELECT id FROM conversations 
        WHERE (user1_id = ? AND user2_id = (SELECT id FROM users WHERE username = ?)) 
           OR (user2_id = ? AND user1_id = (SELECT id FROM users WHERE username = ?))
    ");
    $stmt->bind_param("isis", $user_id, $chat_user, $user_id, $chat_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversation = $result->fetch_assoc();

    if ($conversation) {
        header("Location: chat.php?conversation_id=" . $conversation['id']);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO conversations (user1_id, user2_id) 
            VALUES (?, (SELECT id FROM users WHERE username = ?))
        ");
        $stmt->bind_param("is", $user_id, $chat_user);
        $stmt->execute();
        $conversation_id = $conn->insert_id;
        header("Location: chat.php?conversation_id=" . $conversation_id);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/logo.png" type="image/x-icon">
    <title>聊天室</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .contact-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            box-sizing: border-box;
        }

        .contact-item img {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .contact-info {
            display: flex;
            align-items: center;
        }

        .contact-name {
            font-weight: bold;
        }

        .message-box {
            display: flex;
            flex-direction: column;
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            margin-bottom: 15px;
            padding: 10px;
            position: relative;
            max-height: 400px;
        }

        .message {
            padding: 10px 15px;
            border-radius: 20px;
            margin: 5px 0 25px 0;
            max-width: 60%;
            word-wrap: break-word;
            width: fit-content;
            clear: both;
            position: relative;
            box-sizing: border-box;
        }

        .message.left {
            background-color: #f1f1f1;
            border-radius: 20px 20px 20px 0;
            align-self: flex-start;
        }

        .message.right {
            background-color: #d1e7dd;
            border-radius: 20px 20px 0 20px;
            align-self: flex-end;
            text-align: right;
        }

        .message-time {
            font-size: 12px;
            color: #999;
            position: absolute;
            right: 10px;
            bottom: -18px;
            white-space: nowrap;
            pointer-events: none;
        }

        .message-box::-webkit-scrollbar {
            width: 6px;
        }

        .message-box::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .message-box::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .contact-item .message-preview {
            font-size: 13px;
            color: #666;
        }

        .contact-time {
            position: absolute;
            bottom: 5px;
            right: 10px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

<?php include('navbar.php'); ?>

<div class="container mt-5">
    <div class="row">
    <div class="col-md-3">
        <h4>聯絡人</h4>
        <div class="list-group">
    <?php if (empty($conversations)): ?>
        <div class="list-group-item">無聊天紀錄</div>
    <?php else: ?>
        <?php foreach ($conversations as $conversation): ?>
            <?php
            $lastTime = strtotime($conversation['last_message_time']);
            $now = time();

            // 檢查是否是同一天
            if (date("Y-m-d", $lastTime) !== date("Y-m-d", $now)) {
                $formattedTime = date("m/d", $lastTime);
            } else {
                $formattedTime = date("H:i", $lastTime);
            }
            ?>
            <a href="chat?conversation_id=<?php echo $conversation['conversation_id']; ?>" 
               class="list-group-item contact-item" 
               data-conversation-id="<?php echo $conversation['conversation_id']; ?>">
                <div class="contact-info">
                    <img src="profile_pictures/<?php echo htmlspecialchars(!empty($conversation['avatar']) ? $conversation['avatar'] : 'default.png'); ?>" alt="頭像">
                    <div>
                        <div class="contact-name"><?php echo htmlspecialchars(!empty($conversation['username']) ? $conversation['username'] : '已刪除帳號'); ?></div>
                        <div class="message-preview">
                            <?php
                            $preview = trim($conversation['last_message']);
                            echo !empty($preview) ? htmlspecialchars($preview) : '<span class="text-muted">（無內容）</span>';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="contact-time"><?php echo $formattedTime; ?></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
        <a href="create_conversation" class="btn btn-primary mt-3 w-100" data-bs-toggle="modal" data-bs-target="#createConversationModal">新增聊天</a>
    </div>


        <div class="col-md-9">
            <h4>聊天室</h4>

            <?php
            if (isset($_GET['conversation_id'])) {
                $conversation_id = $_GET['conversation_id'];
                echo '<div class="message-box" id="messageBox" style="position: relative;">
                <div class="text-center mt-3 position-relative" style="z-index: 1050;">
                        <button class="btn btn-secondary" onclick="scrollToBottom()">跳到最新訊息</button>
                    </div>
                </div>';
                
            ?>
                <form action="send_message.php" method="POST" enctype="multipart/form-data" onsubmit="return validateMessage();">
                    <input type="hidden" name="conversation_id" value="<?php echo htmlspecialchars($conversation_id); ?>">
                    <div class="input-group mt-3 position-relative">
                        <div class="position-relative">
                            <button class="btn btn-outline-secondary" type="button" style="max-width: 50px;" onclick="document.getElementById('imageInput').click();">+</button>
                            <input type="file" id="imageInput" class="d-none" name="image" accept="image/*" onchange="previewAttachment(event)">
                            <div id="attachmentPreviewContainer" class="position-fixed" style="bottom: 30%; left: 30%; display: none; z-index: 1050; background: rgba(255, 255, 255, 0.9); padding: 10px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                                <img id="attachmentPreview" src="#" alt="附件預覽" class="img-thumbnail" style="max-width: 200px; height: auto;">
                                <button class="btn btn-danger btn-sm mt-2" onclick="closePreview()">X</button>
                            </div>
                        </div>
                        <input type="text" class="form-control" name="content" placeholder="輸入訊息...">
                        <button class="btn btn-primary" type="submit">送出</button>
                    </div>

                    
                </form>
            <?php } else { ?>
                <div class="alert alert-warning">請選擇一個對話來開始聊天。</div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- 新增聊天 Modal -->
<div class="modal fade" id="createConversationModal" tabindex="-1" aria-labelledby="createConversationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createConversationModalLabel">新增聊天</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createConversationForm">
                    <div class="mb-3">
                        <label for="receiverId" class="form-label">選擇聯絡人</label>
                        <select class="form-select" id="receiverId" name="receiver_id" required>
                            <option value="" disabled selected>請選擇聯絡人</option>
                            <!-- 聯絡人選項將由 JavaScript 動態載入 -->
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">建立對話</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 圖片預覽 Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewModalLabel">圖片預覽</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="#" alt="圖片" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
</div>

<script>
    const userId = <?php echo json_encode($user_id); ?>;
    const messageBox = document.getElementById('messageBox');
    const conversationsContainer = document.querySelector('.list-group');

    // 滾動至聊天底部
    function scrollToBottom() {
        if (messageBox) {
            messageBox.scrollTop = messageBox.scrollHeight;
        }
    }

    // 載入聊天訊息
    function loadMessages(conversationId) {
        if (!conversationId) return;

        fetch(`get_messages.php?conversation_id=${conversationId}`)
            .then(res => res.json())
            .then(messages => {
                messageBox.innerHTML = '';
                messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'message ' + (msg.sender_id == userId ? 'right' : 'left');
                    div.innerHTML = `
                        ${msg.content ? `<p>${msg.content}</p>` : ''}
                        ${msg.image ? `<img src="${msg.image}" alt="圖片" class="img-fluid mt-2" style="max-width: 200px; cursor: pointer;" onclick="openImageModal('${msg.image}')">` : ''}
                        <span class="message-time">${msg.created_at}</span>
                    `;
                    messageBox.appendChild(div);
                });
                scrollToBottom();
            });
    }

    // 檢查新訊息並更新聯絡人列表
    function checkNewMessages(conversationId) {
        if (!conversationId) return;

        fetch(`check_new_messages.php?conversation_id=${conversationId}`)
            .then(res => res.json())
            .then(data => {
                if (data.new_messages > 0) {
                    loadMessages(conversationId);
                    loadConversations();  // 重新載入聯絡人列表
                }
            });
    }

    // 載入所有對話
    function loadConversations() {
        fetch('get_conversations.php')
            .then(res => res.json())
            .then(conversations => {
                const conversationsContainer = document.querySelector('.list-group');
                conversationsContainer.innerHTML = ''; // 清空聯絡人列表

                if (conversations.length === 0) {
                    conversationsContainer.innerHTML = '<div class="list-group-item">無聊天紀錄</div>';
                } else {
                    conversations.forEach(conversation => {
                        const lastMessage = conversation.last_message || '<span class="text-muted">（無內容）</span>';
                        const lastTime = new Date(conversation.last_message_time);
                        const formattedTime = (lastTime.getFullYear() !== new Date().getFullYear()) 
                            ? lastTime.toLocaleDateString() 
                            : lastTime.toLocaleTimeString();

                        // 創建聯絡人項目
                        const contactItem = `
                            <a href="chat.php?conversation_id=${conversation.conversation_id}" class="list-group-item contact-item" data-conversation-id="${conversation.conversation_id}">
                                <div class="contact-info">
                                    <img src="profile_pictures/${conversation.avatar || 'default.png'}" alt="頭像">
                                    <div>
                                        <div class="contact-name">${conversation.username}</div>
                                        <div class="message-preview">${lastMessage}</div>
                                    </div>
                                </div>
                                <div class="contact-time">${formattedTime}</div>
                            </a>
                        `;
                        conversationsContainer.innerHTML += contactItem;
                    });
                }
            });
    }

    // 新增聊天後重新載入聯絡人
    document.addEventListener('DOMContentLoaded', () => {
        const createConversationButton = document.querySelector('.btn-primary.mt-3.w-100');
        if (createConversationButton) {
            createConversationButton.addEventListener('click', () => {
                setTimeout(() => {
                    loadConversations(); // 新增聊天後重新載入聯絡人列表
                }, 1000); // 延遲 1 秒以確保後端處理完成
            });
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadConversations();  // 載入聯絡人列表

        const urlParams = new URLSearchParams(window.location.search);
        const conversationId = urlParams.get('conversation_id');
        if (conversationId) {
            loadMessages(conversationId); // 載入選擇的對話訊息
            setInterval(() => checkNewMessages(conversationId), 1000); // 每 5 秒檢查新訊息
        }
    });
    

    // 定期檢查是否有新對話
    function checkNewConversations() {
        fetch('check_new_conversations.php')
            .then(res => res.json())
            .then(data => {
                if (data.new_conversations) {
                    loadConversations(); // 如果有新對話，重新載入聯絡人列表
                }
            });
    }

    // 每 5 秒檢查一次新對話
    document.addEventListener('DOMContentLoaded', () => {
        setInterval(checkNewConversations, 1000); // 每 5 秒檢查一次
    });

    // 載入聯絡人選項
    function loadAvailableUsers() {
        fetch('get_users.php')
            .then(res => res.json())
            .then(users => {
                const receiverSelect = document.getElementById('receiverId');
                receiverSelect.innerHTML = '<option value="" disabled selected>請選擇聯絡人</option>';
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id; // 使用者 ID
                    option.textContent = user.username; // 使用者名稱
                    receiverSelect.appendChild(option);
                });
            })
            .catch(err => console.error('載入聯絡人失敗:', err));
    }

    // 在 Modal 顯示時載入聯絡人
    document.getElementById('createConversationModal').addEventListener('show.bs.modal', loadAvailableUsers);

    document.getElementById('createConversationForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('create_conversation.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            console.log(data); // 👉 新增除錯訊息
            if (data.success) {
                window.location.href = `chat.php?conversation_id=${data.conversation_id}`; // 跳轉到新對話
            } else if (data.conversation_id) {
                window.location.href = `chat.php?conversation_id=${data.conversation_id}`; // 跳轉到已存在對話
            } else {
                alert(data.message);
            }
        })
        .catch(err => console.error('建立對話失敗:', err));
    });

    function previewAttachment(event) {
        const input = event.target;
        const previewContainer = document.getElementById("attachmentPreviewContainer");
        const preview = document.getElementById("attachmentPreview");

        if (input.files && input.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.style.display = "block";
            };

            reader.readAsDataURL(input.files[0]);
        } else {
            previewContainer.style.display = "none";
        }
    }

    function closePreview() {
        const previewContainer = document.getElementById("attachmentPreviewContainer");
        previewContainer.style.display = "none";
        document.getElementById("imageInput").value = ""; // 清空文件選擇
    }

    document.querySelector('form').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadMessages(data.conversation_id); // 重新載入訊息
                scrollToBottom(); // 跳到最新訊息
            } else {
                alert(data.error || '傳送訊息失敗');
            }
        })
        .catch(err => console.error('傳送訊息失敗:', err));
    });
    function openImageModal(imageSrc) {
        const modalImage = document.getElementById('modalImage');
        modalImage.src = imageSrc; // 設定圖片來源
        const imagePreviewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        imagePreviewModal.show(); // 顯示 Modal
    }
</script>


</body>
</html>
