<?php
if (!function_exists('vilcon_send_no_cache_headers')) {
    function vilcon_send_no_cache_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

if (!function_exists('vilcon_asset_url')) {
    function vilcon_asset_url(string $webPath): string
    {
        $basePrefix = '/vilcon-system-github-main/';
        $normalizedPath = str_replace('\\', '/', $webPath);
        $relativePath = str_starts_with($normalizedPath, $basePrefix)
            ? substr($normalizedPath, strlen($basePrefix))
            : ltrim($normalizedPath, '/');

        $relativePath = ltrim($relativePath, '/');
        $absolutePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : gmdate('YmdHis');

        return $basePrefix . $relativePath . '?v=' . rawurlencode($version);
    }
}
