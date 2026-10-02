<?php

declare(strict_types=1);

namespace ImageBot;

use Intervention\Image\Alignment;
use Intervention\Image\Direction;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Fraction;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

final class Editor
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class);
    }

    /**
     * @param  array<int, string>  $args
     */
    public function apply(string $source, string $destination, string $operation, array $args = []): void
    {
        $image = $this->manager->decodePath($source);
        $this->modify($image, $operation, $args);

        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
        $encoded = $extension === 'jpg' || $extension === 'jpeg'
            ? $image->encodeUsingFormat(Format::JPEG, quality: 90)
            : $image->encodeUsingFileExtension($extension === '' ? 'png' : $extension);

        $encoded->save($destination);
    }

    public function jpegCopy(string $source, string $destination): void
    {
        $this->manager->decodePath($source)
            ->encodeUsingFormat(Format::JPEG, quality: 90)
            ->save($destination);
    }

    /**
     * @param  array<int, string>  $args
     */
    private function modify(ImageInterface $image, string $operation, array $args): void
    {
        switch ($operation) {
            case 'resize':
                $image->resize($this->intAt($args, 0), $this->intAt($args, 1));
                break;
            case 'scale':
                $image->scale(
                    $this->intAt($args, 0),
                    isset($args[1]) ? $this->intAt($args, 1) : null,
                );
                break;
            case 'cover':
                $image->cover($this->intAt($args, 0), $this->intAt($args, 1), Alignment::CENTER);
                break;
            case 'contain':
                $image->contain($this->intAt($args, 0), $this->intAt($args, 1), 'ffffff', Alignment::CENTER);
                break;
            case 'crop':
                $width = $this->intAt($args, 0);
                $height = $this->intAt($args, 1);
                $x = isset($args[2]) ? $this->intAt($args, 2) : (int) (($image->width() - $width) / 2);
                $y = isset($args[3]) ? $this->intAt($args, 3) : (int) (($image->height() - $height) / 2);
                $image->crop($width, $height, max(0, $x), max(0, $y));
                break;
            case 'half':
                $image->scale(Fraction::HALF);
                break;
            case 'double':
                $image->scale(Fraction::DOUBLE);
                break;
            case 'trim':
                $image->trim(8);
                break;
            case 'square':
                $size = min($image->width(), $image->height());
                $image->crop(
                    $size,
                    $size,
                    (int) (($image->width() - $size) / 2),
                    (int) (($image->height() - $size) / 2),
                );
                break;
            case 'gray':
                $image->grayscale();
                break;
            case 'invert':
                $image->invert();
                break;
            case 'blur':
                $image->blur(8);
                break;
            case 'sharp':
                $image->sharpen(15);
                break;
            case 'pixel':
                $image->pixelate(12);
                break;
            case 'colors16':
                $image->reduceColors(16);
                break;
            case 'brighter':
                $image->brightness(25);
                break;
            case 'darker':
                $image->brightness(-25);
                break;
            case 'contrastup':
                $image->contrast(25);
                break;
            case 'contrastdown':
                $image->contrast(-25);
                break;
            case 'warm':
                $image->colorize(red: 30, green: 8);
                break;
            case 'cool':
                $image->colorize(green: 8, blue: 30);
                break;
            case 'bright':
                $image->brightness($this->rangeAt($args, 0, -100, 100));
                break;
            case 'contrast':
                $image->contrast($this->rangeAt($args, 0, -100, 100));
                break;
            case 'gamma':
                $gamma = (float) ($args[0] ?? '');
                if ($gamma <= 0) {
                    throw new RuntimeException('Gamma must be greater than 0. Example: 1.6');
                }
                $image->gamma($gamma);
                break;
            case 'colorize':
                $image->colorize(
                    $this->rangeAt($args, 0, -100, 100),
                    $this->rangeAt($args, 1, -100, 100),
                    $this->rangeAt($args, 2, -100, 100),
                );
                break;
            case 'fliph':
                $image->flip(Direction::HORIZONTAL);
                break;
            case 'flipv':
                $image->flip(Direction::VERTICAL);
                break;
            case 'rot90':
                $image->rotate(90);
                break;
            case 'rot180':
                $image->rotate(180);
                break;
            case 'rot270':
                $image->rotate(270);
                break;
            case 'angle':
                $image->rotate((float) $this->required($args, 0, 'Send degrees. Example: 45'));
                break;
            case 'orient':
                $image->orient();
                break;
            case 'text':
                $this->drawText($image, trim(implode(' ', $args)));
                break;
            default:
                throw new RuntimeException('Unknown edit.');
        }
    }

    private function drawText(ImageInterface $image, string $text): void
    {
        if ($text === '') {
            throw new RuntimeException('Send the text you want on the image.');
        }

        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

        if (!is_file($font)) {
            throw new RuntimeException('No font file is available for text.');
        }

        $size = max(18, (int) ($image->width() / 18));
        $image->text($text, (int) ($image->width() / 2), $image->height() - 24, function ($draw) use ($font, $size): void {
            $draw->filename($font);
            $draw->size($size);
            $draw->color('ffffff');
            $draw->stroke('000000', 2);
            $draw->align(Alignment::CENTER, Alignment::BOTTOM);
        });
    }

    /**
     * @param  array<int, string>  $args
     */
    private function intAt(array $args, int $index): int
    {
        $value = $this->required($args, $index, 'Send positive numbers. Example: 800 600');
        $number = (int) $value;

        if ($number < 1 || $number > 8000) {
            throw new RuntimeException('Each size must be between 1 and 8000.');
        }

        return $number;
    }

    /**
     * @param  array<int, string>  $args
     */
    private function rangeAt(array $args, int $index, int $min, int $max): int
    {
        $number = (int) $this->required($args, $index, 'Send a number from '.$min.' to '.$max.'.');

        if ($number < $min || $number > $max) {
            throw new RuntimeException('Use a number from '.$min.' to '.$max.'.');
        }

        return $number;
    }

    /**
     * @param  array<int, string>  $args
     */
    private function required(array $args, int $index, string $hint): string
    {
        $value = trim($args[$index] ?? '');

        if ($value === '') {
            throw new RuntimeException($hint);
        }

        return $value;
    }
}
