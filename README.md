# Event Ticket Booking API

A backend for selling event tickets online, built with Laravel.

There are two kinds of users:

- **Organizers** create events (a concert, a meetup, a workshop) and decide what tickets to sell, for example 100 "General" tickets at ₹200 and 10 "VIP" tickets at ₹500.
- **Attendees** browse upcoming events, book tickets and cancel them if their plans change.

It is an API only, with no screens. A website or mobile app would talk to it, and it can be tried out with Postman (see [docs/SETUP.md](docs/SETUP.md)).

## What it does

**Accounts**
- Anyone can sign up as an organizer or an attendee, log in and log out.

**For organizers**
- Create, edit and delete their own events, each with one or more ticket types.
- Keep an event as a draft until it is ready, then publish it.
- They can't touch another organizer's events.
- An event can't be deleted once people have bought tickets for it.
- They get an email the moment a ticket type sells out.

**For attendees**
- See all published, upcoming events, search by name or date, 10 per page, with how many seats are left.
- Book up to 5 tickets per event.
- Get a confirmation email after booking.
- Cancel up to 24 hours before the event. The seats go back on sale straight away.
- Get a reminder email the day before the event.

## The rules it protects

| Rule | What happens |
|---|---|
| Never sell more tickets than exist | Even if many people click "book" on the last seat at the same moment, only the right number of bookings go through. The rest get a clear "no seats left" message. |
| Price is decided by the system | The total is always calculated on the server, so nobody can send a fake lower price. |
| Max 5 tickets per person per event | Counted across all their bookings, so it can't be bypassed by booking several times. |
| No booking for past or draft events | Only live, upcoming events can be booked. |
| Emails never go out for a failed booking | If saving a booking fails, no confirmation or sold-out email is sent. |
| One reminder per booking | Even if the reminder process runs twice, nobody gets the same reminder twice. |

## Behind the scenes

Emails and sales reports run in the background, so booking stays fast. The reminder check runs automatically every hour and can handle tens of thousands of bookings.

Every rule above is covered by automated tests. It was also checked by hand against a real MySQL database, including 8 people trying to buy the last 2 seats at the same moment: exactly 2 succeeded.

## Tech

Laravel 13, Laravel Sanctum (login tokens), MySQL, database queues, scheduled commands, PHPUnit tests.

## Running it

See **[docs/SETUP.md](docs/SETUP.md)** for step-by-step setup, running and testing with Postman.
