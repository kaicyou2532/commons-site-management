# commonsウェブサイト要件

next.jsのウェブサイト(next-app直下に存在)をnpm run build,npm run start をpublicディレクトリ内にあるphpのウェブサイト上で行えるウェブアプリ。
phpのウェブサイトはdocker-composeでデプロイされnext.jsに必要な環境変数はenvディレクトリ内に配置。
phpのサイトはdigest認証とし、認証のパスワード等は.envに保存する

# コンテナを停止して削除
docker-compose down

# イメージを再ビルドして起動
docker-compose up -d --build

# ログを確認
docker logs commons-web --tail 20