<?php
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

# --- セッション処理の共通部分 (login.php から移動) ---
// セッションIDの取得(なければ新規で作成&設定)
$session_cookie_name = 'session_id';
$session_id = $_COOKIE[$session_cookie_name] ?? base64_encode(random_bytes(64));
if (!isset($_COOKIE[$session_cookie_name])) {
    setcookie($session_cookie_name, $session_id);
}
// 接続 (redisコンテナの6379番ポートに接続)
$redis = new Redis();
$redis->connect('redis', 6379);
// Redisにセッション変数を保存しておくキー
$redis_session_key = "session-" . $session_id;
// Redisからセッションのデータを読み込み
// 既にセッション変数(の配列)が何かしら格納されていればそれを，なければ空の配列を $session_values変数に保存
$session_values = $redis->exists($redis_session_key)
  ? json_decode($redis->get($redis_session_key), true)
  : [];

// セッションにログインIDが無ければ (=ログインされていない状態であれば) ログイン画面にリダイレクトさせる
if (empty($session_values['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./login.php");
  return;
}
# --------------------------------------------------------

// セッションにあるログインIDから、ログインしている対象の会員情報を引く
$select_user_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_user_sth->execute([
    ':id' => $session_values['login_user_id'],
]);
$user = $select_user_sth->fetch();

$error_message = '';
$success_message = '';

if (!empty($_POST['name'])) {
    // フォームが送信された場合の更新処理
    $new_name = $_POST['name'];

    // 2. DBの情報を更新
    $update_sth = $dbh->prepare("UPDATE users SET name = :name WHERE id = :id");
    $result = $update_sth->execute([
        ':name' => $new_name,
        ':id' => $user['id'], // ログイン中のユーザーID
    ]);

    if ($result) {
        // 更新成功
        $success_message = '名前を更新しました。';
        // 更新後の情報を再取得
        $user['name'] = $new_name; 
    } else {
        // 更新失敗
        $error_message = '更新に失敗しました。';
    }
}

// フォームに表示する名前 (更新処理後の最新の値)
$display_name = $user['name'] ?? '';

?>
<h1>会員情報編集 (名前)</h1>

<?php if (!empty($success_message)): ?>
<div style="color: blue; font-weight: bold;">
  <?= htmlspecialchars($success_message) ?>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div style="color: red; font-weight: bold;">
  <?= htmlspecialchars($error_message) ?>
</div>
<?php endif; ?>

<form method="POST">
  <label>
    現在のメールアドレス:
    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
  </label>
  <br>
  <label>
    新しい名前:
    <input type="text" name="name" value="<?= htmlspecialchars($display_name) ?>" required>
  </label>
  <br>
  <button type="submit">更新</button>
</form>

<hr>
<p><a href="./login_finish.php">戻る</a></p>


