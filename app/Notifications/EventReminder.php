<?php

namespace App\Notifications;

use App\Models\Program;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "See you tomorrow" email to a member registered for an event. */
class EventReminder extends Notification
{
    use Queueable;

    public function __construct(public Program $program) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->program;
        $start = $p->starts_at;
        $when = match (true) {
            $start->isToday() => 'today',
            $start->isTomorrow() => 'tomorrow',
            default => $start->format('l'),
        };

        return (new MailMessage)
            ->subject("Reminder: {$p->title} is {$when}, ".$start->format('g:i A'))
            ->markdown('mail.event-reminder', [
                'name' => $notifiable->firstName(),
                'program' => $p,
                'when' => $when,
                'eventUrl' => route('programs.show', $p),
                'calendarUrl' => $p->googleCalendarUrl(),
            ]);
    }
}
