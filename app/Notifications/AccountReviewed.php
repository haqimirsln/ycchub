<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountReviewed extends Notification
{
    use Queueable;

    public function __construct(public bool $approved, public ?string $note = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('You\'re in! Welcome to YCC')
                ->markdown('mail.account-approved', [
                    'name' => $notifiable->firstName(),
                    'matric' => $notifiable->matric_no,
                    'portalUrl' => route('portal'),
                    'appUrl' => route('pwa.guide'),
                ]);
        }

        return (new MailMessage)
            ->subject('About your YCC Hub sign-up')
            ->markdown('mail.account-rejected', [
                'name' => $notifiable->firstName(),
                'note' => $this->note,
                'instagramUrl' => \App\Http\Controllers\PublicController::social()['instagram_url'],
            ]);
    }
}
