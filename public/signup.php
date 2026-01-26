<?php
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['password'])) {
  // POSTで name と email と password が送られてきた場合はDBへの登録処理をする

  // 既に同じメールアドレスで登録された会員が存在しないか確認する
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();
  if (!empty($user)) {
    // 存在した場合 エラー用のクエリパラメータ付き会員登録画面にリダイレクトする
    header("HTTP/1.1 303 See Other");
    header("Location: ./signup.php?duplicate_email=1");
    return;
  }

  // insertする
  $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  
  $insert_sth = $dbh->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
  $insert_sth->execute([
    ':name' => $_POST['name'],
    ':email' => $_POST['email'],
    ':password' => $hashed_password,
  ]);
  // 処理が終わったら完了画面にリダイレクト
  header("HTTP/1.1 303 See Other");
  header("Location: ./signup_finish.php");
  return;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員登録</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="form-container">
    <div class="link-text">
        会員登録済の人は<a href="/login.php">ログイン</a>しましょう。
    </div>
    
    <h1>会員登録</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="name" class="form-label">名前:</label>
            <input type="text" id="name" name="name" class="form-input" required>
        </div>

        <div class="form-group">
            <label for="email" class="form-label">メールアドレス:</label>
            <input type="email" id="email" name="email" class="form-input" required>
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">パスワード:</label>
            <input type="password" id="password" name="password" minlength="6" autocomplete="new-password" class="form-input" required>
        </div>
        
        <button type="submit" class="btn-primary">決定</button>
    </form>

    <?php if(!empty($_GET['duplicate_email'])): ?>
    <div class="error-message">
        入力されたメールアドレスは既に使われています。
    </div>
    <?php endif; ?>
</div>

</body>
</html>
