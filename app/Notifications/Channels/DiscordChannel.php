<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class DiscordChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('discord', $notification);

        if (! $webhookUrl) {
            return;
        }

        /** @var array{content?: string, embeds?: array<int, mixed>} $message */
        $message = $notification->toDiscord($notifiable);

        Http::post($webhookUrl, $message);
    }
}
