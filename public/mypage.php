<?php
// セッションハンドラをRedisに設定
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');

// セッションを開始
session_start();

// ログイン状態のチェック
if (empty($_SESSION['user_id'])) {
    header("Location: ./login.php");
    exit;
}

// セッションからユーザー情報を取得（Null合体演算子で未定義の場合も安全に処理）
$userId = htmlspecialchars($_SESSION['user_id'] ?? 'N/A');
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'ゲスト');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? 'N/A');

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マイページ</title>
</head>
<body>
    <h1>マイページ</h1>
    
    <p>ようこそ、**<?php echo $userName; ?>** さん。</p>
    
    <h2>登録情報</h2>
    <ul>
        <li>**会員ID:** <?php echo $userId; ?></li>
        <li>**名前:** <?php echo $userName; ?></li>
        <li>**メールアドレス:** <?php echo $userEmail; ?></li>
    </ul>

    <hr>
    
    <p>
        <a href="./logout.php">**ログアウト**</a>
    </p>
</body>
</html>


