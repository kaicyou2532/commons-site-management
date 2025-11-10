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
    
    if ($action === 'build') {
        // Next.jsのビルド
        $command = "cd $nextAppPath && npm run build 2>&1";
        if (file_exists($nextEnvPath)) {
            $command = "export \$(cat $nextEnvPath | xargs) && " . $command;
        }
        
        exec($command, $output, $returnCode);
    } elseif ($action === 'start') {
        // Next.jsの起動
        $command = "cd $nextAppPath && npm run start >> /tmp/nextjs.log 2>&1 &";
        if (file_exists($nextEnvPath)) {
            $command = "export \$(cat $nextEnvPath | xargs) && " . $command;
        }
        
        exec($command, $output, $returnCode);
        $output[] = "Next.jsアプリケーションを起動しました。";
    } elseif ($action === 'deploy') {
        // ビルドと起動を連続実行（バックグラウンド）
        // まず既存のプロセスを停止
        exec("pkill -f 'npm run start'");
        exec("pkill -f 'next start'");
        
        // ログファイルをクリア
        $logFile = '/tmp/nextjs-build.log';
        file_put_contents($logFile, '');
        
        // デプロイスクリプトを作成
        $scriptPath = '/tmp/nextjs-deploy.sh';
        $scriptContent = "#!/bin/bash\n\n";
        $scriptContent .= "cd $nextAppPath || exit 1\n\n";
        if (file_exists($nextEnvPath)) {
            $scriptContent .= "export \$(cat $nextEnvPath | xargs)\n\n";
        }
        $scriptContent .= "echo \"ビルドを開始します...\" > $logFile\n";
        $scriptContent .= "echo \"作業ディレクトリ: \$(pwd)\" >> $logFile\n";
        $scriptContent .= "echo \"\" >> $logFile\n\n";
        $scriptContent .= "# 古い.nextディレクトリを削除\n";
        $scriptContent .= "if [ -d \".next\" ]; then\n";
        $scriptContent .= "  echo \"既存の.nextディレクトリを削除します...\" >> $logFile\n";
        $scriptContent .= "  rm -rf .next\n";
        $scriptContent .= "fi\n\n";
        $scriptContent .= "echo \"npm run buildを実行中...\" >> $logFile\n";
        $scriptContent .= "echo \"\" >> $logFile\n\n";
        $scriptContent .= "# タイムアウト付きでビルドを実行（5分）\n";
        $scriptContent .= "timeout 300 npm run build >> $logFile 2>&1\n";
        $scriptContent .= "BUILD_EXIT_CODE=\$?\n\n";
        $scriptContent .= "echo \"\" >> $logFile\n";
        $scriptContent .= "echo \"========================================\" >> $logFile\n";
        $scriptContent .= "echo \"ビルド終了コード: \$BUILD_EXIT_CODE\" >> $logFile\n";
        $scriptContent .= "echo \"========================================\" >> $logFile\n";
        $scriptContent .= "echo \"\" >> $logFile\n\n";
        $scriptContent .= "# ビルド結果の詳細確認\n";
        $scriptContent .= "echo \".nextディレクトリの内容:\" >> $logFile\n";
        $scriptContent .= "if [ -d \".next\" ]; then\n";
        $scriptContent .= "  ls -laR .next >> $logFile 2>&1\n";
        $scriptContent .= "  echo \"\" >> $logFile\n";
        $scriptContent .= "  if [ -f \".next/BUILD_ID\" ]; then\n";
        $scriptContent .= "    echo \"✓ BUILD_IDファイルが存在します\" >> $logFile\n";
        $scriptContent .= "    echo \"BUILD_ID: \$(cat .next/BUILD_ID)\" >> $logFile\n";
        $scriptContent .= "  else\n";
        $scriptContent .= "    echo \"✗ BUILD_IDファイルが見つかりません\" >> $logFile\n";
        $scriptContent .= "  fi\n";
        $scriptContent .= "else\n";
        $scriptContent .= "  echo \"✗ .nextディレクトリが存在しません\" >> $logFile\n";
        $scriptContent .= "fi\n";
        $scriptContent .= "echo \"\" >> $logFile\n\n";
        $scriptContent .= "# ビルドが成功した場合のみ起動\n";
        $scriptContent .= "if [ \$BUILD_EXIT_CODE -eq 0 ] && [ -f \".next/BUILD_ID\" ]; then\n";
        $scriptContent .= "  echo \"✓ ビルドが正常に完了しました\" >> $logFile\n";
        $scriptContent .= "  echo \"\" >> $logFile\n";
        $scriptContent .= "  echo \"3秒後にNext.jsアプリケーションを起動します...\" >> $logFile\n";
        $scriptContent .= "  sleep 3\n";
        $scriptContent .= "  nohup npm run start >> $logFile 2>&1 &\n";
        $scriptContent .= "  START_PID=\$!\n";
        $scriptContent .= "  echo \"✓ 起動コマンドを実行しました (PID: \$START_PID)\" >> $logFile\n";
        $scriptContent .= "else\n";
        $scriptContent .= "  echo \"\" >> $logFile\n";
        $scriptContent .= "  echo \"✗ ビルドが完了していません。起動をスキップします。\" >> $logFile\n";
        $scriptContent .= "  exit 1\n";
        $scriptContent .= "fi\n";
        
        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);
        
        // スクリプトをバックグラウンドで実行
        exec("bash $scriptPath > /dev/null 2>&1 &");
        
        $output[] = "ビルドと起動をバックグラウンドで実行中...";
        $output[] = "進捗を確認するには「ログ確認」ボタンをクリックしてください。";
    } elseif ($action === 'logs') {
        // ログファイルの内容を表示
        $logFile = '/tmp/nextjs-build.log';
        if (file_exists($logFile)) {
            $output = explode("\n", file_get_contents($logFile));
        } else {
            $output[] = "ログファイルが見つかりません。";
        }
    } elseif ($action === 'stop') {
        // Next.jsプロセスの停止
        exec("pkill -f 'npm run start'", $output, $returnCode);
        exec("pkill -f 'next start'", $output, $returnCode);
        $output[] = "Next.jsアプリケーションを停止しました。";
    } elseif ($action === 'status') {
        // プロセスの状態確認
        exec("ps aux | grep -E '(npm run start|next start)' | grep -v grep", $output, $returnCode);
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
                    ビルドして起動
                </button>
                <button type="submit" name="action" value="logs">
                    ログ確認
                </button>
                <button type="submit" name="action" value="build">
                    ビルド
                </button>
                <button type="submit" name="action" value="start">
                    起動
                </button>
                <button type="submit" name="action" value="stop">
                    停止
                </button>
                <button type="submit" name="action" value="status">
                    状態確認
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
                <strong>ビルドして起動:</strong> バックグラウンドでビルドと起動を実行<br>
                <strong>ログ確認:</strong> ビルドとデプロイの進捗ログを表示<br>
                <strong>ビルド:</strong> Next.jsアプリケーションをビルド<br>
                <strong>起動:</strong> ビルド済みアプリケーションを起動<br>
                <strong>停止:</strong> 実行中のアプリケーションを停止<br>
                <strong>状態確認:</strong> 現在のプロセス状態を確認
            </p>
        </div>
    </div>
</body>
</html>
