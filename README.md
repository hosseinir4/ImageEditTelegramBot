# ImageBot

Telegram bot for editing images. Users send an image file, pick an edit from an inline keyboard, and get the result back as a full-quality file.

Built with [Nutgram](https://github.com/nutgram/nutgram) and [Intervention Image](https://github.com/intervention/image) (GD).

Supported input: JPEG, PNG, GIF, WebP, BMP, up to 20 MB.

## Setup

```bash
composer install
cp .env.example .env
```

Put the bot token in `.env`:

```
BOT_TOKEN=123456:abc
```

PHP needs the `gd`, `curl`, `mbstring`, and `fileinfo` extensions.

## Run

Use one mode at a time.

**Webhook.** Point Telegram at `get.php`:

```bash
curl "https://api.telegram.org/bot<token>/setWebhook?url=https://your-host/ImageBot/get.php"
```

**Polling.** Leave the webhook unset and run:

```bash
php poll.php
```

`poll.php` does nothing unless you start it. A webhook only calls `get.php`.

## Use

1. Send `/start`, then a photo or an image file.
2. Pick a menu: Size, Crop, Effects, Colors, Flip & rotate, or Text.
3. Buttons that need a size or text ask you to send the values. Example: `800 600`. Send `/cancel` to stop.
4. The bot sends the edited image as a PNG file. Telegram compresses photos, so the result is a file.

Edits stack on the latest result. **Reset original** goes back to the file you sent.
