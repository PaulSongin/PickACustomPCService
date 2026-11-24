<?php
/**
 * Модерация отзывов из командной строки (если Telegram-бот не настроен).
 *
 *   php backend/bin/reviews.php              — список отзывов на модерации
 *   php backend/bin/reviews.php approve 12   — опубликовать отзыв №12
 *   php backend/bin/reviews.php reject 12    — отклонить отзыв №12
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Только для командной строки\n");
}
require __DIR__ . '/../bootstrap.php';

use App\Models\ReviewModel;

$reviews = new ReviewModel();
$command = $argv[1] ?? 'list';

if ($command === 'list') {
    $pending = $reviews->pending(100);
    if (!$pending) {
        echo "Отзывов на модерации нет.\n";
    }
    foreach ($pending as $r) {
        echo "№{$r['id']} · {$r['created_at']} · {$r['author_name']} · " . str_repeat('★', $r['rating'])
            . ($r['product_name'] ? " · {$r['product_name']}" : '') . "\n  {$r['text']}\n\n";
    }
    exit(0);
}

if (in_array($command, ['approve', 'reject'], true) && isset($argv[2]) && ctype_digit($argv[2])) {
    $ok = $reviews->moderate((int) $argv[2], $command === 'approve' ? 'approved' : 'rejected', 'консоль');
    echo $ok ? "Готово.\n" : "Отзыв не найден или уже промодерирован.\n";
    exit($ok ? 0 : 1);
}

fwrite(STDERR, "Использование: php backend/bin/reviews.php [list | approve ID | reject ID]\n");
exit(1);
