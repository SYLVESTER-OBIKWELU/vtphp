<?php

use Core\View;
use Core\Collection;

// View helpers
if (!function_exists('view')) {
    function view($view, $data = []) {
        return View::render($view, $data);
    }
}

// Collection helper
if (!function_exists('collect')) {
    function collect($items = []) {
        return new Collection($items);
    }
}

// Debug helpers
if (!function_exists('dd')) {
    function dd(...$vars) {
        echo '<style>
            .dd-container { background: #18171B; color: #fff; padding: 20px; font-family: monospace; }
            .dd-container pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; }
        </style>';
        echo '<div class="dd-container">';
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        echo '</div>';
        exit;
    }
}

if (!function_exists('dump')) {
    function dump(...$vars) {
        echo '<style>
            .dump-container { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; }
        </style>';
        echo '<div class="dump-container">';
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        echo '</div>';
    }
}

// Environment & Config
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        return $value !== false ? $value : $default;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        static $configs = [];
        
        if (empty($configs)) {
            $configPath = __DIR__ . '/../config';
            foreach (glob($configPath . '/*.php') as $file) {
                $name = basename($file, '.php');
                $configs[$name] = require $file;
            }
        }
        
        $keys = explode('.', $key);
        $value = $configs;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

// App helpers
if (!function_exists('app')) {
    function app() {
        return \Core\App::getInstance();
    }
}

if (!function_exists('request')) {
    function request() {
        return new \Core\Request();
    }
}

if (!function_exists('response')) {
    function response($content = '', $status = 200) {
        http_response_code($status);
        echo $content;
        return $content;
    }
}

// Session helpers
if (!function_exists('session')) {
    function session($key = null, $default = null) {
        if (!session_id()) {
            session_start();
        }

        if ($key === null) {
            return $_SESSION;
        }

        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!session_id()) {
            session_start();
        }
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field($method) {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('old')) {
    function old($key, $default = '') {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

// URL & Redirect helpers
if (!function_exists('redirect')) {
    function redirect($url, $status = 302) {
        header("Location: {$url}", true, $status);
        exit;
    }
}

if (!function_exists('back')) {
    function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    function route($name, $params = []) {
        global $router;
        if (!$router) {
            throw new \Exception("Router instance not found");
        }
        
        // Get base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        return $baseUrl . $router->route($name, $params);
    }
}

if (!function_exists('url')) {
    function url($path = null) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        if ($path === null) {
            return $baseUrl;
        }
        
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('public_path')) {
    function public_path($path = '') {
        $publicPath = dirname(__DIR__) . '/public_html';
        return $path ? $publicPath . '/' . ltrim($path, '/') : $publicPath;
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        $storagePath = dirname(__DIR__) . '/storage';
        return $path ? $storagePath . '/' . ltrim($path, '/') : $storagePath;
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '') {
        $basePath = dirname(__DIR__);
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}

if (!function_exists('resource_path')) {
    function resource_path($path = '') {
        $resourcePath = dirname(__DIR__) . '/resources';
        return $path ? $resourcePath . '/' . ltrim($path, '/') : $resourcePath;
    }
}

if (!function_exists('mix')) {
    function mix($path) {
        static $manifest = null;
        
        if ($manifest === null) {
            $manifestPath = public_path('mix-manifest.json');
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
            } else {
                $manifest = [];
            }
        }
        
        if (isset($manifest[$path])) {
            return asset($manifest[$path]);
        }
        
        return asset($path);
    }
}

// String helpers
if (!function_exists('str_random')) {
    function str_random($length = 16) {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('str_slug')) {
    function str_slug($title, $separator = '-') {
        $title = preg_replace('/[^a-z0-9\s-]/i', '', strtolower($title));
        return preg_replace('/[\s-]+/', $separator, trim($title));
    }
}

if (!function_exists('str_limit')) {
    function str_limit($value, $limit = 100, $end = '...') {
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str')) {
    function str($string = '') {
        return new class($string) {
            private $value;
            public function __construct($value) { $this->value = $value; }
            public function upper() { $this->value = strtoupper($this->value); return $this; }
            public function lower() { $this->value = strtolower($this->value); return $this; }
            public function title() { $this->value = ucwords($this->value); return $this; }
            public function limit($limit = 100, $end = '...') { $this->value = str_limit($this->value, $limit, $end); return $this; }
            public function slug($separator = '-') { $this->value = str_slug($this->value, $separator); return $this; }
            public function contains($needle) { return str_contains($this->value, $needle); }
            public function startsWith($needle) { return str_starts_with($this->value, $needle); }
            public function endsWith($needle) { return str_ends_with($this->value, $needle); }
            public function replace($search, $replace) { $this->value = str_replace($search, $replace, $this->value); return $this; }
            public function __toString() { return $this->value; }
        };
    }
}

// Array helpers
if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        
        return $array;
    }
}

if (!function_exists('array_only')) {
    function array_only($array, $keys) {
        return array_intersect_key($array, array_flip((array) $keys));
    }
}

if (!function_exists('array_except')) {
    function array_except($array, $keys) {
        return array_diff_key($array, array_flip((array) $keys));
    }
}

// Array helpers
if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null) {
        return \Core\Arr::get($array, $key, $default);
    }
}

if (!function_exists('array_only')) {
    function array_only($array, $keys) {
        return \Core\Arr::only($array, $keys);
    }
}

if (!function_exists('array_except')) {
    function array_except($array, $keys) {
        return \Core\Arr::except($array, $keys);
    }
}

if (!function_exists('array_flatten')) {
    function array_flatten($array, $depth = INF) {
        return \Core\Arr::flatten($array, $depth);
    }
}

if (!function_exists('array_pluck')) {
    function array_pluck($array, $value, $key = null) {
        return \Core\Arr::pluck($array, $value, $key);
    }
}

if (!function_exists('array_wrap')) {
    function array_wrap($value) {
        return \Core\Arr::wrap($value);
    }
}

if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null) {
        if (is_null($key)) {
            return $target;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($target)) {
                if (!array_key_exists($segment, $target)) {
                    return $default;
                }
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (!isset($target->{$segment})) {
                    return $default;
                }
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }
}

