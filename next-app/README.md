# Next.js Starter App
# このディレクトリにNext.jsアプリケーションを配置してください

## セットアップ方法

1. このディレクトリに移動
```bash
cd next-app
```

2. Next.jsアプリを作成（まだの場合）
```bash
npx create-next-app@latest . --typescript --tailwind --app --no-src-dir
```

3. 依存関係のインストール
```bash
npm install
```

4. 環境変数の設定
- `/env/next.env.sample`を参考に`/env/next.env`を作成

5. ローカル開発
```bash
npm run dev
```

6. ビルドと起動（本番環境）
- PHPの管理画面から実行可能
- または手動で：
```bash
npm run build
npm run start
```

## ディレクトリ構造
```
next-app/
├── app/
│   ├── page.tsx
│   └── layout.tsx
├── public/
├── package.json
└── next.config.js
```
