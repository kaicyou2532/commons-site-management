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

// Next.jsアプリのパス
$nextAppPath = '/var/www/next-app';
$nextEnvPath = getenv('NEXTJS_ENV_FILE') ?: '/var/www/env/next.env';

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
        
        if ($returnCode === 0) {
            $success = 'ビルドが完了しました。';
        } else {
            $error = 'ビルドに失敗しました。';
        }
    } elseif ($action === 'start') {
        // Next.jsの起動
        $command = "cd $nextAppPath && npm run start > /dev/null 2>&1 &";
        if (file_exists($nextEnvPath)) {
            $command = "export \$(cat $nextEnvPath | xargs) && " . $command;
        }
        
        exec($command, $output, $returnCode);
        $success = 'Next.jsアプリケーションを起動しました。';
    } elseif ($action === 'stop') {
        // Next.jsプロセスの停止
        exec("pkill -f 'npm run start'", $output, $returnCode);
        exec("pkill -f 'next start'", $output, $returnCode);
        $success = 'Next.jsアプリケーションを停止しました。';
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 32px;
            text-align: center;
        }
        
        .status {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .status.running {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.stopped {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        button {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-build {
            background: #667eea;
            color: white;
        }
        
        .btn-start {
            background: #28a745;
            color: white;
        }
        
        .btn-stop {
            background: #dc3545;
            color: white;
        }
        
        .btn-status {
            background: #17a2b8;
            color: white;
        }
        
        .output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .output pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .info h3 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        .info p {
            color: #004085;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Commons Site Management</h1>
        
        <div class="status <?php echo $isRunning ? 'running' : 'stopped'; ?>">
            <?php echo $isRunning ? '● Next.js アプリケーション実行中' : '○ Next.js アプリケーション停止中'; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="buttons">
                <button type="submit" name="action" value="build" class="btn-build">
                    🔨 ビルド
                </button>
                <button type="submit" name="action" value="start" class="btn-start">
                    ▶️ 起動
                </button>
                <button type="submit" name="action" value="stop" class="btn-stop">
                    ⏹️ 停止
                </button>
                <button type="submit" name="action" value="status" class="btn-status">
                    📊 状態確認
                </button>
            </div>
        </form>
        
        <?php if (!empty($output)): ?>
            <div class="output">
                <h3>コマンド出力:</h3>
                <pre><?php echo htmlspecialchars(implode("\n", $output)); ?></pre>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <h3>📋 使い方</h3>
            <p>
                <strong>ビルド:</strong> Next.jsアプリケーションをビルドします（npm run build）<br>
                <strong>起動:</strong> ビルドされたNext.jsアプリケーションを起動します（npm run start）<br>
                <strong>停止:</strong> 実行中のNext.jsアプリケーションを停止します<br>
                <strong>状態確認:</strong> 現在のプロセス状態を確認します
            </p>
        </div>
    </div>
</body>
</html>
