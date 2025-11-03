<?php

/**
 * Vite Asset Helper
 * Helps load Vite assets in development and production
 */

if (!function_exists('vite_assets')) {
    /**
     * Generate Vite asset tags
     */
    function vite_assets($entry = 'resources/js/app.js')
    {
        $devServerUrl = 'http://localhost:5173';
        $manifest = __DIR__ . '/../public_html/build/manifest.json';

        // Check if we're in development mode (Vite dev server is running)
        $isDev = false;
        if (function_exists('env')) {
            $isDev = env('APP_ENV') === 'development';
        }

        // Try to detect if dev server is running
        if ($isDev || @file_get_contents($devServerUrl) !== false) {
            // Development mode - use Vite dev server
            $html = '<script type="module" src="' . $devServerUrl . '/@vite/client"></script>';
            $html .= '<script type="module" src="' . $devServerUrl . '/' . $entry . '"></script>';
            
            return $html;
        }

        // Production mode - use built assets
        if (!file_exists($manifest)) {
            return '<!-- Vite manifest not found. Run: npm run build -->';
        }

        $manifestData = json_decode(file_get_contents($manifest), true);
        
        $html = '';
        
        if (isset($manifestData[$entry])) {
            $file = $manifestData[$entry]['file'];
            $html .= '<script type="module" src="/build/' . $file . '"></script>';
            
            // Include CSS if present
            if (isset($manifestData[$entry]['css'])) {
                foreach ($manifestData[$entry]['css'] as $css) {
                    $html .= '<link rel="stylesheet" href="/build/' . $css . '">';
                }
            }
        }

        // Include CSS entry if specified
        if (str_contains($entry, '.css')) {
            $cssEntry = $entry;
            if (isset($manifestData[$cssEntry])) {
                $file = $manifestData[$cssEntry]['file'];
                $html .= '<link rel="stylesheet" href="/build/' . $file . '">';
            }
        }

        return $html;
    }
}

if (!function_exists('vite')) {
    /**
     * Alias for vite_assets
     */
    function vite($entry = 'resources/js/app.js')
    {
        return vite_assets($entry);
    }
}
