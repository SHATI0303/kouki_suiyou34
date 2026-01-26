<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db','root','');

session_start();
if(empty($_SESSION['login_user_id'])){
  header("HTTP/1.1 401 Unauthorized");
  header("Content-Type: application/json");
  print(json_encode(['entries' => [], 'has_more' => false]));
  return;
}

$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// ページネーション用のパラメータを取得
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // デフォルトは10件

// LIMITとOFFSETは整数として直接SQLに埋め込む（PDOのバインディングでは動作しないため）
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries'
  . ' INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE'
  . '   bbs_entries.user_id IN'
  . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)'
  . '   OR bbs_entries.user_id = :login_user_id'
  . ' ORDER BY bbs_entries.created_at DESC'
  . ' LIMIT ' . intval($limit) . ' OFFSET ' . intval($offset);
$select_sth = $dbh->prepare($sql);
$select_sth->execute([
  ':login_user_id' => $_SESSION['login_user_id'],
]);

// 次のページがあるかチェック（limit+1件取得して判定）
$check_sql = 'SELECT COUNT(*) as count FROM bbs_entries'
  . ' WHERE'
  . '   bbs_entries.user_id IN'
  . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)'
  . '   OR bbs_entries.user_id = :login_user_id';
$check_sth = $dbh->prepare($check_sql);
$check_sth->execute([':login_user_id' => $_SESSION['login_user_id']]);
$total_count = $check_sth->fetch()['count'];
$has_more = ($offset + $limit) < $total_count;

// bodyのHTMLを出力するための関数を用意する
function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); // エスケープ処理
  $body = nl2br($body); // 改行文字を<br>要素に変換

  return $body;
}

// 投稿に紐づく画像を取得する関数
function getEntryImages($dbh, $entry_id) {
  // まず新しいテーブル（bbs_entry_images）から取得を試みる
  try {
    $images_sth = $dbh->prepare('SELECT image_filename FROM bbs_entry_images WHERE entry_id = :entry_id ORDER BY display_order ASC');
    $images_sth->execute([':entry_id' => $entry_id]);
    $images = $images_sth->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($images)) {
      return array_map(function($filename) {
        return '/image/' . $filename;
      }, $images);
    }
  } catch (PDOException $e) {
    // テーブルが存在しない場合は無視
  }
  
  // 新しいテーブルにデータがない場合は、既存のimage_filenameカラムを確認
  $entry_sth = $dbh->prepare('SELECT image_filename FROM bbs_entries WHERE id = :entry_id');
  $entry_sth->execute([':entry_id' => $entry_id]);
  $entry = $entry_sth->fetch();
  if (!empty($entry['image_filename'])) {
    return ['/image/' . $entry['image_filename']];
  }
  
  return [];
}

// JSONに吐き出す用のentries
$result_entries = [];
foreach ($select_sth as $entry) {
  $image_urls = getEntryImages($dbh, $entry['id']);
  
  $result_entry = [
    'id' => $entry['id'],
    'user_name' => $entry['user_name'],
    'user_icon_url' =>!empty($entry['user_icon_filename']) ? '/image/' .$entry['user_icon_filename'] : null,
    'user_profile_url' => '/profile.php?user_id=' . $entry['user_id'],
    'body' => bodyFilter($entry['body']),
    'image_urls' => $image_urls, // 複数画像に対応
    'image_url' => !empty($image_urls) ? $image_urls[0] : null, // 後方互換性のため
    'created_at' => $entry['created_at'],
  ];
  $result_entries[] = $result_entry;
}

header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
print(json_encode(['entries' => $result_entries, 'has_more' => $has_more]));

