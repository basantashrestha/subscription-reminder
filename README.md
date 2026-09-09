# Subscription Reminder

A 12-month calendar app for tracking recurring bills and taxes (bike tax, car
tax, electricity, etc). Click any date to open a side panel where you can add
multiple reminder titles at once, then edit or delete them later.

## Stack
- **Frontend:** plain HTML/CSS/JS, served by Nginx (proxies `/api/` to the backend)
- **Backend:** PHP 8.2 (Apache), REST-style JSON API
- **Database:** PostgreSQL 16

## Project layout
```
subscription-reminder/
├── docker-compose.yml
├── db/
│   └── init.sql              # schema, auto-run on first DB start
├── backend/
│   ├── Dockerfile
│   └── src/
│       ├── db.php
│       ├── api.php           # all CRUD endpoints
│       └── index.php
└── frontend/
    ├── Dockerfile
    ├── nginx.conf
    └── public/
        ├── index.html
        ├── style.css
        └── app.js
```

## Running it

```bash
docker compose up --build
```

Then open **http://localhost:8080** in your browser.

- Frontend: http://localhost:8080
- Backend (direct, for debugging): http://localhost:8080/api/api.php?action=year&year=2026
- Postgres: exposed on localhost:5432 (user `subrem_user` / password `subrem_pass`, db `subscription_reminder`)

Data persists in a named Docker volume (`db_data`), so it survives
`docker compose down` (use `docker compose down -v` if you want to wipe it).

## API summary

| Method | Endpoint                                   | Purpose                                   |
|--------|---------------------------------------------|--------------------------------------------|
| GET    | `/api/api.php?action=year&year=YYYY`        | date → count map, for calendar dots        |
| GET    | `/api/api.php?action=month&year=&month=`    | date → count map, single month             |
| GET    | `/api/api.php?action=day&date=YYYY-MM-DD`   | list of reminders for that date            |
| POST   | `/api/api.php?action=save`                  | body `{date, items:[{id?, title}]}` — bulk save (insert new / update items that carry an id) |
| PUT    | `/api/api.php?action=update`                | body `{id, title}` — edit a single reminder |
| DELETE | `/api/api.php?action=delete`                | body `{id}` — delete a single reminder      |

## Notes / things you may want to change before production
- Change the Postgres password and don't commit real credentials.
- The API currently allows `Access-Control-Allow-Origin: *`; tighten this if you expose the backend publicly on its own.
- There's no authentication/multi-user support — every visitor sees the same shared calendar. Add a `users` table + login if you need per-user data.
- Consider adding email/browser push notifications for upcoming due dates — the schema has room to grow (e.g. a `remind_days_before` column).
