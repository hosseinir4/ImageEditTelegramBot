<?php

declare(strict_types=1);

namespace ImageBot;

use RuntimeException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatAction;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

final class Pipeline
{
    private const MAX_BYTES = 20971520;

    public static function storeIncoming(Nutgram $bot): void
    {
        $fileId = self::incomingFileId($bot);
        $extension = self::incomingExtension($bot);

        if ($fileId === null || $extension === null) {
            $bot->sendMessage('Send a photo or an image file: JPEG, PNG, GIF, WebP, or BMP.');

            return;
        }

        $remote = $bot->getFile($fileId);

        if ($remote === null) {
            $bot->sendMessage('Telegram did not return that file.');

            return;
        }

        if (($remote->file_size ?? 0) > self::MAX_BYTES) {
            $bot->sendMessage('That file is larger than 20 MB.');

            return;
        }

        $directory = self::imageDirectory();
        $original = $directory.'/'.$bot->userId().'-original.'.$extension;
        $downloaded = $bot->downloadFile($remote, $original);

        if ($downloaded !== true || !is_file($original)) {
            $bot->sendMessage('Could not download the image.');

            return;
        }

        self::deleteStored($bot, 'current');
        $bot->setUserData('original', $original);
        $bot->setUserData('current', $original);
        $bot->sendMessage(Keyboards::text('home'), reply_markup: Keyboards::for('home'));
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function edit(Nutgram $bot, string $operation, array $args = []): void
    {
        $userId = $bot->userId();

        if ($userId === null) {
            throw new RuntimeException('Send an image first.');
        }

        if (!Usage::allows($bot, $userId)) {
            return;
        }

        $bot->sendChatAction(ChatAction::UPLOAD_DOCUMENT);

        if ($operation === 'reset') {
            $original = $bot->getUserData('original');

            if (!is_string($original) || !is_file($original)) {
                throw new RuntimeException('Send an image first.');
            }

            self::deleteStored($bot, 'current');
            $bot->setUserData('current', $original);
            self::deliver($bot, $original, 'Original image');

            return;
        }

        $current = $bot->getUserData('current');

        if (!is_string($current) || !is_file($current)) {
            throw new RuntimeException('Send an image first.');
        }

        $destination = self::imageDirectory().'/'.$bot->userId().'-'.time().'.png';
        (new Editor())->apply($current, $destination, $operation, $args);

        $original = $bot->getUserData('original');
        $previous = $current;

        if (is_string($previous) && $previous !== $original && is_file($previous)) {
            unlink($previous);
        }

        $bot->setUserData('current', $destination);
        self::deliver($bot, $destination, self::label($operation));
        Usage::increment($userId);
        Usage::remindAfterLimit($bot, $userId);
    }

    public static function showMenu(Nutgram $bot, string $menu): void
    {
        $bot->answerCallbackQuery();
        $bot->editMessageText(
            text: Keyboards::text($menu),
            chat_id: $bot->chatId(),
            message_id: $bot->callbackQuery()?->message?->message_id,
            reply_markup: Keyboards::for($menu),
        );
    }

    private static function deliver(Nutgram $bot, string $path, string $label): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '' || $extension === 'jpeg') {
            $extension = $extension === 'jpeg' ? 'jpg' : 'png';
        }

        $bot->sendDocument(
            document: InputFile::make($path, 'edited.'.$extension),
            caption: $label.'. Full quality file.',
        );

        $bot->sendMessage('Edit again, or send a new image.', reply_markup: Keyboards::for('home'));
    }

    private static function incomingFileId(Nutgram $bot): ?string
    {
        $message = $bot->message();

        if (!$message instanceof Message) {
            return null;
        }

        if ($message->photo !== null && $message->photo !== []) {
            return $message->photo[array_key_last($message->photo)]->file_id;
        }

        $document = $message->document;

        if ($document !== null && self::isImageMime((string) $document->mime_type, (string) $document->file_name)) {
            return $document->file_id;
        }

        return null;
    }

    private static function incomingExtension(Nutgram $bot): ?string
    {
        $message = $bot->message();
        $name = strtolower((string) ($message?->document?->file_name ?? ''));
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        $mime = (string) ($message?->document?->mime_type ?? '');

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
            default => $message?->photo !== null ? 'jpg' : null,
        };
    }

    private static function isImageMime(string $mime, string $name): bool
    {
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    private static function deleteStored(Nutgram $bot, string $key): void
    {
        $path = $bot->getUserData($key);
        $original = $bot->getUserData('original');

        if (is_string($path) && $path !== $original && is_file($path)) {
            unlink($path);
        }
    }

    private static function imageDirectory(): string
    {
        $directory = dirname(__DIR__).'/storage/images';

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    private static function label(string $operation): string
    {
        return match ($operation) {
            'resize' => 'Resized',
            'scale' => 'Scaled',
            'cover' => 'Covered',
            'contain' => 'Contained',
            'crop' => 'Cropped',
            'half' => 'Scaled to half',
            'double' => 'Scaled to double',
            'trim' => 'Trimmed',
            'square' => 'Center square',
            'gray' => 'Grayscale',
            'invert' => 'Inverted',
            'blur' => 'Blurred',
            'sharp' => 'Sharpened',
            'pixel' => 'Pixelated',
            'colors16' => 'Reduced to 16 colors',
            'brighter', 'bright' => 'Brightness',
            'darker' => 'Darker',
            'contrastup', 'contrast' => 'Contrast',
            'contrastdown' => 'Less contrast',
            'warm', 'cool', 'colorize' => 'Colorized',
            'gamma' => 'Gamma',
            'fliph' => 'Flipped horizontally',
            'flipv' => 'Flipped vertically',
            'rot90', 'rot180', 'rot270', 'angle' => 'Rotated',
            'orient' => 'Oriented from EXIF',
            'text' => 'Text added',
            default => 'Edited',
        };
    }
}
