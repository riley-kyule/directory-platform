<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueModerationDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $urgentReports,
        public readonly int $normalReports,
        public readonly int $appeals,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Overdue moderation cases require action')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Moderation cases have exceeded their configured response targets.')
            ->line("Urgent reports: {$this->urgentReports}; normal reports: {$this->normalReports}; appeals: {$this->appeals}.")
            ->action('Open moderation queue', route('staff.moderation.index', ['sla' => 'overdue']))
            ->line('Assign, review, and resolve these cases as soon as possible.');
    }
}
