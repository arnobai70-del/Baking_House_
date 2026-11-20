<?php

namespace App\Support;

/**
 * Versioned URLs for the static CSS / JS files: "?v=<content hash>" changes whenever a file
 * changes, so the web server can send long-lived Cache-Control headers for /css and /js without
 * visitors ever getting a stale file after a deploy.
 */
class Asset
{
    public static function url(string $path): string
    {
        static $versions = [];
        $versions[$path] ??= is_file($file = public_path($path)) ? substr((string) md5_file($file), 0, 10) : null;

        return asset($path).($versions[$path] !== null ? '?v='.$versions[$path] : '');
    }
}
