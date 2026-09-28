<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

// an organizer can only touch events they created
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
