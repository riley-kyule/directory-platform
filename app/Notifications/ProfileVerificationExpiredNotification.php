<?php

namespace App\Notifications;

use App\Models\Profile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileVerificationExpiredNotification extends Notification implements ShouldQueue
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
            ->subject('Profile verification requires renewal')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->displayName} has been made private because one or more required verification checks are no longer current.");

        $profile = Profile::query()->find($this->profileId);
        if ($profile) {
            $message->action('View profile status', route('provider.profiles.show', $profile));
        }

        return $message->line('Contact support to complete verification before reactivation.');
    }
}
