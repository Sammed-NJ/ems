# Event Ticket Booking API

Laravel 13 + Sanctum + MySQL.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Queue worker and scheduler:

```bash
php artisan queue:work --queue=emails,reports
php artisan schedule:work
```

Mail driver is `log`, emails go to `storage/logs/laravel.log`.

Tests: `php artisan test`

## Seed users

Password for all: `password`

- organizer@test.com (organizer)
- attendee1@test.com (attendee)
- attendee2@test.com (attendee)

## Postman

Import `postman/EMS.postman_collection.json`. Login request saves the token automatically.

## Endpoints

| Method | URL | Access |
|---|---|---|
| POST | /api/register | guest |
| POST | /api/login | guest |
| POST | /api/logout | logged in |
| GET | /api/events | public |
| GET, POST | /api/organizer/events | organizer |
| GET, PUT, DELETE | /api/organizer/events/{id} | owner |
| GET, POST | /api/bookings | attendee |
| POST | /api/bookings/{id}/cancel | owner |

## Notes

- Booking locks the event and ticket type rows (`lockForUpdate`) inside a transaction, so tickets can't be oversold.
- `BookingConfirmed` / `BookingCancelled` use `ShouldDispatchAfterCommit`, listeners don't run if the transaction fails.
- Emails run on the `emails` queue, sales stats on the `reports` queue.
- Reminder job sets `reminder_sent_at` with a conditional update, so each booking gets only one reminder.
