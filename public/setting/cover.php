<?php
session_start();
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
// ログインしている会員情報を引く
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $_SESSION['login_user_id'],
]);
$user = $select_sth->fetch();

// フォームが送信された場合の処理
if (isset($_POST['image_base64'])) {
    
    $cover_filename = $user['cover_filename']; // 現在の値で初期化

    if (!empty($_POST['image_base64'])) { 
        $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);
        $image_binary = base64_decode($base64);
        // ファイル名とパスをカバー画像用に調整
        $cover_filename = 'cover_' . strval(time()) . bin2hex(random_bytes(20)) . '.png';
        $filepath = '/var/www/upload/image/' . $cover_filename;
        file_put_contents($filepath, $image_binary);
    }

    // データベースの更新
    $update_sth = $dbh->prepare("
        UPDATE users SET  
            cover_filename = :cover_filename 
        WHERE id = :id
    ");

    $update_sth->execute([
        ':id' => $user['id'],
        ':cover_filename' => $cover_filename,
    ]);
    
    $_SESSION['success_message'] = 'カバー画像が正常に更新されました。';

    header("HTTP/1.1 302 Found");
    header("Location: ./cover.php");
    return;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カバー画像設定</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .cover-preview {
            width: 100%;
            height: 200px;
            background-color: #e0e0e0;
            border: 1px dashed #999;
            margin-bottom: 20px;
            overflow: hidden;
            text-align: center;
        }
        .cover-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>

<div class="main-container">

    <h1>🖼️ カバー画像設定</h1>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-banner">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <div class="setting-card">
        <h2>現在のカバー画像</h2>
        <div class="cover-preview">
            <?php if(!empty($user['cover_filename'])): ?>
                <img src="/image/<?= htmlspecialchars($user['cover_filename']) ?>" alt="現在のカバー画像">
            <?php else: ?>
                <p style="padding-top: 80px; color: #666;">カバー画像未設定</p>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <div class="input-file-group">
                <input type="file" accept="image/*" name="image" id="imageInput">
            </div>
            <input id="imageBase64Input" type="hidden" name="image_base64">
            <canvas id="imageCanvas" style="display: none;"></canvas>
            <button type="submit" class="btn-primary" style="width: auto;">カバー画像をアップロード/変更</button>
        </form>
    </div>

    <ul class="back-link-list">
        <li><a href="./index.php">← 設定一覧に戻る</a></li>
    </ul>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const imageInput = document.getElementById("imageInput");
    const imageBase64Input = document.getElementById("imageBase64Input");
    const canvas = document.getElementById("imageCanvas");

    imageInput.addEventListener("change", () => {
        // ... (icon.phpのJavaScriptと全く同じロジックをコピー) ...
        if (imageInput.files.length < 1) { imageBase64Input.value = ''; return; }
        const file = imageInput.files[0];
        if (!file.type.startsWith('image/')){ return; }
        
        const reader = new FileReader();
        const image = new Image();

        reader.onload = () => { 
            image.onload = () => { 
                // カバー画像の場合、横長を想定し、最大幅1200pxで調整 (maxLengthを1200に設定)
                const originalWidth = image.naturalWidth; 
                const originalHeight = image.naturalHeight; 
                const maxLength = 1200; 

                if (originalWidth <= maxLength && originalHeight <= maxLength) { 
                    canvas.width = originalWidth;
                    canvas.height = originalHeight;
                } else if (originalWidth > originalHeight) { 
                    canvas.width = maxLength;
                    canvas.height = maxLength * originalHeight / originalWidth;
                } else { 
                    canvas.width = maxLength * originalWidth / originalHeight;
                    canvas.height = maxLength;
                }
                
                const context = canvas.getContext("2d");
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                
                imageBase64Input.value = canvas.toDataURL();
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>

</body>
</html>
