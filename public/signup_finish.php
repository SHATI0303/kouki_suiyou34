<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員登録完了</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* 完了ページ固有のスタイルを一時的にここに残します (またはstyle.cssに移動) */
        /* main-containerを垂直中央寄せするためのbodyスタイルを上書き */
        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .container {
            width: 100%;
            max-width: 400px;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px var(--shadow-color), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
        }
        h1 {
            font-size: 2rem;
            color: var(--success-color); 
            margin-top: 0;
            margin-bottom: 20px;
        }
        .icon-check {
            font-size: 3rem;
            color: var(--success-color);
            margin-bottom: 15px;
            display: block;
        }
        .message-text {
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 30px;
        }
        .btn-primary { 
             /* ログイン/登録ページと異なり、display:block が効くように上書き */
             display: block; 
             width: 100%;
             text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">
    
    <h1>会員登録完了</h1>
    
    <p class="message-text">
        ご登録ありがとうございます！<br>
        早速、登録した内容をもとにログインしてください。
    </p>

    <a href="/login.php" class="btn-primary">
        ログインページへ
    </a>
</div>

</body>
</html>
