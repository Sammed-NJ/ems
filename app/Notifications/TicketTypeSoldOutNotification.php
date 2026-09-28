<?php

namespace App\Notifications;

use App\Models\TicketType;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketTypeSoldOutNotification extends Notification
{
    public function __construct(public TicketType $ticketType) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sold out: '.$this->ticketType->name.' for '.$this->ticketType->event->title)
            ->line("All {$this->ticketType->quantity} {$this->ticketType->name} tickets have been sold.");
    }
}
