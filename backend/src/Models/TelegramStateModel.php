<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Состояние Telegram-бота в БД: отправленные карточки, переписка поддержки, ожидаемые ответы */
final class TelegramStateModel
{
    // ---------- app_state (ключ → значение) ----------

    public function get(string $name): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM app_state WHERE name = ?');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function set(string $name, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO app_state (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$name, $value]);
    }

    // ---------- карточки заказов и отзывов у сборщиков ----------

    public function addMessage(string $entityType, int $entityId, int $chatId, int $messageId): void
    {
        Database::pdo()->prepare(
            'INSERT IGNORE INTO tg_messages (chat_id, message_id, entity_type, entity_id) VALUES (?, ?, ?, ?)'
        )->execute([$chatId, $messageId, $entityType, $entityId]);
    }

    /** @return list<array{chat_id:int, message_id:int}> */
    public function messages(string $entityType, int $entityId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT chat_id, message_id FROM tg_messages WHERE entity_type = ? AND entity_id = ? ORDER BY created_at'
        );
        $stmt->execute([$entityType, $entityId]);
        return array_map(static fn (array $r): array => [
            'chat_id' => (int) $r['chat_id'], 'message_id' => (int) $r['message_id'],
        ], $stmt->fetchAll());
    }

    // ---------- поддержка: сообщение у сборщика → чат клиента ----------

    public function mapSupport(int $builderChatId, int $messageId, int $clientChatId): void
    {
        Database::pdo()->prepare(
            'INSERT IGNORE INTO tg_support_messages (builder_chat_id, message_id, client_chat_id) VALUES (?, ?, ?)'
        )->execute([$builderChatId, $messageId, $clientChatId]);
        if (random_int(1, 100) === 1) {
            Database::pdo()->exec('DELETE FROM tg_support_messages WHERE created_at < NOW() - INTERVAL 60 DAY');
        }
    }

    public function supportTarget(int $builderChatId, int $messageId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT client_chat_id FROM tg_support_messages WHERE builder_chat_id = ? AND message_id = ?'
        );
        $stmt->execute([$builderChatId, $messageId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    // ---------- ожидаемое действие сборщика (например, причина отказа) ----------

    public function setPending(int $userId, string $action, int $entityId): void
    {
        Database::pdo()->prepare(
            'REPLACE INTO tg_pending_actions (tg_user_id, action, entity_id) VALUES (?, ?, ?)'
        )->execute([$userId, $action, $entityId]);
    }

    /** @return array{action:string, entity_id:int}|null */
    public function pending(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT action, entity_id FROM tg_pending_actions WHERE tg_user_id = ? AND created_at > NOW() - INTERVAL 1 DAY'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? ['action' => $row['action'], 'entity_id' => (int) $row['entity_id']] : null;
    }

    public function clearPending(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM tg_pending_actions WHERE tg_user_id = ?')->execute([$userId]);
    }
}
