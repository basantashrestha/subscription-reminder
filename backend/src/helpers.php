<?php

declare(strict_types=1);

/**
 * Pure, side-effect-free helpers used by api.php.
 * Kept in their own file (no DB/network calls) so they can be
 * unit tested directly, without needing Postgres or Apache running.
 */

/**
 * Clean up a reminder title: trim edges and collapse internal
 * runs of whitespace down to a single space.
 */
function normalize_title(string $title): string
{
    return trim(preg_replace('/\s+/', ' ', $title));
}

/**
 * True only for a real calendar date in strict Y-m-d form
 * (rejects things like "2026-13-40" or "15-09-2026").
 */
function is_valid_reminder_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

/**
 * Given a list of raw item payloads from the "save" endpoint,
 * return only the ones with a usable (non-empty, after trimming)
 * title, with that title normalized.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function filter_valid_reminder_items(array $items): array
{
    $valid = [];
    foreach ($items as $item) {
        $title = normalize_title((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $item['title'] = $title;
        $valid[] = $item;
    }
    return $valid;
}
