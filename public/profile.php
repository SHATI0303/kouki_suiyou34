<?php
session_start();
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// 1. 表示対象のユーザー情報を取得
$user = null;
if (!empty($_GET['user_id'])) {
  $user_id = $_GET['user_id'];
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([':id' => $user_id]);
  $user = $select_sth->fetch();
}

if (empty($user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// 2. ログイン情報を変数にまとめておく
$login_user_id = $_SESSION['login_user_id'] ?? null;

// 3. この人の投稿データを取得
$post_select_sth = $dbh->prepare(
  'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE user_id = :user_id'
  . ' ORDER BY bbs_entries.created_at DESC'
);
$post_select_sth->execute([':user_id' => $user['id']]);

// 投稿に紐づく画像を取得する関数
function getEntryImages($dbh, $entry_id) {
  try {
    $images_sth = $dbh->prepare('SELECT image_filename FROM bbs_entry_images WHERE entry_id = :entry_id ORDER BY display_order ASC');
    $images_sth->execute([':entry_id' => $entry_id]);
    $images = $images_sth->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($images)) {
      return $images;
    }
  } catch (PDOException $e) {
    // テーブルが存在しない場合は無視
  }
  
  // 新しいテーブルにデータがない場合は、既存のimage_filenameカラムを確認
  $entry_sth = $dbh->prepare('SELECT image_filename FROM bbs_entries WHERE id = :entry_id');
  $entry_sth->execute([':entry_id' => $entry_id]);
  $entry = $entry_sth->fetch();
  if (!empty($entry['image_filename'])) {
    return [$entry['image_filename']];
  }
  
  return [];
}

// 4. フォロー状態を取得
$relationship = null;
$follower_relationship = null;
if ($login_user_id) {
  // 自分が相手をフォローしているか
  $select_sth = $dbh->prepare(
    "SELECT * FROM user_relationships WHERE follower_user_id = :follower AND followee_user_id = :followee"
  );
  $select_sth->execute([':followee' => $user['id'], ':follower' => $login_user_id]);
  $relationship = $select_sth->fetch();

  // 相手が自分をフォローしているか
  $select_sth->execute([':followee' => $login_user_id, ':follower' => $user['id']]);
  $follower_relationship = $select_sth->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']) ?>のプロフィール</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .profile-cover {
            width: 100%;
            height: 200px;
            background: <?= !empty($user['cover_filename']) ? "url('/image/{$user['cover_filename']}') center / cover" : "#ccc" ?>;
            border-radius: 8px 8px 0 0;
        }
        .profile-info {
            position: relative;
            padding: 2em;
            background-color: var(--bg-color);
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2em;
        }
        .profile-avatar-section {
            display: flex;
            align-items: flex-end;
            margin-top: -3.5em;
            margin-bottom: 1em;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border: 3px solid white;
            border-radius: 50%;
            overflow: hidden;
            background: #eee;
            flex-shrink: 0;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-name {
            margin-left: 1em;
            font-size: 1.8em;
            font-weight: bold;
        }
        .profile-actions {
            margin-bottom: 1.5em;
        }
        .profile-actions a {
            display: inline-block;
            padding: 0.6em 1.2em;
            margin-right: 0.5em;
            margin-bottom: 0.5em;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
        .btn-edit {
            background: #6c757d;
            color: white;
        }
        .btn-follow {
            background: var(--primary-color);
            color: white;
        }
        .btn-unfollow {
            color: var(--danger-color);
            font-size: 0.9em;
        }
        .profile-details {
            margin-bottom: 2em;
        }
        .profile-detail-item {
            margin-bottom: 0.5em;
            font-size: 1.1em;
        }
        .profile-introduction {
            white-space: pre-wrap;
            line-height: 1.8;
            padding: 1.5em;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 2em;
        }
        .posts-section h3 {
            margin-bottom: 1.5em;
            font-size: 1.5em;
        }
        .post-item {
            margin-bottom: 2em;
            padding: 1.5em;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-color);
        }
        .post-date {
            color: var(--text-light);
            font-size: 0.85em;
            margin-bottom: 0.5em;
        }
        .post-body {
            font-size: 1.05em;
            margin-bottom: 1em;
            line-height: 1.8;
            word-wrap: break-word;
        }
        .post-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5em;
            margin-top: 1em;
        }
        .post-images img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1><?= htmlspecialchars($user['name']) ?>のプロフィール</h1>
        <button class="hamburger-button" id="hamburgerButton" aria-label="メニュー">☰</button>
    </div>
    <div class="page-nav" id="pageNav">
        <a href="/timeline.php">タイムライン</a>
        <a href="/users.php">会員一覧</a>
        <?php if ($login_user_id): ?>
            <a href="/profile.php?user_id=<?= $login_user_id ?>">プロフィール</a>
            <?php if ($login_user_id == $user['id']): ?>
                <a href="/setting/index.php">設定</a>
            <?php endif; ?>
            <a href="/logout.php">ログアウト</a>
        <?php endif; ?>
    </div>
    <div class="page-nav-overlay" id="pageNavOverlay"></div>
</div>

<div class="container">
  <div>
    <div class="profile-cover"></div>
    <div class="profile-info">
      <div class="profile-avatar-section">
        <div class="profile-avatar">
          <?php if(empty($user['icon_filename'])): ?>
            <div style="height: 100%; width: 100%; display: flex; justify-content: center; align-items: center; color: #888; font-size: 0.8em;">アイコン未設定</div>
          <?php else: ?>
            <img src="/image/<?= $user['icon_filename'] ?>" alt="<?= htmlspecialchars($user['name']) ?>">
          <?php endif; ?>
        </div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
      </div>

      <div class="profile-actions">
        <?php if ($login_user_id == $user['id']): ?>
          <a href="/setting/index.php" class="btn-edit">プロフィールを編集する</a>
        
        <?php elseif ($login_user_id): ?>
          <?php if(empty($relationship)): ?>
            <a href="./follow.php?followee_user_id=<?= $user['id'] ?>" class="btn-follow">フォローする</a>
          <?php else: ?>
            <span style="color: gray; margin-right: 0.5em;">[フォロー中] <?= htmlspecialchars(date('Y/m/d', strtotime($relationship['created_at']))) ?> にフォローしました</span>
            <a href="./unfollow.php?followee_user_id=<?= $user['id'] ?>" class="btn-unfollow">フォロー解除</a>
          <?php endif; ?>

          <?php if(!empty($follower_relationship)): ?>
            <span style="margin-left: 1em; padding: 4px 8px; background: #e9ecef; font-size: 0.8em; border-radius: 4px; color: #495057;">フォローされています</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="profile-details">
        <div class="profile-detail-item">
          <?php if(!empty($user['birthday'])): ?>
            <?php
              $birthday = DateTime::createFromFormat('Y-m-d', $user['birthday']);
              $today = new DateTime('now');
              $age = $today->diff($birthday)->y;
            ?>
            生年月日: <?= $birthday->format('Y年m月d日') ?> (<?= $age ?>歳)
          <?php else: ?>
            生年月日: 未設定
          <?php endif; ?>
        </div>
      </div>

      <div class="profile-introduction">
        <?= nl2br(htmlspecialchars($user['introduction'] ?? '自己紹介はまだありません。')) ?>
      </div>
    </div>
  </div>

  <div class="posts-section">
    <h3>投稿一覧</h3>
    <?php foreach($post_select_sth as $entry): 
      $entry_images = getEntryImages($dbh, $entry['id']);
    ?>
      <div class="post-item">
        <div class="post-date">投稿日時: <?= $entry['created_at'] ?></div>
        <div class="post-body"><?= nl2br(htmlspecialchars($entry['body'])) ?></div>
        <?php if(!empty($entry_images)): ?>
          <div class="post-images">
            <?php foreach($entry_images as $image_filename): ?>
              <img src="/image/<?= htmlspecialchars($image_filename) ?>" alt="投稿画像" onclick="window.open(this.src, '_blank')">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($post_select_sth->rowCount() === 0): ?>
      <p style="color: #999; text-align: center; padding: 2em;">まだ投稿がありません。</p>
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
