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

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['birthday'])) {
    $new_birthday = trim($_POST['birthday']);

    // データベースの更新
    $update_sth = $dbh->prepare("
        UPDATE users SET birthday = :birthday WHERE id = :id
    ");

    if ($update_sth->execute([':id' => $user['id'], ':birthday' => $new_birthday])) {
        $message = ['type' => 'success', 'text' => '生年月日が更新されました。'];
        // ユーザー情報を再取得して最新の状態を反映
        $user['birthday'] = $new_birthday;
    } else {
        $message = ['type' => 'error', 'text' => '更新に失敗しました。'];
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生年月日設定</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* メッセージタイプ別のスタイル（style.cssの既存クラスを拡張） */
        .message-success {
            background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
            padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;
        }
        .message-error {
            background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
            padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;
        }
    </style>
</head>
<body>

<div class="main-container">

    <h1>🎂 生年月日設定</h1>
    
    <?php if ($message): ?>
        <div class="<?= $message['type'] === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <div class="setting-card">
        <form method="POST">
            <div class="form-group">
                <label for="birthday" class="form-label">生年月日</label>
                <input 
                    type="date" 
                    id="birthday" 
                    name="birthday" 
                    class="form-input"
                    value="<?= htmlspecialchars($user['birthday'] ?? '') ?>"
                    required
                >
            </div>
            
            <button type="submit" class="btn-primary" style="width: auto;">生年月日を更新</button>
        </form>
    </div>

    <ul class="back-link-list">
        <li><a href="./index.php">← 設定一覧に戻る</a></li>
    </ul>

</div>
</body>
</html>
