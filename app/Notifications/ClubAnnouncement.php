<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\EmailHtml;
use Illuminate\Support\Facades\URL;

/** A club email written by the MT on the Email members page. */
class ClubAnnouncement extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->announcement;
        $fill = fn (string $text) => strtr($text, ['{name}' => $notifiable->firstName(), '{matric}' => $notifiable->matric_no]);
        $fillHtml = fn (string $html) => strtr($html, ['{name}' => e($notifiable->firstName()), '{matric}' => e($notifiable->matric_no)]);
        $program = $a->program_id ? $a->program : null;

        return (new MailMessage)
            ->subject($fill($a->subject))
            ->markdown('mail.announcement', [
                'body' => $fillHtml(EmailHtml::clean($a->body)),
                'buttonLabel' => $a->button_label,
                'buttonUrl' => $a->button_url,
                'program' => $program && ! $program->trashed() ? $program : null,
                'unsubscribeUrl' => URL::signedRoute('email.unsubscribe', ['user' => $notifiable->getKey()]),
            ]);
    }
}
