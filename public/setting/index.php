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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定画面</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1>設定画面</h1>
        <button class="hamburger-button" id="hamburgerButton" aria-label="メニュー">☰</button>
    </div>
    <div class="page-nav" id="pageNav">
        <a href="/timeline.php">タイムライン</a>
        <a href="/users.php">会員一覧</a>
        <a href="/profile.php?user_id=<?= $user['id'] ?>">プロフィール</a>
        <a href="/setting/index.php">設定</a>
        <a href="/logout.php">ログアウト</a>
    </div>
    <div class="page-nav-overlay" id="pageNavOverlay"></div>
</div>

<div class="container">
    <div class="post-form-card">
        <h2 style="margin-bottom: 1em;">現在の設定</h2>
        <dl style="margin-bottom: 2em;">
            <dt style="font-weight: 600; margin-bottom: 0.5em;">ID</dt>
            <dd style="margin-bottom: 1em; padding-left: 1em;"><?= htmlspecialchars($user['id']) ?></dd>
            <dt style="font-weight: 600; margin-bottom: 0.5em;">メールアドレス</dt>
            <dd style="margin-bottom: 1em; padding-left: 1em;"><?= htmlspecialchars($user['email']) ?></dd>
            <dt style="font-weight: 600; margin-bottom: 0.5em;">名前</dt>
            <dd style="margin-bottom: 1em; padding-left: 1em;"><?= htmlspecialchars($user['name']) ?></dd>
        </dl>
        
        <h3 style="margin-bottom: 1em;">設定項目</h3>
        <ul style="list-style: none; padding: 0;">
            <li style="margin-bottom: 0.5em;"><a href="./icon.php" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;">アイコン設定</a></li>
            <li style="margin-bottom: 0.5em;"><a href="./cover.php" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;">カバー画像設定</a></li>
            <li style="margin-bottom: 0.5em;"><a href="./birthday.php" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;">生年月日設定</a></li>
            <li style="margin-bottom: 0.5em;"><a href="./introduction.php" class="btn-primary" style="display: inline-block; width: 100%; text-align: center;">自己紹介文設定</a></li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hamburgerButton = document.getElementById('hamburgerButton');
  const pageNav = document.getElementById('pageNav');
  const pageNavOverlay = document.getElementById('pageNavOverlay');

  if (hamburgerButton && pageNav && pageNavOverlay) {
    hamburgerButton.addEventListener('click', () => {
      const isOpen = pageNav.classList.toggle('is-open');
      pageNavOverlay.classList.toggle('is-open', isOpen);
      hamburgerButton.textContent = isOpen ? '✕' : '☰';
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    pageNavOverlay.addEventListener('click', () => {
      pageNav.classList.remove('is-open');
      pageNavOverlay.classList.remove('is-open');
      hamburgerButton.textContent = '☰';
      document.body.style.overflow = '';
    });
  }
});
</script>

</body>
</html>
