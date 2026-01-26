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

// 自分をフォローしている人（フォロワー）の一覧をDBから引く。
$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.follower_user_id = users.id' // フォロワーのユーザー情報を取得
  . ' WHERE user_relationships.followee_user_id = :followee_user_id' // 自分がフォローされている関係を絞り込み
  . ' ORDER BY user_relationships.id DESC'
);

// IDを整数型にキャストし、PDOで安全にバインド
$login_user_id = (int)$_SESSION['login_user_id'];
$select_sth->bindValue(':followee_user_id', $login_user_id, PDO::PARAM_INT);
$select_sth->execute();

$follower_users = $select_sth->fetchAll(PDO::FETCH_ASSOC); 
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フォロワー一覧</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* follow_list.phpと共通のスタイルを適用 */
        .following-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .following-list-item {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            display: flex; 
            justify-content: space-between;
            align-items: center;
        }
        .user-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            flex-grow: 1; 
        }
        .follower-icon { /* followee-iconを流用し、名前を変更 */
            height: 2.5em; 
            width: 2.5em; 
            border-radius: 50%; 
            object-fit: cover;
            margin-right: 15px;
            border: 1px solid #ccc;
        }
        .user-details {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .follow-date {
            font-size: 0.8em;
            color: #6b7280;
            margin-top: 2px;
            font-weight: normal;
        }
        .user-name-id {
            display: block;
            font-size: 1rem;
            color: var(--primary-color);
        }
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            border: 1px dashed var(--border-color);
            border-radius: 8px;
        }
        /* フォローバックボタン用のスタイル（今回は表示のみのためボタンはなし） */
        .btn-followback { 
            color: #10b981; 
            font-size: 0.8em; 
            padding: 5px 10px; 
            border: 1px solid #10b981; 
            border-radius: 4px; 
            text-decoration: none;
            font-weight: 600;
            margin-left: 15px;
            transition: background-color 0.2s;
        }
        .btn-followback:hover {
            background-color: #10b981;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="main-container">
    <h1>👤 フォロワー一覧</h1>
    
    <?php if (empty($follower_users)): ?>
        <div class="empty-message">
            <p>まだフォロワーはいません。</p>
            <a href="/bbs.php">まずは投稿して注目を集めましょう！</a>
        </div>
    <?php else: ?>
        <ul class="following-list">
            <?php foreach($follower_users as $relationship): ?>
            <li class="following-list-item">
                <a href="/profile.php?user_id=<?= htmlspecialchars($relationship['follower_user_id']) ?>" class="user-link">
                    
                    <?php if(!empty($relationship['follower_user_icon_filename'])): ?>
                    <img src="/image/<?= htmlspecialchars($relationship['follower_user_icon_filename']) ?>"
                        class="follower-icon" alt="アイコン">
                    <?php endif; ?>

                    <div class="user-details">
                        <span class="user-name-id">
                            <?= htmlspecialchars($relationship['follower_user_name']) ?>
                            <span style="font-weight: normal; color: #6b7280;">(ID: <?= htmlspecialchars($relationship['follower_user_id']) ?>)</span>
                        </span>
                        <span class="follow-date">
                            <?= date('Y年m月d日', strtotime($relationship['created_at'])) ?>にフォローされました
                        </span>
                    </div>
                </a>
                
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <div class="back-link-list" style="margin-top: 30px;">
        <li><a href="/bbs.php">← 掲示板に戻る</a></li>
    </div>

</div>

</body>
</html>
