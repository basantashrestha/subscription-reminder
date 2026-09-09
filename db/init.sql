-- Subscription Reminder schema

CREATE TABLE IF NOT EXISTS reminders (
    id              SERIAL PRIMARY KEY,
    reminder_date   DATE NOT NULL,
    title           VARCHAR(255) NOT NULL,
    notes           VARCHAR(500),
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_reminders_date ON reminders (reminder_date);
