# Commons Site Management

Next.jsアプリケーションをPHP管理画面から管理するためのWebアプリケーションです。

## 📋 機能

- ✅ Next.jsアプリのビルド実行（`npm run build`）
- ✅ Next.jsアプリの起動（`npm run start`）  
- ✅ Next.jsプロセスの停止
- ✅ プロセス状態の確認
- ✅ Docker Composeでデプロイ
- ✅ Digest認証によるアクセス制御

## 🏗️ プロジェクト構成

```
commons-site-management/
├── docker/
│   ├── Dockerfile              # PHPとNode.jsを含むDockerイメージ
│   ├── apache-config.conf      # Apache設定
│   └── htpasswd.sample         # Digest認証用サンプル
├── public/
│   ├── index.php               # メイン管理画面
│   └── .htaccess              # Digest認証設定
├── next-app/                   # Next.jsアプリケーション配置場所
│   └── README.md
├── env/
│   └── next.env.sample         # Next.js環境変数サンプル
├── .env                        # PHP環境変数（認証情報など）
├── docker-compose.yml          # Docker Compose設定
└── youken.md                   # 要件定義書
```

## 🚀 セットアップ手順

### 1. Digest認証の設定

htpasswdファイルを生成します：

```bash
# Dockerコンテナ内で実行、または事前に作成
htdigest -c docker/htpasswd "Commons Management Area" admin
# パスワードを入力
```

### 2. 環境変数の設定

`.env`ファイルを編集して認証情報を設定：

```bash
# .env
DIGEST_REALM="Commons Management Area"
DIGEST_USERNAME=admin
DIGEST_PASSWORD=your-password-here
NEXTJS_ENV_FILE=/var/www/env/next.env
```

### 3. Next.js環境変数の設定

```bash
cp env/next.env.sample env/next.env
# env/next.envを編集して実際の値を設定
```

### 4. Next.jsアプリケーションの配置

`next-app/`ディレクトリにNext.jsプロジェクトを配置：

```bash
cd next-app
npx create-next-app@latest . --typescript --tailwind --app
npm install
```

### 5. Dockerコンテナの起動

```bash
docker-compose up -d --build
```

### 6. アクセス

ブラウザで以下にアクセス：
```
http://localhost:8080
```

Digest認証のダイアログが表示されるので、設定したユーザー名とパスワードを入力してください。

## 🎯 使い方

管理画面では以下の操作が可能です：

1. **🔨 ビルド** - Next.jsアプリをビルド（`npm run build`）
2. **▶️ 起動** - ビルド済みアプリを起動（`npm run start`）
3. **⏹️ 停止** - 実行中のNext.jsプロセスを停止
4. **📊 状態確認** - 現在のプロセス状態を表示

## 🔒 セキュリティ

- Apache Digest認証で管理画面を保護
- 認証情報は`.env`ファイルで管理（Gitにコミットしないこと）
- セキュリティヘッダーを`.htaccess`で設定

## 📝 環境変数

### PHP（`.env`）
- `DIGEST_REALM` - 認証レルム名
- `DIGEST_USERNAME` - 管理者ユーザー名
- `DIGEST_PASSWORD` - 管理者パスワード
- `NEXTJS_ENV_FILE` - Next.js環境変数ファイルのパス

### Next.js（`env/next.env`）
- プロジェクト固有の環境変数を設定
- `NEXT_PUBLIC_*` - クライアントサイドで使用する変数
- その他API Keys、データベース接続情報など

## 🛠️ トラブルシューティング

### ビルドが失敗する
- `next-app`ディレクトリに有効なNext.jsプロジェクトがあるか確認
- `npm install`で依存関係がインストールされているか確認

### 認証ダイアログが表示されない
- `docker/htpasswd`ファイルが正しく生成されているか確認
- Apacheのauth_digestモジュールが有効か確認

### プロセスが起動しない
- `env/next.env`が正しく設定されているか確認
- ポート3000が他のプロセスで使用されていないか確認

## 📦 使用技術

- **Backend**: PHP 8.2
- **Web Server**: Apache 2.4
- **Frontend**: Next.js
- **Runtime**: Node.js 20
- **Container**: Docker & Docker Compose

## 📄 ライセンス

このプロジェクトは内部使用を目的としています。
