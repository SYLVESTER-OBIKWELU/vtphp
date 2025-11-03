<?php

namespace Core;

class View
{
    protected static $viewPath;
    protected static $cachePath;
    protected static $shared = [];
    protected static $sections = [];
    protected static $extends = null;
    protected static $stacks = [];

    public static function setViewPath($path)
    {
        self::$viewPath = $path;
    }

    public static function setCachePath($path)
    {
        self::$cachePath = $path;
    }

    public static function share($key, $value)
    {
        self::$shared[$key] = $value;
    }

    public static function render($view, $data = [])
    {
        $viewPath = self::$viewPath ?: dirname(__DIR__) . '/resources/views';
        $cachePath = self::$cachePath ?: dirname(__DIR__) . '/storage/cache/views';

        // Try .blade.php first, then fall back to .php
        $viewFile = self::findViewFile($viewPath, $view);

        if (!$viewFile) {
            throw new \Exception("View not found: {$view}");
        }

        // Reset extends and sections
        self::$extends = null;
        self::$sections = [];

        // Compile the view (Blade-like syntax)
        $compiled = self::compile(file_get_contents($viewFile));

        // Check if view caching is enabled (default: disabled)
        $cacheEnabled = env('VIEW_CACHE', false);
        
        if ($cacheEnabled) {
            // Create cache directory if it doesn't exist
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            $cacheFile = $cachePath . '/' . md5($view) . '.php';
            
            // Check if cache exists and is fresh
            if (!file_exists($cacheFile) || filemtime($viewFile) > filemtime($cacheFile)) {
                file_put_contents($cacheFile, $compiled);
            }
        } else {
            // No caching - create temporary file
            $cacheFile = $cachePath . '/' . md5($view . microtime(true)) . '.php';
            
            // Create cache directory if it doesn't exist
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
            
            file_put_contents($cacheFile, $compiled);
        }

        // Merge shared data
        $data = array_merge(self::$shared, $data);
        
        // Convert arrays to collections for blade helper methods
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                // Skip associative arrays (keep as arrays)
                continue;
            } elseif (is_array($value)) {
                // Convert indexed arrays to collections
                $data[$key] = collect($value);
            }
        }

        // Extract data and render
        extract($data);
        ob_start();
        include $cacheFile;
        $content = ob_get_clean();
        
        // Clean up temporary cache file if caching is disabled
        if (!env('VIEW_CACHE', false) && file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        // If this view extends a layout, render the layout
        if (self::$extends) {
            $data['__sections'] = self::$sections;
            return self::render(self::$extends, $data);
        }

        return $content;
    }

    protected static function compile($content)
    {
        // @extends
        $content = preg_replace_callback('/@extends\s*\([\'"](.+?)[\'"]\)/', function($matches) {
            return '<?php self::$extends = "' . $matches[1] . '"; ?>';
        }, $content);

        // @section...@endsection
        $content = preg_replace('/@section\s*\([\'"](.+?)[\'"]\)/', '<?php self::startSection("$1"); ?>', $content);
        $content = preg_replace('/@endsection/', '<?php self::endSection(); ?>', $content);

        // @yield
        $content = preg_replace('/@yield\s*\([\'"](.+?)[\'"](?:,\s*[\'"](.+?)[\'"])?\)/', '<?php echo self::yieldContent("$1", "$2"); ?>', $content);

        // @component...@endcomponent
        $content = preg_replace('/@component\s*\([\'"](.+?)[\'"]\)/', '<?php self::startComponent("$1"); ?>', $content);
        $content = preg_replace('/@endcomponent/', '<?php echo self::endComponent(); ?>', $content);

        // @slot...@endslot
        $content = preg_replace('/@slot\s*\([\'"](.+?)[\'"]\)/', '<?php self::startSlot("$1"); ?>', $content);
        $content = preg_replace('/@endslot/', '<?php self::endSlot(); ?>', $content);

        // @include with optional data
        $content = preg_replace_callback('/@include\s*\([\'"](.+?)[\'"](?:,\s*(\[.+?\]))?\)/', function($matches) {
            $view = $matches[1];
            $data = $matches[2] ?? '[]';
            return '<?php echo self::render("' . $view . '", ' . $data . '); ?>';
        }, $content);

        // @if, @elseif, @else, @endif
        $content = preg_replace('/@if\s*\((.*?)\)\s*$/m', '<?php if($1): ?>', $content);
        $content = preg_replace('/@elseif\s*\((.*?)\)\s*$/m', '<?php elseif($1): ?>', $content);
        $content = preg_replace('/@else\s*$/m', '<?php else: ?>', $content);
        $content = preg_replace('/@endif\s*$/m', '<?php endif; ?>', $content);

        // @unless, @endunless
        $content = preg_replace('/@unless\s*\((.*?)\)/', '<?php if(!($1)): ?>', $content);
        $content = preg_replace('/@endunless/', '<?php endif; ?>', $content);

        // @isset, @endisset
        $content = preg_replace('/@isset\s*\((.*?)\)/', '<?php if(isset($1)): ?>', $content);
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);

        // @empty, @endempty
        $content = preg_replace('/@empty\s*\((.*?)\)/', '<?php if(empty($1)): ?>', $content);
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        // @foreach, @endforeach
        $content = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach($1): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        // @forelse, @empty, @endforelse
        $content = preg_replace('/@forelse\s*\((.*?)\)/', '<?php if(!empty($1)): foreach($1): ?>', $content);
        $content = preg_replace('/@endforelse/', '<?php endforeach; endif; ?>', $content);

        // @for, @endfor
        $content = preg_replace('/@for\s*\((.*?)\)/', '<?php for($1): ?>', $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        // @while, @endwhile
        $content = preg_replace('/@while\s*\((.*?)\)/', '<?php while($1): ?>', $content);
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        // @continue, @break
        $content = preg_replace('/@continue/', '<?php continue; ?>', $content);
        $content = preg_replace('/@break/', '<?php break; ?>', $content);

        // @php, @endphp
        $content = preg_replace('/@php/', '<?php', $content);
        $content = preg_replace('/@endphp/', '?>', $content);

        // {{ $variable }} - escaped echo with safe array/object handling
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function($matches) {
            $expression = trim($matches[1]);
            return '<?php echo htmlspecialchars((' . $expression . ') ?? "", ENT_QUOTES, "UTF-8"); ?>';
        }, $content);

        // {!! $variable !!} - unescaped echo
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?php echo $1; ?>', $content);

        // @csrf
        $content = preg_replace('/@csrf/', '<?php echo \'<input type="hidden" name="_token" value="\' . ($_SESSION["_token"] ?? "") . \'">\'; ?>', $content);

        // @method
        $content = preg_replace('/@method\s*\([\'"](.+?)[\'"]\)/', '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>', $content);

        // @auth, @guest, @endauth, @endguest
        $content = preg_replace('/@auth\s*$/m', '<?php if(isset($_SESSION["user"])): ?>', $content);
        $content = preg_replace('/@endauth\s*$/m', '<?php endif; ?>', $content);
        $content = preg_replace('/@guest\s*$/m', '<?php if(!isset($_SESSION["user"])): ?>', $content);
        $content = preg_replace('/@endguest\s*$/m', '<?php endif; ?>', $content);

        // @error, @enderror
        $content = preg_replace('/@error\s*\([\'"](.+?)[\'"]\)/', '<?php if(isset($errors) && $errors->has("$1")): ?>', $content);
        $content = preg_replace('/@enderror\s*$/m', '<?php endif; ?>', $content);

        // @json
        $content = preg_replace('/@json\s*\((.*?)\)/', '<?php echo json_encode($1); ?>', $content);

        // @dd (dump and die)
        $content = preg_replace('/@dd\s*\((.*?)\)/', '<?php dd($1); ?>', $content);

        // @dump
        $content = preg_replace('/@dump\s*\((.*?)\)/', '<?php dump($1); ?>', $content);

        // @push, @endpush
        $content = preg_replace('/@push\s*\([\'"](.+?)[\'"]\)/', '<?php self::startPush("$1"); ?>', $content);
        $content = preg_replace('/@endpush\s*$/m', '<?php self::endPush(); ?>', $content);

        // @stack
        $content = preg_replace('/@stack\s*\([\'"](.+?)[\'"]\)/', '<?php echo self::yieldPushContent("$1"); ?>', $content);

        // @prepend, @endprepend
        $content = preg_replace('/@prepend\s*\([\'"](.+?)[\'"]\)/', '<?php self::startPrepend("$1"); ?>', $content);
        $content = preg_replace('/@endprepend\s*$/m', '<?php self::endPrepend(); ?>', $content);

        // @once, @endonce
        $content = preg_replace('/@once\s*$/m', '<?php if(!isset($__once_' . md5(random_bytes(8)) . ')): $__once_' . md5(random_bytes(8)) . ' = true; ?>', $content);
        $content = preg_replace('/@endonce\s*$/m', '<?php endif; ?>', $content);

        // @can, @cannot, @endcan, @endcannot
        $content = preg_replace('/@can\s*\([\'"](.+?)[\'"]\)/', '<?php if(can("$1")): ?>', $content);
        $content = preg_replace('/@endcan\s*$/m', '<?php endif; ?>', $content);
        $content = preg_replace('/@cannot\s*\([\'"](.+?)[\'"]\)/', '<?php if(!can("$1")): ?>', $content);
        $content = preg_replace('/@endcannot\s*$/m', '<?php endif; ?>', $content);

        // @class directive
        $content = preg_replace_callback('/@class\s*\((.*?)\)/', function($matches) {
            return '<?php echo \'class="\' . buildClass(' . $matches[1] . ') . \'"\'; ?>';
        }, $content);

        // @style directive
        $content = preg_replace_callback('/@style\s*\((.*?)\)/', function($matches) {
            return '<?php echo \'style="\' . buildStyle(' . $matches[1] . ') . \'"\'; ?>';
        }, $content);

        // @selected
        $content = preg_replace('/@selected\s*\((.*?)\)/', '<?php echo ($1) ? \'selected\' : \'\'; ?>', $content);

        // @checked
        $content = preg_replace('/@checked\s*\((.*?)\)/', '<?php echo ($1) ? \'checked\' : \'\'; ?>', $content);

        // @disabled
        $content = preg_replace('/@disabled\s*\((.*?)\)/', '<?php echo ($1) ? \'disabled\' : \'\'; ?>', $content);

        // @readonly
        $content = preg_replace('/@readonly\s*\((.*?)\)/', '<?php echo ($1) ? \'readonly\' : \'\'; ?>', $content);

        // @required
        $content = preg_replace('/@required\s*\((.*?)\)/', '<?php echo ($1) ? \'required\' : \'\'; ?>', $content);

        // @env
        $content = preg_replace('/@env\s*\([\'"](.+?)[\'"]\)/', '<?php if(env("APP_ENV") === "$1"): ?>', $content);
        $content = preg_replace('/@endenv\s*$/m', '<?php endif; ?>', $content);

        // @production
        $content = preg_replace('/@production\s*$/m', '<?php if(env("APP_ENV") === "production"): ?>', $content);
        $content = preg_replace('/@endproduction\s*$/m', '<?php endif; ?>', $content);

        // @verbatim, @endverbatim (for JS frameworks)
        $content = preg_replace_callback('/@verbatim\s*(.*?)\s*@endverbatim/s', function($matches) {
            return $matches[1];
        }, $content);

        // Laravel blade helper directives
        // @route directive
        $content = preg_replace_callback('/@route\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\)/', function($matches) {
            $name = $matches[1];
            $params = $matches[2] ?? '[]';
            return '<?php echo route("' . $name . '", ' . $params . '); ?>';
        }, $content);

        // @url directive
        $content = preg_replace('/@url\s*\([\'"](.+?)[\'"]\)/', '<?php echo url("$1"); ?>', $content);

        // @asset directive
        $content = preg_replace('/@asset\s*\([\'"](.+?)[\'"]\)/', '<?php echo asset("$1"); ?>', $content);

        return $content;
    }

    // Push/Stack functionality
    protected static $pushes = [];
    protected static $currentPush = null;

    public static function startPush($name)
    {
        self::$currentPush = $name;
        ob_start();
    }

    public static function endPush()
    {
        $content = ob_get_clean();
        $name = self::$currentPush;
        
        if (!isset(self::$pushes[$name])) {
            self::$pushes[$name] = [];
        }
        
        self::$pushes[$name][] = $content;
        self::$currentPush = null;
    }

    public static function startPrepend($name)
    {
        self::$currentPush = $name;
        ob_start();
    }

    public static function endPrepend()
    {
        $content = ob_get_clean();
        $name = self::$currentPush;
        
        if (!isset(self::$pushes[$name])) {
            self::$pushes[$name] = [];
        }
        
        array_unshift(self::$pushes[$name], $content);
        self::$currentPush = null;
    }

    public static function yieldPushContent($name)
    {
        return isset(self::$pushes[$name]) ? implode('', self::$pushes[$name]) : '';
    }

    public static function startSection($name)
    {
        self::$sections[$name] = '';
        ob_start();
    }

    public static function endSection()
    {
        $name = array_key_last(self::$sections);
        self::$sections[$name] = ob_get_clean();
    }

    public static function yieldContent($name, $default = '')
    {
        return self::$sections[$name] ?? $default;
    }

    protected static $currentComponent = null;
    protected static $componentStack = [];
    protected static $slots = [];

    public static function startComponent($name)
    {
        self::$componentStack[] = [
            'name' => $name,
            'slots' => []
        ];
        ob_start();
    }

    public static function endComponent()
    {
        $content = ob_get_clean();
        $component = array_pop(self::$componentStack);
        
        $data = array_merge($component['slots'], ['slot' => $content]);
        return self::render($component['name'], $data);
    }

    public static function startSlot($name)
    {
        $currentIndex = count(self::$componentStack) - 1;
        self::$componentStack[$currentIndex]['currentSlot'] = $name;
        ob_start();
    }

    public static function endSlot()
    {
        $currentIndex = count(self::$componentStack) - 1;
        $slotName = self::$componentStack[$currentIndex]['currentSlot'];
        self::$componentStack[$currentIndex]['slots'][$slotName] = ob_get_clean();
    }

    protected static function getViewPath($view)
    {
        $viewPath = self::$viewPath ?: dirname(__DIR__) . '/resources/views';
        $file = self::findViewFile($viewPath, $view);
        return $file ?: $viewPath . '/' . str_replace('.', '/', $view) . '.php';
    }

    protected static function findViewFile($viewPath, $view)
    {
        $relativePath = str_replace('.', '/', $view);
        
        // Check .blade.php first (preferred)
        $bladePath = $viewPath . '/' . $relativePath . '.blade.php';
        if (file_exists($bladePath)) {
            return $bladePath;
        }
        
        // Fall back to .php
        $phpPath = $viewPath . '/' . $relativePath . '.php';
        if (file_exists($phpPath)) {
            return $phpPath;
        }
        
        return null;
    }
}
