<?php
/**
 * Пример настроек. Скопируйте в config.php рядом и поменяйте под себя.
 * config.php не попадает в git (там пароль от БД и токен бота).
 * Пока config.php нет, используется этот файл.
 */
return [
    'app' => [
        'debug'    => false,            // true — показывать текст ошибок в ответах API (только для разработки!)
        'timezone' => 'Europe/Minsk',
        'currency' => 'BYN',
    ],

    'db' => [
        'host'     => '127.0.0.1',      // в Open Server Panel 6 можно указать имя модуля, например 'MySQL-8.0'
        'port'     => 3306,
        'name'     => 'pcbuild',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'telegram' => [
        'token'          => '',         // токен от @BotFather; пусто — бот выключен, заказы просто сохраняются в БД
        'builder_ids'    => [],         // Telegram ID сборщиков, например [111111111, 222222222]
        'bot_username'   => '',         // логин бота без @ (если пусто — узнаётся через getMe и кэшируется)
        'webhook_secret' => '',         // секрет для режима webhook (заголовок X-Telegram-Bot-Api-Secret-Token)
        'api_url'        => 'https://api.telegram.org',
        'ca_bundle'      => '',         // путь к cacert.pem, если cURL ругается на SSL-сертификат
    ],

    'orders' => [
        'max_per_hour' => 5,            // принятых заказов в час с одного IP
    ],

    'reviews' => [
        'premoderation' => true,        // true — отзыв публикуется после одобрения в Telegram / backend/bin/reviews.php
        'max_per_hour'  => 3,
    ],

    'builds' => [
        'max_per_hour' => 30,           // сохранений сборки «Поделиться» в час с одного IP
    ],
];
