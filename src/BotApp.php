<?php

declare(strict_types=1);

namespace ImageBot;

use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;

final class BotApp
{
    public static function make(): Nutgram
    {
        self::loadEnv(dirname(__DIR__).'/.env');

        $token = $_ENV['BOT_TOKEN'] ?? getenv('BOT_TOKEN') ?: '';

        if ($token === '') {
            throw new \RuntimeException('Set BOT_TOKEN in .env');
        }

        $cacheDirectory = dirname(__DIR__).'/storage/cache';
        $bot = new Nutgram($token, new Configuration(
            cache: new FileCache($cacheDirectory),
            clientTimeout: 60,
        ));

        $bot->onCommand('start', function (Nutgram $bot): void {
            $bot->sendMessage(
                "Send the image as a file to keep its quality. A photo is already compressed by Telegram.\n".
                "Supported: JPEG, PNG, GIF, WebP, BMP.\n".
                "Then pick an edit. I send the result as a file, so it stays full quality.\n".
                'Edits stack. Reset original goes back to the file you sent.'
            );
        });

        $bot->onPhoto(Pipeline::storeIncoming(...));
        $bot->onDocument(Pipeline::storeIncoming(...));

        foreach (['home', 'size', 'crop', 'fx', 'color', 'turn', 'text'] as $menu) {
            $bot->onCallbackQueryData('m:'.$menu, function (Nutgram $bot) use ($menu): void {
                Pipeline::showMenu($bot, $menu);
            });
        }

        foreach ([
            'half', 'double', 'trim', 'square', 'gray', 'invert', 'blur', 'sharp', 'pixel', 'colors16',
            'brighter', 'darker', 'contrastup', 'contrastdown', 'warm', 'cool',
            'fliph', 'flipv', 'rot90', 'rot180', 'rot270', 'orient', 'reset',
        ] as $operation) {
            $bot->onCallbackQueryData('a:'.$operation, function (Nutgram $bot) use ($operation): void {
                $bot->answerCallbackQuery();

                try {
                    Pipeline::edit($bot, $operation);
                } catch (\Throwable $exception) {
                    $bot->sendMessage($exception->getMessage());
                }
            });
        }

        foreach (['resize', 'scale', 'cover', 'contain', 'crop', 'bright', 'contrast', 'gamma', 'colorize', 'angle', 'text'] as $operation) {
            $bot->onCallbackQueryData('q:'.$operation, function (Nutgram $bot) use ($operation): void {
                if (!is_string($bot->getUserData('current'))) {
                    $bot->answerCallbackQuery(text: 'Send an image first', show_alert: true);

                    return;
                }

                $bot->answerCallbackQuery();
                EditConversation::begin($bot, data: [$operation]);
            });
        }

        return $bot;
    }

    private static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value, " \t\"'");
            $_ENV[trim($key)] = $value;
            putenv(trim($key).'='.$value);
        }
    }
}
