<?php
session_start();

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (!empty($_POST['email']) && !empty($_POST['password'])) {
  // POSTで email と password が送られてきた場合のみログイン処理をする
  // email から会員情報を引く
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();

  if (empty($user)) {
    // 入力されたメールアドレスに該当する会員が見つからなければ、処理を中断しエラー用クエリパラメータ付きのログイン画面URLにリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  // パスワードが正しいかチェック
  $correct_password = password_verify($_POST['password'], $user['password']);

  if (!$correct_password) {
    // パスワードが間違っていれば、処理を中断しエラー用クエリパラメータ付きのログイン画面URLにリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  // セッションにログインIDを保存
  $_SESSION["login_user_id"] = $user['id'];

  // ログインが成功したらログイン完了画面にリダイレクト
  header("HTTP/1.1 303 See Other");
  header("Location: ./setting/index.php");
  return;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="form-container">
    <div class="link-text">
        初めての人は<a href="/signup.php">会員登録</a>しましょう。
    </div>
    
    <h1>ログイン</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="email" class="form-label">メールアドレス:</label>
            <input type="email" id="email" name="email" class="form-input" required>
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">パスワード:</label>
            <input type="password" id="password" name="password" minlength="6" class="form-input" required>
        </div>
        
        <button type="submit" class="btn-primary">決定</button>
    </form>
    
    <?php if(!empty($_GET['error'])): ?>
    <div class="error-message">
        メールアドレスかパスワードが間違っています。
    </div>
    <?php endif; ?>
</div>

</body>
</html>
