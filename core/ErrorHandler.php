<?php

namespace Core;

class ErrorHandler
{
    protected static $isRegistered = false;

    public static function register()
    {
        if (self::$isRegistered) {
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$isRegistered = true;
    }

    public static function handleError($level, $message, $file = '', $line = 0)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    public static function handleException($exception)
    {
        self::renderException($exception);
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::renderException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    public static function renderException($exception)
    {
        // Check if env() function exists, otherwise fallback to checking $_ENV
        if (function_exists('env')) {
            $isDev = env('APP_DEBUG', true);
        } else {
            $isDev = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true;
            // Convert string to boolean
            if (is_string($isDev)) {
                $isDev = filter_var($isDev, FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (!$isDev) {
            self::renderProductionError();
            return;
        }

        self::renderDebugError($exception);
    }

    protected static function renderProductionError()
    {
        http_response_code(500);
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .error-container { text-align: center; max-width: 600px; padding: 40px; }
        h1 { font-size: 72px; margin: 0; color: #e74c3c; }
        h2 { font-size: 24px; margin: 20px 0; color: #2c3e50; }
        p { color: #7f8c8d; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>500</h1>
        <h2>Oops! Something went wrong</h2>
        <p>We\'re sorry, but something went wrong on our end. Please try again later.</p>
    </div>
</body>
</html>';
    }

    protected static function renderDebugError($exception)
    {
        $message = htmlspecialchars($exception->getMessage());
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        $type = get_class($exception);

        $code = self::getCodeSnippet($file, $line);
        $stackTrace = self::formatStackTrace($exception->getTrace());

        http_response_code(500);

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $type . ' - VTPHP Framework</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "SF Mono", Monaco, "Cascadia Code", "Roboto Mono", Consolas, monospace; background: #1a1b26; color: #a9b1d6; font-size: 14px; line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .error-header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 30px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        .error-title { font-size: 24px; font-weight: 600; color: #fff; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .error-type { background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .error-message { color: #fff; font-size: 16px; margin-top: 10px; opacity: 0.95; }
        .error-location { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 8px; }
        .section { background: #24283b; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .section-title { font-size: 16px; font-weight: 600; color: #7aa2f7; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
        .copy-btn { background: #7aa2f7; color: #1a1b26; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .copy-btn:hover { background: #89b4fa; transform: translateY(-1px); }
        .copy-btn:active { transform: translateY(0); }
        .copy-btn.copied { background: #9ece6a; }
        .code-snippet { background: #1f2335; border-radius: 6px; overflow: hidden; border: 1px solid #414868; }
        .code-line { display: flex; padding: 4px 0; font-size: 13px; border-bottom: 1px solid #2a2e42; }
        .code-line:last-child { border-bottom: none; }
        .line-number { min-width: 60px; text-align: right; padding: 0 15px; color: #565f89; background: #1a1e2e; user-select: none; }
        .line-content { flex: 1; padding: 0 15px; overflow-x: auto; white-space: pre; }
        .line-error { background: rgba(231, 76, 60, 0.1); border-left: 3px solid #e74c3c; }
        .line-error .line-number { color: #e74c3c; font-weight: 600; background: rgba(231, 76, 60, 0.1); }
        .stack-trace { font-size: 13px; }
        .stack-item { padding: 12px; border-bottom: 1px solid #2a2e42; transition: background 0.2s; }
        .stack-item:hover { background: #1f2335; }
        .stack-item:last-child { border-bottom: none; }
        .stack-file { color: #7dcfff; }
        .stack-line { color: #f7768e; }
        .stack-function { color: #9ece6a; margin-top: 4px; }
        .raw-trace { background: #1f2335; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 12px; white-space: pre; color: #a9b1d6; border: 1px solid #414868; max-height: 400px; overflow-y: auto; }
        .highlight { color: #f7768e; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .badge-error { background: #f7768e; color: #1a1b26; }
        .badge-warning { background: #e0af68; color: #1a1b26; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1f2335; }
        ::-webkit-scrollbar-thumb { background: #414868; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #565f89; }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #9ece6a 0%, #7dcfff 100%);
            color: #1a1b26;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease-out, slideOut 0.3s ease-in 2.7s;
            opacity: 0;
        }
        
        .toast.show {
            opacity: 1;
            animation: slideIn 0.3s ease-out forwards;
        }
        
        .toast.hide {
            animation: slideOut 0.3s ease-in forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        .toast-icon {
            width: 20px;
            height: 20px;
            background: #1a1b26;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ece6a;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-header">
            <div class="error-title">
                <span>⚠️</span>
                <span>' . $type . '</span>
                <span class="error-type">Exception</span>
            </div>
            <div class="error-message">' . $message . '</div>
            <div class="error-location">
                📁 ' . htmlspecialchars($file) . ' <span class="highlight">on line ' . $line . '</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <span>📝 Code Snippet</span>
                <button class="copy-btn" onclick="copyCode(\'code-snippet\')">Copy Code</button>
            </div>
            <div class="code-snippet" id="code-snippet">
                ' . $code . '
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <span>📚 Stack Trace</span>
                <button class="copy-btn" onclick="copyCode(\'stack-trace\')">Copy Stack Trace</button>
            </div>
            <div class="stack-trace" id="stack-trace">
                ' . $stackTrace . '
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <span>🔍 Raw Trace</span>
                <button class="copy-btn" onclick="copyCode(\'raw-trace\')">Copy Raw</button>
            </div>
            <div class="raw-trace" id="raw-trace">' . htmlspecialchars($trace) . '</div>
        </div>
    </div>

    <script>
        function copyCode(elementId) {
            const element = document.getElementById(elementId);
            const text = element.innerText || element.textContent;
            
            // Try multiple methods for copying
            // Method 1: Modern Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess(event.target);
                }).catch(err => {
                    // Fallback to legacy method
                    copyToClipboardFallback(text, event.target);
                });
            } else {
                // Method 2: Legacy fallback
                copyToClipboardFallback(text, event.target);
            }
        }

        function copyToClipboardFallback(text, btn) {
            const textarea = document.createElement(\'textarea\');
            textarea.value = text;
            textarea.style.position = \'fixed\';
            textarea.style.left = \'-999999px\';
            textarea.style.top = \'-999999px\';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            try {
                const successful = document.execCommand(\'copy\');
                if (successful) {
                    showCopySuccess(btn);
                } else {
                    showCopyError();
                }
            } catch (err) {
                showCopyError();
            }
            
            document.body.removeChild(textarea);
        }

        function showCopySuccess(btn) {
            const originalText = btn.textContent;
            btn.textContent = "✓ Copied!";
            btn.classList.add("copied");
            
            // Show toast notification
            showToast("✓ Copied to clipboard successfully!");
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove("copied");
            }, 2000);
        }

        function showCopyError() {
            showToast("⚠ Copy failed. Please select and copy manually (Ctrl+C)", "error");
        }
        
        function showToast(message, type) {
            if (typeof type === \'undefined\') type = "success";
            
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll(\'.toast\');
            existingToasts.forEach(toast => toast.remove());
            
            // Create new toast
            const toast = document.createElement(\'div\');
            toast.className = \'toast show\';
            
            const icon = type === "success" ? "✓" : "⚠";
            toast.innerHTML = \'<div class="toast-icon">\' + icon + \'</div><span>\' + message + \'</span>\';
            
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(function() {
                toast.classList.remove(\'show\');
                toast.classList.add(\'hide\');
                setTimeout(function() { toast.remove(); }, 300);
            }, 3000);
        }

        document.addEventListener("keydown", (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === "c" && window.getSelection().toString() === "") {
                e.preventDefault();
                copyCode("raw-trace");
            }
        });
    </script>
</body>
</html>';
    }

    protected static function getCodeSnippet($file, $errorLine, $linesAround = 10)
    {
        if (!file_exists($file)) {
            return '<div class="line-content">File not found</div>';
        }

        $lines = file($file);
        $start = max(0, $errorLine - $linesAround - 1);
        $end = min(count($lines), $errorLine + $linesAround);

        $html = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $lineContent = htmlspecialchars($lines[$i]);
            $isError = ($lineNumber === $errorLine);
            
            $class = $isError ? 'code-line line-error' : 'code-line';
            $html .= "<div class=\"{$class}\">
                <div class=\"line-number\">{$lineNumber}</div>
                <div class=\"line-content\">{$lineContent}</div>
            </div>";
        }

        return $html;
    }

    protected static function formatStackTrace($trace)
    {
        $html = '';
        
        foreach ($trace as $index => $item) {
            $file = isset($item['file']) ? htmlspecialchars($item['file']) : 'unknown';
            $line = isset($item['line']) ? $item['line'] : '?';
            $function = isset($item['function']) ? htmlspecialchars($item['function']) : 'unknown';
            $class = isset($item['class']) ? htmlspecialchars($item['class']) : '';
            $type = isset($item['type']) ? htmlspecialchars($item['type']) : '';

            $html .= "<div class=\"stack-item\">
                <div><span class=\"highlight\">#{$index}</span> <span class=\"stack-file\">{$file}</span>:<span class=\"stack-line\">{$line}</span></div>";
            
            if ($class) {
                $html .= "<div class=\"stack-function\">{$class}{$type}{$function}()</div>";
            } else {
                $html .= "<div class=\"stack-function\">{$function}()</div>";
            }
            
            $html .= "</div>";
        }

        return $html ?: '<div class="stack-item">No stack trace available</div>';
    }
}
