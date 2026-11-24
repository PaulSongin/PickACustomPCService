<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Logger;
use App\Models\ProductModel;
use App\Models\RateLimitModel;
use App\Models\ReviewModel;
use App\Services\Telegram\TelegramBot;
use App\Validators\ReviewValidator;
use App\Validators\ValidationException;

final class ReviewService
{
    /** Ссылки и упоминания — частый признак спама: такие отзывы всегда идут на модерацию */
    private const SUSPICIOUS = '~(https?://|www\.|t\.me/|@[a-z0-9_]{4,}|\b[a-z0-9-]+\.(ru|by|com|net|org|io|xyz|top|info|online|site)\b)~iu';

    public function __construct(
        private readonly ReviewModel $reviews = new ReviewModel(),
        private readonly ProductModel $products = new ProductModel(),
        private readonly ReviewValidator $validator = new ReviewValidator(),
        private readonly RateLimitModel $limits = new RateLimitModel(),
    ) {
    }

    /** @return array{id:int, status:string} */
    public function create(array $body, string $ip): array
    {
        // honeypot: скрытое поле, которое заполняют только боты — делаем вид, что всё хорошо
        if (!empty($body['website'])) {
            Logger::info("Review honeypot triggered from $ip");
            return ['id' => 0, 'status' => 'pending'];
        }

        $max = (int) Config::get('reviews.max_per_hour', 3);
        if ($this->limits->count('review', $ip, 3600) >= $max) {
            throw new HttpException(429, 'Вы уже оставили несколько отзывов. Попробуйте позже.');
        }

        $data = $this->validator->validate($body);

        $productId = null;
        if ($data['product'] !== null) {
            $found = $this->products->findByCodes([$data['product']]);
            if (!isset($found[$data['product']])) {
                throw new ValidationException('Товар не найден', ['field' => 'product']);
            }
            $productId = $found[$data['product']]['_pk'];
        }

        if ($this->reviews->isDuplicate($ip, $data['text'])) {
            throw new ValidationException('Такой отзыв уже отправлен');
        }

        $premoderation = (bool) Config::get('reviews.premoderation', true);
        $status = $premoderation || preg_match(self::SUSPICIOUS, $data['text'] . ' ' . $data['name']) ? 'pending' : 'approved';

        $id = $this->reviews->create([
            'product_id' => $productId,
            'name'       => $data['name'],
            'rating'     => $data['rating'],
            'text'       => $data['text'],
            'status'     => $status,
            'ip'         => $ip,
        ]);
        $this->limits->hit('review', $ip);

        if ($status === 'pending' && ($bot = TelegramBot::fromConfig()) !== null) {
            App::defer(static fn () => $bot->announceReview($id));
        }
        return ['id' => $id, 'status' => $status];
    }
}