// String helpers - Enhanced
if (!function_exists('str_camel')) {
    function str_camel($value) {
        return \Core\Str::camel($value);
    }
}

if (!function_exists('str_studly')) {
    function str_studly($value) {
        return \Core\Str::studly($value);
    }
}

if (!function_exists('str_snake')) {
    function str_snake($value, $delimiter = '_') {
        return \Core\Str::snake($value, $delimiter);
    }
}

if (!function_exists('str_kebab')) {
    function str_kebab($value) {
        return \Core\Str::kebab($value);
    }
}

if (!function_exists('str_title')) {
    function str_title($value) {
        return \Core\Str::title($value);
    }
}

if (!function_exists('str_upper')) {
    function str_upper($value) {
        return \Core\Str::upper($value);
    }
}

if (!function_exists('str_lower')) {
    function str_lower($value) {
        return \Core\Str::lower($value);
    }
}

if (!function_exists('str_after')) {
    function str_after($subject, $search) {
        return \Core\Str::after($subject, $search);
    }
}

if (!function_exists('str_before')) {
    function str_before($subject, $search) {
        return \Core\Str::before($subject, $search);
    }
}

if (!function_exists('str_replace_first')) {
    function str_replace_first($search, $replace, $subject) {
        return \Core\Str::replaceFirst($search, $replace, $subject);
    }
}

if (!function_exists('str_replace_last')) {
    function str_replace_last($search, $replace, $subject) {
        return \Core\Str::replaceLast($search, $replace, $subject);
    }
}

if (!function_exists('str_start')) {
    function str_start($value, $prefix) {
        return \Core\Str::start($value, $prefix);
    }
}

