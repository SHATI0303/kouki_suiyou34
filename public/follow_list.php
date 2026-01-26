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

// 自分がフォローしている一覧をDBから引く。
$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS followee_user_name, users.icon_filename AS followee_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.followee_user_id = users.id'
  . ' WHERE user_relationships.follower_user_id = :follower_user_id'
  . ' ORDER BY user_relationships.id DESC'
);

// ★修正点: bindParam/bindValueを使用して、型を確実にINTEGERとして指定する
$login_user_id = (int)$_SESSION['login_user_id']; // IDを整数型にキャスト

$select_sth->bindValue(':follower_user_id', $login_user_id, PDO::PARAM_INT);
$select_sth->execute();

$following_users = $select_sth->fetchAll(PDO::FETCH_ASSOC); 
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フォロー中ユーザー一覧</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* ... スタイルは省略 ... */
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
        .followee-icon {
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
        .btn-unfollow-list { 
            color: #dc2626; 
            font-size: 0.8em; 
            padding: 5px 10px; 
            border: 1px solid #dc2626; 
            border-radius: 4px; 
            text-decoration: none;
            font-weight: 600;
            margin-left: 15px;
            transition: background-color 0.2s;
        }
        .btn-unfollow-list:hover {
            background-color: #dc2626;
            color: white;
            text-decoration: none;
        }
        .message-success {
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="main-container">
    <h1>👥 フォロー中のユーザー</h1>
    
    <?php if (!empty($_GET['unfollowed'])): ?>
        <div class="message-success">
            ✅ フォローを解除しました。
        </div>
    <?php endif; ?>

    <?php if (empty($following_users)): ?>
        <div class="empty-message">
            <p>まだ誰もフォローしていません。</p>
            <a href="/bbs.php">掲示板で新しいユーザーを探しましょう！</a>
        </div>
    <?php else: ?>
        <ul class="following-list">
            <?php foreach($following_users as $relationship): ?>
            <li class="following-list-item">
                <a href="/profile.php?user_id=<?= htmlspecialchars($relationship['followee_user_id']) ?>" class="user-link">
                    
                    <?php if(!empty($relationship['followee_user_icon_filename'])): ?>
                    <img src="/image/<?= htmlspecialchars($relationship['followee_user_icon_filename']) ?>"
                        class="followee-icon" alt="アイコン">
                    <?php endif; ?>

                    <div class="user-details">
                        <span class="user-name-id">
                            <?= htmlspecialchars($relationship['followee_user_name']) ?>
                            <span style="font-weight: normal; color: #6b7280;">(ID: <?= htmlspecialchars($relationship['followee_user_id']) ?>)</span>
                        </span>
                        <span class="follow-date">
                            <?= date('Y年m月d日', strtotime($relationship['created_at'])) ?>にフォロー開始
                        </span>
                    </div>
                </a>
                
                <a href="./unfollow.php?followee_user_id=<?= htmlspecialchars($relationship['followee_user_id']) ?>"
                   class="btn-unfollow-list">
                    💔 解除
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
