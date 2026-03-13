<?php

if (!function_exists('erp_app_url')) {
    function erp_app_url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('erp_asset_url')) {
    function erp_asset_url(string $path): string
    {
        $relativePath = ltrim($path, '/');
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $version = is_file($fullPath) ? (string) filemtime($fullPath) : null;
        $url = erp_app_url($relativePath);

        return $version ? $url . '?v=' . $version : $url;
    }
}

if (!function_exists('erp_redirect')) {
    function erp_redirect(string $path): never
    {
        $target = erp_app_url($path);

        if (!headers_sent()) {
            header('Location: ' . $target);
            exit();
        }

        echo '<script>window.location.href=' . json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        exit();
    }
}

if (!function_exists('erp_send_private_page_headers')) {
    function erp_send_private_page_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