if (!function_exists('str_finish')) {
    function str_finish($value, $cap) {
        return \Core\Str::finish($value, $cap);
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class) {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('e')) {
    function e($value, $doubleEncode = true) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

// Date helpers
if (!function_exists('now')) {
    function now() {
        return new \Carbon\Carbon();
    }
}

if (!function_exists('today')) {
    function today() {
        return \Carbon\Carbon::today();
    }
}

// Security helpers
if (!function_exists('bcrypt')) {
    function bcrypt($value, $options = []) {
        return password_hash($value, PASSWORD_BCRYPT, $options);
    }
}

if (!function_exists('hash_check')) {
    function hash_check($value, $hashedValue) {
        return password_verify($value, $hashedValue);
    }
}

// Error handling
if (!function_exists('abort')) {
    function abort($code, $message = '') {
        http_response_code($code);
        throw new \Exception($message ?: "Error {$code}", $code);
    }
}

if (!function_exists('abort_if')) {
    function abort_if($condition, $code, $message = '') {
        if ($condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    function abort_unless($condition, $code, $message = '') {
        if (!$condition) {
            abort($code, $message);
        }
    }
}

// Validation helpers
if (!function_exists('validator')) {
    function validator($data, $rules, $messages = []) {
        return (new \Core\Validator())->validate($data, $rules, $messages);
    }
}

// Cache helpers
if (!function_exists('cache')) {
    function cache($key = null, $default = null) {
        if ($key === null) {
            return new \Core\Cache();
        }
        return \Core\Cache::get($key, $default);
    }
}

// Storage helpers
if (!function_exists('storage')) {
    function storage($disk = null) {
        return \Core\Storage::disk($disk);
    }
}

// Logging helpers
if (!function_exists('logger')) {
    function logger($message = null, $context = []) {
        if ($message === null) {
            return \Core\Log::getLogger();
        }
        return \Core\Log::info($message, $context);
    }
}

if (!function_exists('info')) {
    function info($message, $context = []) {
        return \Core\Log::info($message, $context);
    }
}

if (!function_exists('error_log')) {
    function error_log_custom($message, $context = []) {
        return \Core\Log::error($message, $context);
    }
}

// Event helpers
if (!function_exists('event')) {
    function event($event, $payload = []) {
        return \Core\Event::dispatch($event, $payload);
    }
}

// Authorization helpers
if (!function_exists('can')) {
    function can($ability, $arguments = []) {
        // Simple authorization check - can be expanded
        return true;
    }
}

if (!function_exists('cannot')) {
    function cannot($ability, $arguments = []) {
        return !can($ability, $arguments);
    }
}

// Blade directive helpers
if (!function_exists('buildClass')) {
    function buildClass($classes) {
        if (is_string($classes)) {
            return $classes;
        }
        
        if (is_array($classes)) {
            $result = [];
            foreach ($classes as $key => $value) {
                if (is_numeric($key)) {
                    $result[] = $value;
                } elseif ($value) {
                    $result[] = $key;
                }
            }
            return implode(' ', $result);
        }
        
        return '';
    }
}

if (!function_exists('buildStyle')) {
    function buildStyle($styles) {
        if (is_string($styles)) {
            return $styles;
        }
        
        if (is_array($styles)) {
            $result = [];
            foreach ($styles as $key => $value) {
                if ($value) {
                    $result[] = "{$key}: {$value}";
                }
            }
            return implode('; ', $result);
        }
        
        return '';
    }
}

// Misc helpers
if (!function_exists('value')) {
    function value($value) {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (!function_exists('filled')) {
    function filled($value) {
        return !blank($value);
    }
}

if (!function_exists('blank')) {
    function blank($value) {
        if (is_null($value)) {
            return true;
        }
        
        if (is_string($value)) {
            return trim($value) === '';
        }
        
        if (is_numeric($value) || is_bool($value)) {
            return false;
        }
        
        if ($value instanceof Countable) {
            return count($value) === 0;
        }
        
        return empty($value);
    }
}

if (!function_exists('optional')) {
    function optional($value = null, callable $callback = null) {
        if (is_null($callback)) {
            return new class($value) {
                private $value;
                public function __construct($value) { $this->value = $value; }
                public function __get($key) { return $this->value ? $this->value->$key : null; }
                public function __call($method, $parameters) { return $this->value ? $this->value->$method(...$parameters) : null; }
            };
        } elseif (!is_null($value)) {
            return $callback($value);
        }
    }
}

if (!function_exists('retry')) {
    function retry($times, callable $callback, $sleep = 0) {
        $attempts = 0;
        
        beginning:
        $attempts++;
        
        try {
            return $callback($attempts);
        } catch (Exception $e) {
            if ($attempts >= $times) {
                throw $e;
            }
            
            if ($sleep) {
                usleep($sleep * 1000);
            }
            
            goto beginning;
        }
    }
}

if (!function_exists('tap')) {
    function tap($value, callable $callback) {
        $callback($value);
        return $value;
    }
}

if (!function_exists('with')) {
    function with($value, callable $callback = null) {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field($method) {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('back')) {
    function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('session')) {
    function session($key = null, $default = null) {
        if ($key === null) {
            return new \Core\Session();
        }
        return \Core\Session::get($key, $default);
    }
}

if (!function_exists('cache')) {
    function cache($key = null, $default = null) {
        if ($key === null) {
            return new \Core\Cache();
        }
        return \Core\Cache::get($key, $default);
    }
}

if (!function_exists('storage')) {
    function storage($disk = null) {
        return \Core\Storage::disk($disk);
    }
}

if (!function_exists('logger')) {
    function logger($message = null, $context = []) {
        if ($message === null) {
            return \Core\Log::getLogger();
        }
        return \Core\Log::info($message, $context);
    }
}

if (!function_exists('event')) {
    function event($event, $payload = []) {
        return \Core\Event::dispatch($event, $payload);
    }
}

if (!function_exists('bcrypt')) {
    function bcrypt($value, $options = []) {
        return \Core\Hash::make($value, $options);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return \Core\Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field($method) {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('old')) {
    function old($key, $default = null) {
        return \Core\Session::flash('old.' . $key) ?? $default;
    }
}

if (!function_exists('back')) {
    function back() {
        $previous = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($previous);
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return env('APP_URL', 'http://localhost') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        return env('APP_URL', 'http://localhost') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    function route($name, $params = []) {
        // Simple route helper - can be expanded
        return url($name);
    }
}

if (!function_exists('str_random')) {
    function str_random($length = 16) {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('str_slug')) {
    function str_slug($title, $separator = '-') {
        $title = preg_replace('/[^a-z0-9\s-]/i', '', strtolower($title));
        return preg_replace('/[\s-]+/', $separator, trim($title));
    }
}

if (!function_exists('str_limit')) {
    function str_limit($value, $limit = 100, $end = '...') {
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('abort')) {
    function abort($code, $message = '') {
        http_response_code($code);
        throw new \Exception($message ?: "Error {$code}", $code);
    }
}

if (!function_exists('now')) {
    function now() {
        return new \Carbon\Carbon();
    }
}

if (!function_exists('today')) {
    function today() {
        return \Carbon\Carbon::today();
    }
}

// Number formatting helpers
if (!function_exists('number_format_short')) {
    function number_format_short($number, $decimals = 1) {
        if ($number >= 1000000000) {
            return number_format($number / 1000000000, $decimals) . 'B';
        }
        if ($number >= 1000000) {
            return number_format($number / 1000000, $decimals) . 'M';
        }
        if ($number >= 1000) {
            return number_format($number / 1000, $decimals) . 'K';
        }
        return number_format($number, $decimals);
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('currency')) {
    function currency($amount, $currency = 'USD', $decimals = 2) {
        $symbols = ['USD' => '$', 'EUR' => '', 'GBP' => '', 'NGN' => '', 'JPY' => ''];
        $symbol = $symbols[$currency] ?? $currency . ' ';
        return $symbol . number_format($amount, $decimals);
    }
}

if (!function_exists('percentage')) {
    function percentage($value, $total, $decimals = 2) {
        if ($total == 0) return '0%';
        return number_format(($value / $total) * 100, $decimals) . '%';
    }
}

// Date/Time helpers
if (!function_exists('diff_for_humans')) {
    function diff_for_humans($date) {
        if (is_string($date)) {
            $date = new \Carbon\Carbon($date);
        }
        if ($date instanceof \Carbon\Carbon) {
            return $date->diffForHumans();
        }
        return $date;
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'Y-m-d H:i:s') {
        if (is_string($date)) {
            $date = new \Carbon\Carbon($date);
        }
        if ($date instanceof \Carbon\Carbon) {
            return $date->format($format);
        }
        return $date;
    }
}
