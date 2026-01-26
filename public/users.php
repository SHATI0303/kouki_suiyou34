<?php
session_start();
// 1. データベース接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

$login_user_id = $_SESSION['login_user_id'] ?? null;

// --- 検索処理 ---
$search_name = $_GET['name'] ?? '';
$year_from = $_GET['year_from'] ?? '';
$year_to = $_GET['year_to'] ?? '';

// ベースとなるSQL
$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];

// 名前の部分一致
if (!empty($search_name)) {
    $sql .= ' AND name LIKE :name';
    $params[':name'] = '%' . $search_name . '%';
}

// 生まれ年（birthdayカラムから年を抽出）の範囲指定
if (!empty($year_from)) {
    $sql .= ' AND YEAR(birthday) >= :year_from';
    $params[':year_from'] = intval($year_from);
}
if (!empty($year_to)) {
    $sql .= ' AND YEAR(birthday) <= :year_to';
    $params[':year_to'] = intval($year_to);
}

$sql .= ' ORDER BY id DESC';

$select_sth = $dbh->prepare($sql);
$select_sth->execute($params);
$users = $select_sth->fetchAll();
// ----------------

// フォロー状態チェック用の準備
$check_follow_sth = $dbh->prepare('SELECT created_at FROM user_relationships WHERE follower_user_id = :login_id AND followee_user_id = :target_id');
$check_follower_sth = $dbh->prepare('SELECT created_at FROM user_relationships WHERE follower_user_id = :target_id AND followee_user_id = :login_id');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員一覧</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .users-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 1em;
        }
        .users-header {
            background-color: white;
            padding: 1.5em;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5em;
        }
        .users-header h1 {
            margin: 0 0 1em 0;
        }
        .users-nav {
            display: flex;
            gap: 1em;
            flex-wrap: wrap;
        }
        .users-nav a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .users-nav a:hover {
            text-decoration: underline;
        }
        .search-form {
            background-color: white;
            padding: 1.5em;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5em;
        }
        .search-form fieldset {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1em;
        }
        .search-form legend {
            font-weight: 600;
            padding: 0 0.5em;
        }
        .search-form-group {
            margin-bottom: 1em;
        }
        .search-form-group label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 500;
        }
        .search-form-group input {
            padding: 0.5em;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        .search-form-actions {
            display: flex;
            gap: 1em;
            align-items: center;
            flex-wrap: wrap;
        }
        .user-list {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .user-item {
            display: flex;
            align-items: center;
            padding: 1.5em;
            border-bottom: 1px solid var(--border-color);
            gap: 1em;
        }
        .user-item:last-child {
            border-bottom: none;
        }
        .user-item:hover {
            background-color: #f8f9fa;
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #eee;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info {
            flex-grow: 1;
        }
        .user-name {
            font-weight: 600;
            margin-bottom: 0.25em;
        }
        .user-name a {
            text-decoration: none;
            color: var(--primary-color);
        }
        .user-name a:hover {
            text-decoration: underline;
        }
        .user-birthday {
            font-size: 0.9em;
            color: var(--text-light);
        }
        .user-actions {
            text-align: right;
            flex-shrink: 0;
        }
        .user-actions .btn-follow {
            display: inline-block;
            padding: 0.5em 1em;
            text-decoration: none;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 4px;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        .user-actions .btn-follow:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .user-status {
            font-size: 0.8em;
            color: var(--text-light);
            margin-top: 0.25em;
        }
        .user-status.following {
            color: #888;
        }
        .user-status.followed {
            display: inline-block;
            padding: 2px 6px;
            background: #f0f0f0;
            border-radius: 4px;
            margin-top: 0.5em;
        }
        .empty-message {
            text-align: center;
            padding: 3em;
            color: var(--text-light);
        }
        @media (max-width: 768px) {
            .users-container {
                padding: 0.5em;
            }
            .user-item {
                flex-wrap: wrap;
            }
            .user-actions {
                width: 100%;
                text-align: left;
                margin-top: 0.5em;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1>会員一覧</h1>
        <button class="hamburger-button" id="hamburgerButton" aria-label="メニュー">☰</button>
    </div>
    <div class="page-nav" id="pageNav">
        <a href="/timeline.php">タイムライン</a>
        <a href="/users.php">会員一覧</a>
        <?php if ($login_user_id): ?>
            <a href="/profile.php?user_id=<?= $login_user_id ?>">プロフィール</a>
            <a href="/setting/index.php">設定</a>
            <a href="/logout.php">ログアウト</a>
        <?php endif; ?>
    </div>
    <div class="page-nav-overlay" id="pageNavOverlay"></div>
</div>

<div class="container">

  <div class="search-form">
    <fieldset>
      <legend>会員検索</legend>
      <form action="./users.php" method="GET">
        <div class="search-form-group">
          <label for="name">名前:</label>
          <input type="text" id="name" name="name" placeholder="名前で検索" value="<?= htmlspecialchars($search_name) ?>">
        </div>
        <div class="search-form-group">
          <label>生まれ年:</label>
          <input type="number" name="year_from" placeholder="1990" value="<?= htmlspecialchars($year_from) ?>" style="width: 100px;"> 年 〜 
          <input type="number" name="year_to" placeholder="2000" value="<?= htmlspecialchars($year_to) ?>" style="width: 100px;"> 年
        </div>
        <div class="search-form-actions">
          <button type="submit" class="btn-primary">検索実行</button>
          <a href="./users.php" style="font-size: 0.9em; color: var(--text-light);">検索をクリア</a>
        </div>
      </form>
    </fieldset>
  </div>

  <div class="user-list">
    <?php if (empty($users)): ?>
      <div class="empty-message">
        該当する会員が見つかりませんでした。
      </div>
    <?php else: ?>
      <div style="padding: 1em; background-color: #f8f9fa; border-bottom: 1px solid var(--border-color);">
        <strong><?= count($users) ?> 名の会員が表示されています。</strong>
      </div>
      
      <?php foreach($users as $user): ?>
        <div class="user-item">
          <div class="user-avatar">
            <?php if(!empty($user['icon_filename'])): ?>
              <img src="/image/<?= htmlspecialchars($user['icon_filename']) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
            <?php endif; ?>
          </div>

          <div class="user-info">
            <div class="user-name">
              <a href="/profile.php?user_id=<?= $user['id'] ?>">
                <?= htmlspecialchars($user['name']) ?>
              </a>
            </div>
            <div class="user-birthday">
              <?php if (!empty($user['birthday'])): ?>
                生まれ年: <?= htmlspecialchars(date('Y', strtotime($user['birthday']))) ?> 年
              <?php else: ?>
                生まれ年: 未設定
              <?php endif; ?>
            </div>
          </div>

          <div class="user-actions">
            <?php if($login_user_id && $user['id'] == $login_user_id): ?>
              <div style="font-size: 0.8em; color: var(--success-color); font-weight: 600;">
                あなた
              </div>
            <?php elseif($login_user_id): ?>
              <?php
                $check_follow_sth->execute([':login_id' => $login_user_id, ':target_id' => $user['id']]);
                $relationship = $check_follow_sth->fetch();
                $check_follower_sth->execute([':login_id' => $login_user_id, ':target_id' => $user['id']]);
                $follower_relationship = $check_follower_sth->fetch();
              ?>

              <?php if(empty($relationship)): ?>
                <a href="./follow.php?followee_user_id=<?= $user['id'] ?>" class="btn-follow">フォローする</a>
              <?php else: ?>
                <div class="user-status following">
                  フォロー中 (<?= htmlspecialchars(date('Y/m/d', strtotime($relationship['created_at']))) ?>)
                </div>
              <?php endif; ?>

              <?php if(!empty($follower_relationship)): ?>
                <div class="user-status followed">
                  フォローされています
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
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
