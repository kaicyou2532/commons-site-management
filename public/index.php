<?php
// .envファイルの読み込み
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Next.jsアプリのパス（Dockerコンテナ内の場合は /var/www/next-app）
// ホストマシンで実行する場合は、プロジェクトルートからの相対パス
$isDocker = file_exists('/.dockerenv');
if ($isDocker) {
    $nextAppPath = '/var/www/next-app';
    $nextEnvPath = '/var/www/env/next.env';
} else {
    // ホストマシンの場合
    $projectRoot = dirname(__DIR__);
    $nextAppPath = $projectRoot . '/next-app';
    $nextEnvPath = $projectRoot . '/env/next.env';
}

// エラーメッセージ
$error = '';
$success = '';
$output = [];

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'deploy') {
        // 既存のプロセスを停止
        exec("pkill -f 'npm run start'");
        exec("pkill -f 'next start'");
        
        // 環境変数の設定
        $envCmd = '';
        if (file_exists($nextEnvPath)) {
            $envCmd = "export \$(cat $nextEnvPath | xargs) && ";
        }
        
        // npm install
        $output[] = "=== npm install を実行中 ===";
        $installCmd = "cd $nextAppPath && {$envCmd}npm install 2>&1";
        exec($installCmd, $installOutput, $installCode);
        $output = array_merge($output, $installOutput);
        $output[] = "";
        
        if ($installCode !== 0) {
            $output[] = "エラー: npm install に失敗しました";
        } else {
            // npm run build
            $output[] = "=== npm run build を実行中 ===";
            $buildCmd = "cd $nextAppPath && {$envCmd}npm run build 2>&1";
            exec($buildCmd, $buildOutput, $buildCode);
            $output = array_merge($output, $buildOutput);
            $output[] = "";
            
            if ($buildCode !== 0) {
                $output[] = "エラー: npm run build に失敗しました";
            } else {
                // npm run start（バックグラウンド）
                $output[] = "=== npm run start を実行中 ===";
                $startCmd = "cd $nextAppPath && {$envCmd}nohup npm run start > /tmp/nextjs.log 2>&1 &";
                exec($startCmd);
                $output[] = "Next.jsアプリケーションをバックグラウンドで起動しました";
                $output[] = "http://localhost:3000 でアクセスできます";
            }
        }
    }
}

// 現在の状態を確認
exec("ps aux | grep -E '(npm run start|next start)' | grep -v grep", $statusOutput);
$isRunning = !empty($statusOutput);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commons Site Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border: 2px solid #d9ae4c;
            padding: 40px;
        }
        
        h1 {
            color: #d9ae4c;
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .status {
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #d9ae4c;
            background: white;
        }
        
        .status.running {
            color: #d9ae4c;
        }
        
        .status.stopped {
            color: #999;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #d9ae4c;
        }
        
        .alert.success {
            background: #fffef8;
            color: #333;
        }
        
        .alert.error {
            background: #fff5f5;
            color: #333;
            border-color: #ccc;
        }
        
        .buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        button {
            padding: 15px 30px;
            border: 2px solid #d9ae4c;
            background: white;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            color: #333;
        }
        
        button:hover {
            background: #d9ae4c;
            color: white;
        }
        
        .output {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            padding: 20px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .output h3 {
            color: #d9ae4c;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .output pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }
        
        .info {
            background: white;
            border: 1px solid #d9ae4c;
            padding: 20px;
            margin-top: 30px;
        }
        
        .info h3 {
            color: #d9ae4c;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .info p {
            color: #333;
            line-height: 1.8;
        }
        
        .info strong {
            color: #d9ae4c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>COMMONS SITE MANAGEMENT</h1>
        
        <div class="status <?php echo $isRunning ? 'running' : 'stopped'; ?>">
            <?php echo $isRunning ? 'Next.js アプリケーション実行中' : 'Next.js アプリケーション停止中'; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="buttons">
                <button type="submit" name="action" value="deploy">
                    デプロイ実行
                </button>
            </div>
        </form>
        
        <?php if (!empty($output)): ?>
            <div class="output">
                <h3>コマンド出力</h3>
                <pre><?php echo htmlspecialchars(implode("\n", $output)); ?></pre>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <h3>使い方</h3>
            <p>
                <strong>デプロイ実行</strong>ボタンをクリックすると、以下の処理が順次実行されます：<br>
                1. 既存のNext.jsプロセスを停止<br>
                2. npm install で依存関係をインストール<br>
                3. npm run build でアプリケーションをビルド<br>
                4. npm run start でアプリケーションを起動<br>
                <br>
                起動後は <strong>http://localhost:3000</strong> でアクセスできます。
            </p>
        </div>
    </div>
</body>
</html>
