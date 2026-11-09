<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Правила совместимости — копия checkCompat() из js/data.js.
 * На сайте они дают мгновенную подсказку, а здесь повторно проверяют заказ,
 * чтобы несовместимую сборку нельзя было отправить в обход интерфейса.
 */
final class CompatibilityService
{
    /** Запас мощности блока питания: (CPU + GPU + 100 Вт на остальное) × 1.2 */
    public function requiredPower(array $build): int
    {
        $cpu = $build['cpu'] ?? null;
        $gpu = $build['gpu'] ?? null;
        return (int) round((($cpu['tdp'] ?? 0) + ($gpu['tdp'] ?? 0) + 100) * 1.2);
    }

    /**
     * @param array<string, array> $build категория → товар (в формате API)
     * @return list<array{cats: list<string>, text: string}> список конфликтов
     */
    public function check(array $build): array
    {
        $cpu = $build['cpu'] ?? null;
        $mb = $build['mb'] ?? null;
        $ram = $build['ram'] ?? null;
        $gpu = $build['gpu'] ?? null;
        $cool = $build['cooler'] ?? null;
        $case = $build['case'] ?? null;
        $psu = $build['psu'] ?? null;
        $errors = [];
        $add = static function (array $cats, string $text) use (&$errors): void {
            $errors[] = ['cats' => $cats, 'text' => $text];
        };

        if ($cpu && $mb && ($cpu['socket'] ?? null) !== ($mb['socket'] ?? null)) {
            $add(['cpu', 'mb'], "Сокет процессора ({$cpu['socket']}) не подходит к плате ({$mb['socket']})");
        }
        if ($mb && $ram && ($mb['mem'] ?? null) !== ($ram['mem'] ?? null)) {
            $add(['mb', 'ram'], "Плата работает с {$mb['mem']}, а память — {$ram['mem']}");
        }
        if ($mb && $case && !in_array($mb['ff'] ?? null, $case['ff'] ?? [], true)) {
            $add(['mb', 'case'], "Плата {$mb['ff']} не помещается в этот корпус");
        }
        if ($gpu && $case && ($gpu['len'] ?? 0) > ($case['gpuMax'] ?? PHP_INT_MAX)) {
            $add(['gpu', 'case'], "Видеокарта ({$gpu['len']} мм) длиннее допустимого для корпуса ({$case['gpuMax']} мм)");
        }

        $socket = $cpu['socket'] ?? $mb['socket'] ?? null;
        if ($cool && $socket && !in_array($socket, $cool['sockets'] ?? [], true)) {
            $add(['cooler', $cpu ? 'cpu' : 'mb'], "Кулер не поддерживает сокет $socket");
        }
        if ($cool && $cpu && ($cool['tdpMax'] ?? 0) < ($cpu['tdp'] ?? 0)) {
            $add(['cooler', 'cpu'], "Кулер рассчитан на {$cool['tdpMax']} Вт, процессор выделяет {$cpu['tdp']} Вт");
        }
        if ($cool && $case && ($cool['h'] ?? 0) > ($case['coolerMax'] ?? PHP_INT_MAX)) {
            $add(['cooler', 'case'], "Кулер ({$cool['h']} мм) выше допустимого для корпуса ({$case['coolerMax']} мм)");
        }

        if ($psu && ($cpu || $gpu)) {
            $need = $this->requiredPower($build);
            if (($psu['watt'] ?? 0) < $need) {
                $add(['psu', 'cpu', 'gpu'], "Мощности блока питания не хватит: нужно от $need Вт");
            }
        }
        return $errors;
    }
}
