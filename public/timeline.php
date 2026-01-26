<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// 投稿処理（複数画像対応）
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {
  $image_base64_array = [];
  if (!empty($_POST['image_base64_array'])) {
    $image_base64_array = json_decode($_POST['image_base64_array'], true);
    if (!is_array($image_base64_array)) {
      $image_base64_array = [];
    }
  }
  
  $image_base64_array = array_slice($image_base64_array, 0, 4);

  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
  $insert_sth->execute([
    ':user_id' => $_SESSION['login_user_id'],
    ':body' => $_POST['body'],
    ':image_filename' => null,
  ]);
  
  $entry_id = $dbh->lastInsertId();

  if (!empty($image_base64_array)) {
    // テーブルが存在するか確認
    $table_exists = false;
    try {
      $check_table_sth = $dbh->query("SHOW TABLES LIKE 'bbs_entry_images'");
      $table_exists = $check_table_sth->rowCount() > 0;
    } catch (PDOException $e) {
    }

    // テーブルが存在しない場合は作成
    if (!$table_exists) {
      try {
        // FOREIGN KEY制約なしでテーブルを作成
        $create_table_sql = "
          CREATE TABLE bbs_entry_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_id INT NOT NULL,
            image_filename VARCHAR(255) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entry_id (entry_id)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $dbh->exec($create_table_sql);
      } catch (PDOException $e) {
        // テーブル作成エラーを無視（既に存在する可能性がある）
      }
    }

    // 画像を保存
    try {
      $image_insert_sth = $dbh->prepare("INSERT INTO bbs_entry_images (entry_id, image_filename, display_order) VALUES (:entry_id, :image_filename, :display_order)");
      
      foreach ($image_base64_array as $index => $base64_data) {
        if (empty($base64_data)) continue;
        
        $base64 = preg_replace('/^data:.+base64,/', '', $base64_data);
        $image_binary = base64_decode($base64);
        
        if ($image_binary === false) continue;
        
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '_' . $index . '.png';
        $filepath = '/var/www/upload/image/' . $image_filename;
        file_put_contents($filepath, $image_binary);
        
        $image_insert_sth->execute([
          ':entry_id' => $entry_id,
          ':image_filename' => $image_filename,
          ':display_order' => $index,
        ]);
      }
    } catch (PDOException $e) {
      // 画像保存エラーをログに記録（本番環境では適切に処理）
      error_log("画像保存エラー: " . $e->getMessage());
    }
  }

  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline.php");
  return;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タイムライン</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="page-header">
    <div class="page-header-top">
        <h1>タイムライン</h1>
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
        <form method="POST" action="./timeline.php" id="postForm" class="post-form">
            <textarea name="body" placeholder="今何してる？" required id="postTextarea"></textarea>
            <div class="image-input-area">
                <input type="file" accept="image/*" id="imageInput" multiple>
                <small style="color: var(--text-light);">最大4枚まで選択できます</small>
                <div class="image-preview-container" id="imagePreviewContainer"></div>
            </div>
            <div class="post-form-actions">
                <div>
                    <button type="button" class="btn-primary" onclick="document.getElementById('imageInput').click()">画像を選択</button>
                </div>
                <button type="submit" id="submitButton" class="btn-submit">投稿する</button>
            </div>
            <input id="imageBase64ArrayInput" type="hidden" name="image_base64_array">
            <canvas id="imageCanvas" style="display: none;"></canvas>
        </form>
    </div>

    <div id="entriesRenderArea"></div>
    <div id="loadingIndicator" class="loading-indicator hidden">読み込み中...</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const entriesRenderArea = document.getElementById('entriesRenderArea');
  const loadingIndicator = document.getElementById('loadingIndicator');
  let currentOffset = 0;
  const limit = 10;
  let isLoading = false;
  let hasMore = true;

  function renderEntry(entry) {
    const entryDiv = document.createElement('div');
    entryDiv.className = 'timeline-entry';
    
    const headerDiv = document.createElement('div');
    headerDiv.className = 'timeline-entry-header';
    
    const iconImg = document.createElement('img');
    iconImg.className = 'timeline-entry-icon';
    iconImg.style.width = '24px';
    iconImg.style.height = '24px';
    iconImg.style.borderRadius = '50%';
    iconImg.style.objectFit = 'cover';
    iconImg.style.flexShrink = '0';
    if (entry.user_icon_url) {
      iconImg.src = entry.user_icon_url;
      iconImg.alt = entry.user_name;
    }
    
    const userDiv = document.createElement('div');
    userDiv.className = 'timeline-entry-user';
    const userLink = document.createElement('a');
    userLink.href = entry.user_profile_url;
    userLink.textContent = entry.user_name;
    userDiv.appendChild(userLink);
    
    const dateDiv = document.createElement('div');
    dateDiv.className = 'timeline-entry-date';
    dateDiv.textContent = entry.created_at;
    
    headerDiv.appendChild(iconImg);
    headerDiv.appendChild(userDiv);
    headerDiv.appendChild(dateDiv);
    
    const bodyDiv = document.createElement('div');
    bodyDiv.className = 'timeline-entry-body';
    bodyDiv.innerHTML = entry.body;
    
    entryDiv.appendChild(headerDiv);
    entryDiv.appendChild(bodyDiv);
    
    if (entry.image_urls && entry.image_urls.length > 0) {
      const imagesDiv = document.createElement('div');
      imagesDiv.className = 'timeline-entry-images';
      
      entry.image_urls.forEach(imageUrl => {
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = '投稿画像';
        img.onclick = () => window.open(imageUrl, '_blank');
        imagesDiv.appendChild(img);
      });
      
      entryDiv.appendChild(imagesDiv);
    } else if (entry.image_url) {
      const imagesDiv = document.createElement('div');
      imagesDiv.className = 'timeline-entry-images';
      const img = document.createElement('img');
      img.src = entry.image_url;
      img.alt = '投稿画像';
      img.onclick = () => window.open(entry.image_url, '_blank');
      imagesDiv.appendChild(img);
      entryDiv.appendChild(imagesDiv);
    }
    
    entriesRenderArea.appendChild(entryDiv);
  }

  function loadEntries() {
    if (isLoading || !hasMore) return;
    
    isLoading = true;
    loadingIndicator.classList.remove('hidden');
    
    const request = new XMLHttpRequest();
    request.onload = (event) => {
      isLoading = false;
      loadingIndicator.classList.add('hidden');
      
      if (request.status === 200) {
        const response = event.target.response;
        response.entries.forEach(renderEntry);
        hasMore = response.has_more;
        currentOffset += response.entries.length;
      }
    };
    
    request.onerror = () => {
      isLoading = false;
      loadingIndicator.classList.add('hidden');
    };
    
    request.open('GET', `/timeline_json.php?offset=${currentOffset}&limit=${limit}`, true);
    request.responseType = 'json';
    request.send();
  }

  loadEntries();

  let scrollTimeout;
  window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      const windowHeight = window.innerHeight;
      const documentHeight = document.documentElement.scrollHeight;
      
      if (scrollTop + windowHeight >= documentHeight - 100) {
        loadEntries();
      }
    }, 100);
  });

  // 画像処理
  const imageInput = document.getElementById("imageInput");
  const imagePreviewContainer = document.getElementById("imagePreviewContainer");
  const imageBase64ArrayInput = document.getElementById("imageBase64ArrayInput");
  const canvas = document.getElementById("imageCanvas");
  const submitButton = document.getElementById("submitButton");
  let imageBase64Array = [];
  let imagePreviews = [];

  function processImage(file, index) {
    return new Promise((resolve, reject) => {
      if (!file.type.startsWith('image/')) {
        resolve(null);
        return;
      }

      const reader = new FileReader();
      const image = new Image();

      reader.onerror = () => reject(new Error('ファイル読み込みエラー'));
      image.onerror = () => reject(new Error('画像読み込みエラー'));

      reader.onload = () => {
        image.onload = () => {
          try {
            const originalWidth = image.naturalWidth;
            const originalHeight = image.naturalHeight;
            const maxLength = 1000;

            let canvasWidth, canvasHeight;
            if (originalWidth <= maxLength && originalHeight <= maxLength) {
              canvasWidth = originalWidth;
              canvasHeight = originalHeight;
            } else if (originalWidth > originalHeight) {
              canvasWidth = maxLength;
              canvasHeight = maxLength * originalHeight / originalWidth;
            } else {
              canvasWidth = maxLength * originalWidth / originalHeight;
              canvasHeight = maxLength;
            }

            canvas.width = canvasWidth;
            canvas.height = canvasHeight;

            const context = canvas.getContext("2d");
            context.drawImage(image, 0, 0, canvasWidth, canvasHeight);

            const base64 = canvas.toDataURL('image/png', 0.9);
            resolve({ base64, file, index });
          } catch (error) {
            reject(error);
          }
        };
        image.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  function addImagePreview(base64, index) {
    const previewItem = document.createElement('div');
    previewItem.className = 'image-preview-item';
    previewItem.dataset.index = index;

    const img = document.createElement('img');
    img.src = base64;
    previewItem.appendChild(img);

    const removeButton = document.createElement('button');
    removeButton.className = 'remove-image';
    removeButton.textContent = '×';
    removeButton.onclick = () => {
      imageBase64Array = imageBase64Array.filter((_, i) => i !== index);
      imagePreviews = imagePreviews.filter((_, i) => i !== index);
      updatePreviews();
    };
    previewItem.appendChild(removeButton);

    imagePreviewContainer.appendChild(previewItem);
  }

  function updatePreviews() {
    imagePreviewContainer.innerHTML = '';
    imagePreviews.forEach((preview, index) => {
      addImagePreview(preview.base64, index);
    });
    imageBase64ArrayInput.value = JSON.stringify(imageBase64Array.map(p => p.base64));
  }

  imageInput.addEventListener("change", async () => {
    const files = Array.from(imageInput.files);
    
    if (files.length === 0) return;

    const remainingSlots = 4 - imageBase64Array.length;
    const filesToProcess = files.slice(0, remainingSlots);

    if (filesToProcess.length === 0) {
      alert('最大4枚まで選択できます');
      imageInput.value = '';
      return;
    }

    submitButton.disabled = true;
    submitButton.textContent = '画像を処理中...';

    try {
      for (const file of filesToProcess) {
        const result = await processImage(file, imageBase64Array.length);
        if (result) {
          imageBase64Array.push(result);
          imagePreviews.push({ base64: result.base64, file: result.file });
        }
      }
      updatePreviews();
    } catch (error) {
      console.error('画像処理エラー:', error);
      alert('画像の処理中にエラーが発生しました: ' + error.message);
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = '投稿する';
      imageInput.value = '';
    }
  });

  // ハンバーガーメニューの開閉
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
