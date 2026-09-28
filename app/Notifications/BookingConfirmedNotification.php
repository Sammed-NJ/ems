<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification
{
    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking confirmed: '.$this->booking->event->title)
            ->line("Your booking #{$this->booking->id} is confirmed.")
            ->line("{$this->booking->quantity} x {$this->booking->ticketType->name}, total {$this->booking->total_amount}.")
            ->line('Event starts at '.$this->booking->event->starts_at->toDayDateTimeString().'.');
    }
}
