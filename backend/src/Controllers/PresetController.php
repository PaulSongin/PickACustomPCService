<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\PresetModel;
use App\Validators\ValidationException;

final class PresetController
{
    /** GET /api/presets?purpose=gaming&min=4500&max=9500 — подбор готовых сборок по назначению и бюджету */
    public function index(Request $request): Response
    {
        $purpose = $request->query('purpose', '');
        if ($purpose !== '' && !in_array($purpose, PresetModel::PURPOSES, true)) {
            throw new ValidationException('Назначение: ' . implode(', ', PresetModel::PURPOSES));
        }

        $bounds = [];
        foreach (['min', 'max'] as $key) {
            $value = $request->query($key, '');
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || $value < 0) {
                throw new ValidationException("Параметр $key должен быть неотрицательным числом");
            }
            $bounds[$key] = (float) $value;
        }

        $items = array_values(array_filter(
            (new PresetModel())->list($purpose === '' ? null : $purpose),
            static fn (array $p): bool => (!isset($bounds['min']) || $p['total'] >= $bounds['min'])
                && (!isset($bounds['max']) || $p['total'] < $bounds['max'])
        ));
        return Response::json(['items' => $items, 'count' => count($items)]);
    }
}
