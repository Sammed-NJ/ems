# Technical Guide: Setup, Run and Test

## 1. Requirements

| Tool | Version |
|---|---|
| PHP | 8.3 or newer (extensions: pdo_mysql, mbstring, openssl) |
| Composer | 2.x |
| MySQL | 8.x or MariaDB 10.4+ (XAMPP works) |
| Git | any |
| Postman | any (for manual testing) |

## 2. Get the code

```bash
git clone https://github.com/Sammed-NJ/ems.git
cd ems
composer install
```

## 3. Configure

```bash
cp .env.example .env          # Windows PowerShell: copy .env.example .env
php artisan key:generate
```

Create an empty database named `event_booking` (phpMyAdmin or `CREATE DATABASE event_booking;`), then check these values in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=event_booking
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
MAIL_MAILER=log
```

## 4. Create tables and sample data

```bash
php artisan migrate --seed
```

To reset everything back to the sample data at any time:

```bash
php artisan migrate:fresh --seed
```

**Seeded users** (password for all: `password`)

| Email | Role |
|---|---|
| organizer@test.com | organizer |
| attendee1@test.com | attendee |
| attendee2@test.com | attendee |

**Seeded events**

| Event | Status | Starts | ticket_type_id |
|---|---|---|---|
| Tech Meetup | published | in 20 hours | 1 = General (100 seats), 2 = VIP (**2 seats**) |
| Music Night | published | in 15 days | 3 = General (200), 4 = VIP (20) |
| Food Fest | draft | in 30 days | 5 = General (50) |

## 5. Run

Open three terminals in the project folder:

```bash
# 1. API server -> http://127.0.0.1:8000
php artisan serve

# 2. Queue worker: sends emails and updates sales stats
php artisan queue:work --queue=emails,reports

# 3. Scheduler: runs the reminder command every hour
php artisan schedule:work
```

Emails use the `log` driver. Open `storage/logs/laravel.log` to read them.

## 6. Test with Postman

### Import

Postman → **Import** → select `postman/EMS.postman_collection.json`.

The collection:
- has a `base_url` variable (`http://127.0.0.1:8000/api`)
- sends `Accept: application/json` on every request
- saves the token automatically when you run a login request
- uses the variables `event_id`, `ticket_type_id` and `booking_id`: click the collection → **Variables** to change them

### Walkthrough

Start from fresh data (`php artisan migrate:fresh --seed`) and keep the queue worker running.

| # | Request | Change | Expected |
|---|---|---|---|
| 1 | Public → List events | none | 200, Tech Meetup + Music Night, no Food Fest (draft) |
| 2 | List events with `search=music` | query param | only Music Night |
| 3 | Attendee bookings → Book tickets (not logged in) | none | 401 |
| 4 | Auth → Login as attendee | none | 200, token saved |
| 5 | Book tickets | `ticket_type_id` = 3, quantity 2 | 201, `total_amount` = 1600.00, confirmation email in log |
| 6 | Book tickets | quantity 4 | 422, max 5 tickets per event |
| 7 | Book tickets | `ticket_type_id` = 5 | 422, event not open (draft) |
| 8 | Book tickets | `ticket_type_id` = 2, quantity 2 | 201, organizer gets "Sold out" email |
| 9 | Book tickets | `ticket_type_id` = 2, quantity 1 | 422, no seats left |
| 10 | Cancel booking | `booking_id` = 1 | 200, status cancelled, seats back |
| 11 | Cancel booking | `booking_id` = 2 (Tech Meetup) | 422, less than 24 hours to event |
| 12 | Auth → Login as organizer | none | token switches to organizer |
| 13 | Organizer events → Create event | none | 201 with two ticket types |
| 14 | Delete event | `event_id` = 1 (Tech Meetup) | 422, has confirmed bookings |
| 15 | Book tickets (as organizer) | none | 403, only attendees can book |

### Reminders

```bash
php artisan bookings:send-reminders
```

This prints `Queued N reminders.` for confirmed bookings of events starting within 24 hours. Run it again before the worker picks the jobs up: the log still gets only one reminder per booking.

## 7. Automated tests

```bash
php artisan test
```

