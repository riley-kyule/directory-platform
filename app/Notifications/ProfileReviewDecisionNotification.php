<?php

namespace App\Notifications;

use App\Models\Profile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileReviewDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $profileId,
        public readonly string $displayName,
        public readonly string $decision,
        public readonly string $reason,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === 'approved';
        $message = (new MailMessage)
            ->subject($approved ? 'Your profile has been approved' : 'Your profile needs changes')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($approved
                ? "{$this->displayName} has been approved and activated."
                : "{$this->displayName} was not approved during this review.")
            ->line('Staff reason: '.$this->reason);

        $profile = Profile::query()->find($this->profileId);
        if ($profile) {
            $message->action('View profile status', route('provider.profiles.show', $profile));
        }

        return $message->line('Sign in to review the current status and any next steps.');
    }
}
