<?php

namespace App\Support;

class PublicStorageUrl
{
    private const PREFIX = 'storage/app/public/';

    public static function from(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $relative = ltrim($path, '/');

        if (str_starts_with($relative, self::PREFIX)) {
            $relative = substr($relative, strlen(self::PREFIX));
        } elseif (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        return url(self::PREFIX.ltrim($relative, '/'));
    }
}
