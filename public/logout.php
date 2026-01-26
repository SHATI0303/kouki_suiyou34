<?php
// セッション設定を再度読み込む (Redis設定)
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');

// 1. セッションを開始
session_start();

// 既にセッションが開始されていない場合は、何もせずリダイレクトしても良いが、ここでは念のため処理を行う

// 2. セッション変数を全てクリア
$_SESSION = [];

// 3. セッションクッキーを削除
// セッションIDを格納しているクッキーを有効期限切れにする
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. セッションファイルを破棄
session_destroy();

// 5. ログインページへリダイレクト
header("Location: ./login.php");
exit;
?>

