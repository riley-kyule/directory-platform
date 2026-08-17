<?php

namespace App\Notifications;

use App\Models\Profile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfilePackageExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $profileId, public readonly string $displayName)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your profile package has expired')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->displayName} is now private because its package period has ended.");

        $profile = Profile::query()->find($this->profileId);
        if ($profile) {
            $message->action('Request renewal', route('provider.profiles.show', $profile));
        }

        return $message->line('Renew the listing to make it eligible for publication again.');
    }
}
