<?php

declare(strict_types=1);

namespace ImageBot;

use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

final class EditConversation extends Conversation
{
    public string $operation = '';

    public function start(Nutgram $bot, string $operation): void
    {
        $this->operation = $operation;
        $bot->sendMessage(Keyboards::prompt($operation)."\n/cancel to stop.");
        $this->next('receive');
    }

    public function receive(Nutgram $bot): void
    {
        if ($bot->callbackQuery() !== null) {
            $bot->answerCallbackQuery(text: 'Send the values as a message, or /cancel', show_alert: true);

            return;
        }

        if ($bot->message()?->photo !== null || $bot->message()?->document !== null) {
            $this->end();
            Pipeline::storeIncoming($bot);

            return;
        }

        $text = trim((string) ($bot->message()?->text ?? ''));

        if ($text === '/cancel') {
            $this->end();
            $bot->sendMessage('Cancelled.', reply_markup: Keyboards::for('home'));

            return;
        }

        if ($text === '') {
            $bot->sendMessage(Keyboards::prompt($this->operation));

            return;
        }

        $parts = preg_split('/\s+/', $text);
        $args = $this->operation === 'text' ? [$text] : ($parts === false ? [] : $parts);

        try {
            Pipeline::edit($bot, $this->operation, $args);
        } catch (\Throwable $exception) {
            $bot->sendMessage($exception->getMessage());

            return;
        }

        $this->end();
    }
}
