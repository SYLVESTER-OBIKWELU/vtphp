<?php

namespace Core;

class ExceptionHandler
{
    protected $debugMode = true;

    public function __construct($debugMode = true)
    {
        $this->debugMode = $debugMode;
    }

    public function handle($exception)
    {
        $this->logException($exception);

        if ($this->shouldReturnJson()) {
            $this->renderJsonError($exception);
        } else {
            $this->renderHtmlError($exception);
        }

        exit(1);
    }

    public function handleError($level, $message, $file = '', $line = 0)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    public function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->handle(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    protected function shouldReturnJson()
    {
        return isset($_SERVER['HTTP_ACCEPT']) && 
               strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    protected function renderJsonError($exception)
    {
        http_response_code($this->getStatusCode($exception));
        header('Content-Type: application/json');

        $error = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode()
        ];

        if ($this->debugMode) {
            $error['exception'] = get_class($exception);
            $error['file'] = $exception->getFile();
            $error['line'] = $exception->getLine();
            $error['trace'] = $this->formatTrace($exception->getTrace());
        }

        echo json_encode($error, JSON_PRETTY_PRINT);
    }

    protected function renderHtmlError($exception)
    {
        http_response_code($this->getStatusCode($exception));

        if (!$this->debugMode) {
            $this->renderProductionError($exception);
            return;
        }

        $this->renderDevelopmentError($exception);
    }

    protected function renderProductionError($exception)
    {
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .error-container {
            text-align: center;
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #e74c3c; margin: 0 0 10px; font-size: 48px; }
        p { color: #7f8c8d; margin: 0; font-size: 18px; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Oops!</h1>
        <p>Something went wrong. Please try again later.</p>
    </div>
</body>
</html>
HTML;
    }

    protected function renderDevelopmentError($exception)
    {
        $message = htmlspecialchars($exception->getMessage());
        $file = htmlspecialchars($exception->getFile());
        $line = $exception->getLine();
        $class = htmlspecialchars(get_class($exception));
        $code = $exception->getCode();
        
        $trace = $this->formatHtmlTrace($exception->getTrace());
        $filePreview = $this->getFilePreview($exception->getFile(), $exception->getLine());

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$class}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            font-size: 14px;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 40px;
            border-bottom: 3px solid #9333ea;
        }
        .exception-type {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .exception-message {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            word-wrap: break-word;
        }
        .exception-location {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        .section {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #3a3a3a;
        }
        .section-title {
            color: #9333ea;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .code-preview {
            background: #1e1e1e;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #3a3a3a;
        }
        .code-line {
            display: flex;
            padding: 4px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border-left: 3px solid transparent;
        }
        .code-line.highlight {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
        }
        .line-number {
            color: #666;
            padding: 0 15px;
            text-align: right;
            min-width: 60px;
            user-select: none;
        }
        .line-code {
            color: #d4d4d4;
            padding-right: 15px;
            flex: 1;
            white-space: pre;
        }
        .stack-trace {
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .trace-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            background: #1e1e1e;
            border-radius: 6px;
            border-left: 3px solid #444;
            transition: all 0.2s;
        }
        .trace-item:hover {
            background: #252525;
            border-left-color: #9333ea;
        }
        .trace-call {
            color: #60a5fa;
            margin-bottom: 5px;
        }
        .trace-location {
            color: #9ca3af;
            font-size: 12px;
        }
        .keyword { color: #c586c0; }
        .string { color: #ce9178; }
        .number { color: #b5cea8; }
        .comment { color: #6a9955; }
    </style>
</head>
<body>
    <div class="header">
        <div class="exception-type">{$class}</div>
        <div class="exception-message">{$message}</div>
        <div class="exception-location">📁 {$file}:{$line}</div>
    </div>
    
    <div class="container">
        <div class="section">
            <div class="section-title">📄 Code Preview</div>
            <div class="code-preview">
                {$filePreview}
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">📚 Stack Trace</div>
            <div class="stack-trace">
                {$trace}
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    protected function getFilePreview($file, $errorLine, $context = 10)
    {
        if (!file_exists($file)) {
            return '<div style="padding: 15px; color: #ef4444;">File not found</div>';
        }

        $lines = file($file);
        $start = max(0, $errorLine - $context - 1);
        $end = min(count($lines), $errorLine + $context);
        
        $html = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $lineCode = htmlspecialchars($lines[$i]);
            $highlight = $lineNum === $errorLine ? 'highlight' : '';
            
            $html .= "<div class='code-line {$highlight}'>";
            $html .= "<span class='line-number'>{$lineNum}</span>";
            $html .= "<span class='line-code'>{$lineCode}</span>";
            $html .= "</div>";
        }
        
        return $html;
    }

    protected function formatHtmlTrace($trace)
    {
        $html = '';
        
        foreach ($trace as $index => $item) {
            $file = isset($item['file']) ? htmlspecialchars($item['file']) : 'unknown';
            $line = $item['line'] ?? '?';
            $function = $item['function'] ?? '';
            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            
            $call = htmlspecialchars($class . $type . $function . '()');
            
            $html .= "<div class='trace-item'>";
            $html .= "<div class='trace-call'>#{$index} {$call}</div>";
            $html .= "<div class='trace-location'>📁 {$file}:{$line}</div>";
            $html .= "</div>";
        }
        
        return $html ?: '<div style="padding: 15px; color: #9ca3af;">No trace available</div>';
    }

    protected function formatTrace($trace)
    {
        return array_map(function($item) {
            return [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? '',
                'class' => $item['class'] ?? '',
                'type' => $item['type'] ?? ''
            ];
        }, $trace);
    }

    protected function getStatusCode($exception)
    {
        $code = $exception->getCode();
        return ($code >= 400 && $code < 600) ? $code : 500;
    }

    protected function logException($exception)
    {
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] %s: %s in %s:%d\n",
            $timestamp,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        error_log($message, 3, $logFile);
    }
}
