<?php
declare(strict_types=1);

namespace App\Core;

/** Простейший лог в backend/storage/logs/app.log */
final class Logger
{
    public static function error(string|\Throwable $error): void
    {
        self::write('ERROR', $error instanceof \Throwable
            ? get_class($error) . ': ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine()
            : $error);
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    private static function write(string $level, string $message): void
    {
        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $message);
        $dir = BACKEND_ROOT . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log(rtrim($line));
            return;
        }
        if (@file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line));
        }
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $line);
        }
    }
}
