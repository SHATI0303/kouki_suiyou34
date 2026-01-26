<?php
session_start();
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
// セッションにあるログインIDから、ログインしている対象の会員情報を引く
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $_SESSION['login_user_id'],
]);
$user = $select_sth->fetch();

// フォームが送信された場合の処理を統合
if (isset($_POST['image_base64']) || isset($_POST['introduction_submitted'])) {
    
    // データベースに保存する値を現在の値で初期化
    $image_filename = $user['icon_filename'];
    $new_introduction = $user['introduction'];

    // --- 1. 画像処理 ---
    if (isset($_POST['image_base64'])) { // 画像データが送られてきた場合のみ処理
        if (!empty($_POST['image_base64'])) { 
            $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);
            $image_binary = base64_decode($base64);
            $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
            $filepath = '/var/www/upload/image/' . $image_filename;
            file_put_contents($filepath, $image_binary);
        }
    }
    
    // --- 2. 自己紹介文の処理 ---
    if (isset($_POST['introduction_submitted'])) {
        $new_introduction = trim($_POST['introduction']);
    }

    // --- 3. データベースの更新 ---
    $update_sth = $dbh->prepare("
        UPDATE users SET 
            icon_filename = :icon_filename,
            introduction = :introduction 
        WHERE id = :id
    ");

    $update_sth->execute([
        ':id' => $user['id'],
        ':icon_filename' => $image_filename,
        ':introduction' => $new_introduction,
    ]);
    
    // ★ 成功メッセージをセッションに保存
    $_SESSION['success_message'] = '設定が正常に更新されました。';

    // 処理が終わったらリダイレクトする
    header("HTTP/1.1 302 Found");
    header("Location: ./icon.php");
    return;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィール設定</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="main-container">

    <h1>⚙️ プロフィール設定</h1>
    
    <?php 
    if (isset($_SESSION['success_message'])): 
    ?>
        <div class="success-banner">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
    <?php 
    unset($_SESSION['success_message']); 
    endif; 
    ?>

    <div class="setting-card">
        <form method="POST">
            <h2>自己紹介文の編集</h2>
            <p style="color: #6b7280; margin-bottom: 15px;">最大1000文字まで入力できます。</p>
            <textarea name="introduction" maxlength="1000" rows="8"><?= htmlspecialchars($user['introduction'] ?? '') ?></textarea>
            <input type="hidden" name="introduction_submitted" value="1">
            <button type="submit" class="btn-primary">自己紹介文を更新</button>
        </form>
    </div>

    <div class="setting-card">
        <h2>アイコン画像の変更</h2>
        
        <div class="icon-wrapper">
            <?php if(empty($user['icon_filename'])): ?>
                <div class="icon-placeholder">
                    <span style="font-size: 3em;">👤</span><br>
                    現在未設定
                </div>
            <?php else: ?>
                <div>
                    <img src="/image/<?= htmlspecialchars($user['icon_filename']) ?>"
                        class="icon-current"
                        alt="現在のアイコン">
                </div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="input-file-group">
                <input type="file" accept="image/*" name="image" id="imageInput">
            </div>
            <input id="imageBase64Input" type="hidden" name="image_base64">
            <canvas id="imageCanvas" style="display: none;"></canvas>
            <button type="submit" class="btn-primary">アイコンをアップロード/変更</button>
        </form>
    </div>

    <ul class="back-link-list">
        <li><a href="/login_finish.php">← ログイン完了画面に戻る</a></li>
    </ul>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const imageInput = document.getElementById("imageInput");
    const imageBase64Input = document.getElementById("imageBase64Input");
    const canvas = document.getElementById("imageCanvas");

    imageInput.addEventListener("change", () => {
        if (imageInput.files.length < 1) {
            // 未選択の場合
            imageBase64Input.value = '';
            return;
        }
        const file = imageInput.files[0];
        if (!file.type.startsWith('image/')){ // 画像でなければスキップ
            return;
        }
        
        // 画像縮小処理
        const reader = new FileReader();
        const image = new Image();

        reader.onload = () => { // ファイルの読み込み完了したら動く処理を指定
            image.onload = () => { // 画像として読み込み完了したら動く処理を指定
                // 元の縦横比を保ったまま縮小するサイズを決めてcanvasの縦横に指定する
                const originalWidth = image.naturalWidth; // 元画像の横幅
                const originalHeight = image.naturalHeight; // 元画像の高さ
                const maxLength = 1000; // 横幅も高さも1000以下に縮小するものとする
                
                if (originalWidth <= maxLength && originalHeight <= maxLength) { // どちらもmaxLength以下の場合そのまま
                    canvas.width = originalWidth;
                    canvas.height = originalHeight;
                } else if (originalWidth > originalHeight) { // 横長画像の場合
                    canvas.width = maxLength;
                    canvas.height = maxLength * originalHeight / originalWidth;
                } else { // 縦長画像の場合
                    canvas.width = maxLength * originalWidth / originalHeight;
                    canvas.height = maxLength;
                }
                
                // canvasに実際に画像を描画 (canvasは display:none; で隠れている)
                const context = canvas.getContext("2d");
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                
                // canvasの内容をbase64に変換しinputのvalueに設定
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
