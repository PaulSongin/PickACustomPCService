<?php
declare(strict_types=1);

namespace App\Services;

/** Жизненный цикл заказа: new → building → built → delivery → done (или rejected) */
final class OrderStatus
{
    public const LABELS = [
        'new'      => '🆕 Новый',
        'building' => '🛠 Сборка',
        'built'    => '🔧 Собран',
        'delivery' => '🚚 Доставка',
        'done'     => '📦 Выполнен',
        'rejected' => '❌ Отклонён',
    ];

    /** Следующий шаг и подпись кнопки для сборщика */
    public const NEXT = [
        'building' => ['built', '🔧 Собран'],
        'built'    => ['delivery', '🚚 Передать в доставку'],
        'delivery' => ['done', '📦 Доставлен'],
    ];

    /** Уведомление клиенту при переходе в статус */
    public const CLIENT_TEXT = [
        'building' => '✅ Заказ №%d принят и передан в сборку.',
        'built'    => '🔧 Заказ №%d собран.',
        'delivery' => '🚚 Заказ №%d передан в доставку.',
        'done'     => '📦 Заказ №%d выполнен. Спасибо, что выбрали нас!',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
