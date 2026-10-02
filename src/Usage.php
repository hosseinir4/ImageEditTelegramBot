<?php

declare(strict_types=1);

namespace ImageBot;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatMemberStatus;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatMemberRestricted;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class Usage
{
    public const FREE_LIMIT = 10;

    public static function count(int $userId): int
    {
        $statement = Database::connection()->prepare('SELECT `usage` FROM users WHERE user_id = ?');
        $statement->execute([$userId]);
        $usage = $statement->fetchColumn();

        return $usage === false ? 0 : (int) $usage;
    }

    public static function increment(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (user_id, `usage`) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE `usage` = `usage` + 1'
        );
        $statement->execute([$userId]);
    }

    public static function allows(Nutgram $bot, int $userId): bool
    {
        if (self::count($userId) < self::FREE_LIMIT || self::channel() === '' || self::hasJoined($bot, $userId)) {
            return true;
        }

        self::askToJoin($bot);

        return false;
    }

    public static function remindAfterLimit(Nutgram $bot, int $userId): void
    {
        if (self::count($userId) < self::FREE_LIMIT || self::channel() === '' || self::hasJoined($bot, $userId)) {
            return;
        }

        self::askToJoin($bot);
    }

    public static function hasJoined(Nutgram $bot, int $userId): bool
    {
        $channel = self::channel();

        if ($channel === '') {
            return true;
        }

        try {
            $member = $bot->getChatMember('@'.$channel, $userId);
        } catch (\Throwable) {
            return false;
        }

        if ($member === null) {
            return false;
        }

        if ($member instanceof ChatMemberRestricted) {
            return $member->is_member;
        }

        $status = $member->status instanceof ChatMemberStatus
            ? $member->status
            : ChatMemberStatus::tryFrom((string) $member->status);

        return in_array($status, [
            ChatMemberStatus::CREATOR,
            ChatMemberStatus::ADMINISTRATOR,
            ChatMemberStatus::MEMBER,
        ], true);
    }

    private static function askToJoin(Nutgram $bot): void
    {
        $channel = self::channel();

        $bot->sendMessage(
            "You have used 10 edits. Join the channel to continue.\nhttps://t.me/{$channel}",
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('Join channel', url: 'https://t.me/'.$channel),
            ),
        );
    }

    private static function channel(): string
    {
        return ltrim(trim((string) ($_ENV['CHANNEL'] ?? '')), '@');
    }
}