This runs 19 feature tests on an in-memory SQLite database, so your MySQL data is not touched. They cover auth, ownership, listing filters, every booking rule, cancellation, events firing only after commit, the sold-out email and reminder idempotency.

## 8. API reference

All endpoints are prefixed with `/api`. Send `Accept: application/json`, plus `Authorization: Bearer <token>` where auth is needed.

| Method | Endpoint | Auth | Body / query |
|---|---|---|---|
| POST | /register | – | name, email, password, password_confirmation, role (organizer/attendee) |
| POST | /login | – | email, password |
| POST | /logout | any | – |
| GET | /events | – | ?search=&from=YYYY-MM-DD&to=YYYY-MM-DD&page= |
| GET | /organizer/events | organizer | – |
| POST | /organizer/events | organizer | title, description, venue, starts_at, status, ticket_types[] {name, price, quantity} |
| GET | /organizer/events/{id} | owner | – |
| PUT | /organizer/events/{id} | owner | any of: title, description, venue, starts_at, status |
| DELETE | /organizer/events/{id} | owner | – |
| GET | /bookings | attendee | – |
| POST | /bookings | attendee | ticket_type_id, quantity |
| POST | /bookings/{id}/cancel | owner | – |

**Error responses**

| Code | When | Body |
|---|---|---|
| 401 | missing or invalid token | `{"message": "Unauthenticated."}` |
| 403 | wrong role or not the owner | `{"message": "..."}` |
| 404 | unknown id or route | `{"message": "Resource not found."}` |
| 422 | validation or business rule | `{"message": "...", "errors": {"field": ["..."]}}` |

## 9. How it works

```
app/
├── Http/Controllers/      thin controllers: validate, authorize, call service, return resource
├── Http/Requests/         Form Request validation
├── Http/Resources/        JSON response shapes
├── Http/Middleware/       EnsureUserHasRole (role:organizer / role:attendee)
├── Policies/              EventPolicy, BookingPolicy (ownership -> 403)
├── Services/              BookingService: booking and cancellation rules
├── Events/                BookingConfirmed, BookingCancelled
├── Listeners/             queued email + stats listeners
├── Jobs/                  SendEventReminder
├── Console/Commands/      bookings:send-reminders
└── Notifications/         the emails
```

**Booking flow (`BookingService::book`)**

1. Open a DB transaction.
2. `SELECT ... FOR UPDATE` on the event and the ticket type. Parallel bookings for the same event now wait in line.
3. Check the event is published and upcoming, there are enough seats, and the user's total stays at 5 or fewer.
4. Increment `ticket_types.sold` and create the booking with a server-calculated total.
5. Dispatch `BookingConfirmed`. It implements `ShouldDispatchAfterCommit`, so listeners are queued only after a successful commit.

**Listeners**

| Event | Listener | Queue |
|---|---|---|
| BookingConfirmed | SendBookingConfirmation | emails |
| BookingConfirmed | NotifyOrganizerWhenSoldOut (only when seats hit 0) | emails |
| BookingConfirmed | UpdateEventSalesStats → tickets_sold, revenue | reports |
| BookingCancelled | SendBookingCancellation | emails |
| BookingCancelled | UpdateEventSalesStats (subtracts) | reports |

All queued listeners and jobs use `tries = 3`, `backoff = [10, 30, 60]`, a `failed()` method that logs, and `deleteWhenMissingModels`.

**Reminders**

- The `bookings:send-reminders` command runs hourly (`routes/console.php`, `withoutOverlapping`).
- It uses `chunkById(500)`, so memory stays flat for large tables.
- `SendEventReminder` receives only the booking id. It "claims" the booking with `UPDATE ... SET reminder_sent_at = NOW() WHERE reminder_sent_at IS NULL`, so only one run can win. A deleted or cancelled booking matches no row and the job exits quietly.

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [2002] ... actively refused` | MySQL is not running. Start it in XAMPP. |
| `Unknown database 'event_booking'` | Create the database first. |
| `php` not recognized | Add PHP to PATH, or restart the terminal/editor after installing. |
| No emails in the log | The queue worker isn't running. Start `php artisan queue:work --queue=emails,reports`. |
