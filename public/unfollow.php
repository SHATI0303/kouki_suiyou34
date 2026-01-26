<?php
session_start();

// ログインしてなければログイン画面に飛ばす
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db;charset=utf8mb4', 'root', '');

// アンフォロー対象(フォローされる側)のデータを引く
$followee_user = null;
if (!empty($_GET['followee_user_id'])) {
    $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
    $select_sth->execute([
        ':id' => $_GET['followee_user_id'],
    ]);
    $followee_user = $select_sth->fetch();
}
if (empty($followee_user)) {
    header("HTTP/1.1 404 Not Found");
    print("そのようなユーザーIDの会員情報は存在しません");
    return;
}

// 現在のフォロー状態をDBから取得
$select_sth = $dbh->prepare(
    "SELECT * FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);
$select_sth->execute([
    ':followee_user_id' => $followee_user['id'], // フォローされる側(アンフォロー対象)
    ':follower_user_id' => $_SESSION['login_user_id'], // フォローする側はログインしている会員
]);
$relationship = $select_sth->fetch();

$is_following = !empty($relationship);

$delete_result = false;
if ($is_following && $_SERVER['REQUEST_METHOD'] == 'POST') { // フォロー関係があり、POSTした場合は解除処理を行う
    $delete_sth = $dbh->prepare(
        "DELETE FROM user_relationships WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
    );
    $delete_sth->execute([
        ':followee_user_id' => $followee_user['id'], 
        ':follower_user_id' => $_SESSION['login_user_id'],
    ]);
    
    // 削除後、フォロー一覧に戻る
    header("HTTP/1.1 303 See Other");
    header("Location: ./follow_list.php?unfollowed=1");
    return;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アンフォロー確認</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1>アンフォロー操作</h1>
        <button class="hamburger-button" id="hamburgerButton" aria-label="メニュー">☰</button>
    </div>
    <div class="page-nav" id="pageNav">
        <a href="/timeline.php">タイムライン</a>
        <a href="/users.php">会員一覧</a>
        <a href="/profile.php?user_id=<?= htmlspecialchars($followee_user['id']) ?>">プロフィール</a>
    </div>
    <div class="page-nav-overlay" id="pageNavOverlay"></div>
</div>

<div class="container">
    <div class="form-container" style="text-align: center;">

        <?php if (!$is_following): ?>
            <div class="error-message" style="background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5;">
                このユーザー（<?= htmlspecialchars($followee_user['name']) ?> さん）は現在フォローされていません。
            </div>
            <a href="/profile.php?user_id=<?= htmlspecialchars($followee_user['id']) ?>" class="btn-primary" style="display: inline-block; margin-top: 1em;">
                プロフィールに戻る
            </a>
            
        <?php else: ?>
            <div class="error-message" style="background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5;">
                本当に <?= htmlspecialchars($followee_user['name']) ?> さんのフォローを解除しますか?
            </div>
            <form method="POST">
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1em; margin-bottom: 1em; background-color: var(--danger-color);">
                    アンフォローする
                </button>
            </form>
            <a href="/profile.php?user_id=<?= htmlspecialchars($followee_user['id']) ?>" style="color: var(--primary-color); text-decoration: none;">
                キャンセル
            </a>
        <?php endif; ?>
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
