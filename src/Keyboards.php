<?php

declare(strict_types=1);

namespace ImageBot;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class Keyboards
{
    public static function text(string $menu): string
    {
        return match ($menu) {
            'size' => 'Size: resize stretches, scale keeps the ratio, cover fills and crops, contain fits inside.',
            'crop' => 'Crop the current image.',
            'fx' => 'These effects run immediately.',
            'color' => 'Color changes. The custom buttons ask for a number.',
            'turn' => 'Flip, rotate, or fix phone orientation.',
            'text' => 'Draw text on the image.',
            default => 'Choose an edit. Each edit is applied on the latest result. Reset goes back to the image you sent.',
        };
    }

    public static function for(string $menu): InlineKeyboardMarkup
    {
        $rows = match ($menu) {
            'size' => [
                [self::ask('Resize', 'resize'), self::ask('Scale', 'scale')],
                [self::ask('Cover', 'cover'), self::ask('Contain', 'contain')],
                [self::apply('Half', 'half'), self::apply('Double', 'double')],
                [self::apply('Trim edges', 'trim')],
                [self::menu('Back', 'home')],
            ],
            'crop' => [
                [self::apply('Center square', 'square')],
                [self::ask('Custom crop', 'crop')],
                [self::menu('Back', 'home')],
            ],
            'fx' => [
                [self::apply('Grayscale', 'gray'), self::apply('Invert', 'invert')],
                [self::apply('Blur', 'blur'), self::apply('Sharpen', 'sharp')],
                [self::apply('Pixelate', 'pixel'), self::apply('16 colors', 'colors16')],
                [self::menu('Back', 'home')],
            ],
            'color' => [
                [self::apply('Brighter', 'brighter'), self::apply('Darker', 'darker')],
                [self::apply('More contrast', 'contrastup'), self::apply('Less contrast', 'contrastdown')],
                [self::apply('Warmer', 'warm'), self::apply('Cooler', 'cool')],
                [self::ask('Brightness', 'bright'), self::ask('Contrast', 'contrast')],
                [self::ask('Gamma', 'gamma'), self::ask('Colorize', 'colorize')],
                [self::menu('Back', 'home')],
            ],
            'turn' => [
                [self::apply('Flip horizontal', 'fliph'), self::apply('Flip vertical', 'flipv')],
                [self::apply('Rotate 90', 'rot90'), self::apply('Rotate 180', 'rot180')],
                [self::apply('Rotate 270', 'rot270'), self::ask('Custom angle', 'angle')],
                [self::apply('Fix EXIF', 'orient')],
                [self::menu('Back', 'home')],
            ],
            'text' => [
                [self::ask('Add text', 'text')],
                [self::menu('Back', 'home')],
            ],
            default => [
                [self::menu('Size', 'size'), self::menu('Crop', 'crop')],
                [self::menu('Effects', 'fx'), self::menu('Colors', 'color')],
                [self::menu('Flip & rotate', 'turn'), self::menu('Text', 'text')],
                [self::apply('Reset original', 'reset')],
            ],
        };

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($rows as $row) {
            $keyboard->addRow(...$row);
        }

        return $keyboard;
    }

    public static function prompt(string $operation): string
    {
        return match ($operation) {
            'resize' => 'Send width and height. Example: 800 600',
            'scale' => 'Send width, or width and height. Example: 800 or 800 600',
            'cover' => 'Send width and height to fill. Example: 800 600',
            'contain' => 'Send width and height to fit inside. Example: 800 600',
            'crop' => 'Send width and height, or width height x y. Example: 400 300 or 400 300 20 40',
            'bright' => 'Send brightness from -100 to 100. Example: 20',
            'contrast' => 'Send contrast from -100 to 100. Example: -15',
            'gamma' => 'Send gamma greater than 0. Example: 1.6',
            'colorize' => 'Send red green blue from -100 to 100. Example: 20 0 -10',
            'angle' => 'Send degrees. Example: 45',
            'text' => 'Send the text to draw on the image.',
            default => 'Send the values for this edit, or /cancel.',
        };
    }

    private static function menu(string $label, string $name): InlineKeyboardButton
    {
        return InlineKeyboardButton::make($label, callback_data: 'm:'.$name);
    }

    private static function apply(string $label, string $operation): InlineKeyboardButton
    {
        return InlineKeyboardButton::make($label, callback_data: 'a:'.$operation);
    }

    private static function ask(string $label, string $operation): InlineKeyboardButton
    {
        return InlineKeyboardButton::make($label, callback_data: 'q:'.$operation);
    }
}
