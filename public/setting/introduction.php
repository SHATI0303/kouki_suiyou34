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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['introduction'])) {
    $new_introduction = trim($_POST['introduction']);

    // データベースの更新
    $update_sth = $dbh->prepare("
        UPDATE users SET introduction = :introduction WHERE id = :id
    ");

    if ($update_sth->execute([':id' => $user['id'], ':introduction' => $new_introduction])) {
        $message = ['type' => 'success', 'text' => '自己紹介文が更新されました。'];
        // ユーザー情報を再取得して最新の状態を反映
        $user['introduction'] = $new_introduction;
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
    <title>自己紹介文設定</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .setting-card {
            background-color: var(--bg-color);
            padding: 2em;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2em;
        }
        .back-link-list {
            list-style: none;
            padding: 0;
        }
        .back-link-list li {
            margin-bottom: 0.5em;
        }
        .back-link-list a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link-list a:hover {
            text-decoration: underline;
        }
        textarea.form-input {
            min-height: 150px;
            font-family: inherit;
            resize: vertical;
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1>自己紹介文設定</h1>
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
    <?php if ($message): ?>
        <div class="<?= $message['type'] === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <div class="setting-card">
        <form method="POST">
            <div class="form-group">
                <label for="introduction" class="form-label">自己紹介文</label>
                <textarea 
                    id="introduction" 
                    name="introduction" 
                    class="form-input"
                    maxlength="1000"
                    placeholder="自己紹介文を入力してください（最大1000文字）"
                ><?= htmlspecialchars($user['introduction'] ?? '') ?></textarea>
                <small style="color: var(--text-light);">最大1000文字まで入力できます。</small>
            </div>
            
            <button type="submit" class="btn-primary">自己紹介文を更新</button>
        </form>
    </div>

    <ul class="back-link-list">
        <li><a href="./index.php">← 設定一覧に戻る</a></li>
    </ul>
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
