<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification
{
    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: '.$this->booking->event->title.' starts soon')
            ->line($this->booking->event->title.' starts at '.$this->booking->event->starts_at->toDayDateTimeString().'.')
            ->line('Venue: '.$this->booking->event->venue);
    }
}
