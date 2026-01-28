# 掲示板サービス構築ガイド

このガイドでは、**AWS EC2** と **Docker** を使用して、シンプルな Webサービスを構築する手順を説明します。

---

## 要件

- 会員登録&ログインした人のみが投稿できるサービスであること
- 会員同士のフォロー機能があること
- 自身がフォローしている人の投稿のみが時系列で表示される画面(=タイムライン)があること
- 投稿には自由に画像を投稿できること（大きい画像もブラウザ側で自動で縮小してからサーバーにアップロードすること）


---

## 1. AWS EC2 インスタンスの準備

1. AWS マネジメントコンソールで **EC2 ダッシュボード**に移動し、「インスタンスを起動」をクリック  
2. AMI は **Amazon Linux 2** を選択  
3. インスタンスタイプは **t2.micro** を推奨  
4. セキュリティグループ設定  
   - SSH (22): 自分の IP から許可  
   - HTTP (80): すべての IP (0.0.0.0/0) から許可  
5. キーペアを作成してインスタンスを起動  
6. SSH ssh ec2-user@インスタンスのpublicのアドレス -i  /秘密鍵のファイル場所

---

## 2. Docker 環境のセットアップ

EC2 に接続後、以下を実行してください。

# システムパッケージ更新
```bash
sudo yum update -y
```

# Git インストール
```bash
sudo yum install git -y
```

# Docker インストール
```bash
sudo yum install docker -y
```

# Docker 起動 & 自動起動設定
```bash
sudo systemctl start docker
sudo systemctl enable docker
```


# Docker Compose インストール

```bash
sudo curl -L "https://github.com/docker/compose/releases/download/1.29.2/docker-compose-$(uname -s)-$(uname -m)" \
-o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

## 3. Docker コンテナの定義と設定
プロジェクトディレクトリ作成
```bash
mkdir bulletin-board
cd bulletin-board
```


docker-compose.yml
```bash
services:
  web:
    image: nginx:latest
    ports:
      - 80:80
    volumes:
      - ./nginx/conf.d/:/etc/nginx/conf.d/
      - ./public/:/var/www/public/
      - image:/var/www/upload/image/
    depends_on:
      - php
  php:
    container_name: php
    build:
      context: .
      target: php
    volumes:
      - ./public/:/var/www/public/
      - image:/var/www/upload/image/
  mysql:
    container_name: mysql
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: example_db
      MYSQL_ALLOW_EMPTY_PASSWORD: 1
      TZ: Asia/Tokyo
    volumes:
      - mysql:/var/lib/mysql
    command: >
      mysqld
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --max_allowed_packet=4MB
  redis:
    container_name: redis
    image: redis:latest
    ports:
      - 6379:6379
volumes:
  mysql:
  image:

```

Dockerfile
```bash
FROM php:8.4-fpm-alpine AS php

RUN apk add --no-cache autoconf build-base \
    && yes '' | pecl install redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-install pdo_mysql

RUN install -o www-data -g www-data -d /var/www/upload/image/

COPY ./php.ini ${PHP_INI_DIR}/php.ini



```


ディレクトリ作成
```bash
mkdir nginx
mkdir public
```

4. アプリケーションとサーバー設定
nginx/default.conf
```bash
server {
    listen 80;
    server_name _;
    root /var/www/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    
    location /css/ {
         alias /var/www/public/css/;
         try_files $uri =404;
     }

    location /image/ {
      alias /var/www/upload/image/;
    }
}

```

php.ini
```bash
post_max_size = 5M
upload_max_filesize = 5M

session.save_handler = redis
session.save_path = "tcp://redis:6379"
sessio.gc_maxlifetime = 86400
```

---

## 4. アプリケーションの取得

リポジトリからアプリケーションを取得する場合:

```bash
cd ~
git clone https://github.com/SHATI0303/kouki_suiyou34.git dockertest
cd dockertest
```

※ 授業用に自分の GitHub リポジトリにコードを置いた場合は、その URL を使用してください。

---

## 5. Docker でサービスを起動

リポジトリ直下（`compose.yml` があるディレクトリ）で以下を実行:

```bash
docker compose up -d
```

これにより、以下のコンテナが起動します:
- `web` (nginx)
- `php` (PHP-FPM)
- `mysql` (MySQL 8.4)
- `redis` (Redis)

起動状態の確認:

```bash
docker compose ps
```

---

## 6. ブラウザからのアクセス確認

ブラウザで以下にアクセスします:

```
http://<インスタンスのパブリックIP>/
```

例: `http://54.157.214.151/`

ページが表示されれば、AWS 上でのサービス構築と Docker のセットアップは完了です。

---

## 7. 注意点

- インスタンス停止中はサービスも停止します。採点時間中はインスタンスが起動していることを確認してください。
- セキュリティグループで **HTTP(80)** が外部から許可されていることを必ず確認してください。
- 必要に応じて、AWS Academy の指示や授業資料に従って設定してください。
