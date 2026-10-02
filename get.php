<?php

declare(strict_types=1);

use ImageBot\BotApp;
use SergiX44\Nutgram\RunningMode\Webhook;

require __DIR__.'/vendor/autoload.php';

$bot = BotApp::make();
$bot->setRunningMode(Webhook::class);
$bot->run();
