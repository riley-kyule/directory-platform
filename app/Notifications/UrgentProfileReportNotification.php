<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UrgentProfileReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reportPublicId,
        public readonly string $profileName,
        public readonly string $category,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Urgent profile report requires review')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("An urgent {$this->category} report was submitted for {$this->profileName}.")
            ->line('Reference: '.$this->reportPublicId)
            ->action('Review urgent report', route('staff.moderation.show', $this->reportPublicId))
            ->line('Review and acknowledge this report as soon as possible.');
    }
}
