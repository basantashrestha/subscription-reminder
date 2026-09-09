<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');
// CORS (kept as a safety net in case frontend is ever served from a different origin)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    respond(['error' => 'Database connection failed', 'detail' => $e->getMessage()], 500);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // ---------------------------------------------------------------
    // GET ?action=month&year=YYYY&month=MM
    // Returns a map of date => count, used to render dots on the calendar
    // ---------------------------------------------------------------
    if ($method === 'GET' && $action === 'month') {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('n'));

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-d', strtotime("$start +1 month"));

        $stmt = $pdo->prepare(
            'SELECT reminder_date, COUNT(*) as cnt
             FROM reminders
             WHERE reminder_date >= :start AND reminder_date < :end
             GROUP BY reminder_date'
        );
        $stmt->execute(['start' => $start, 'end' => $end]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['reminder_date']] = (int) $row['cnt'];
        }

        respond($result);
    }

    // ---------------------------------------------------------------
    // GET ?action=year&year=YYYY
    // Returns a map of date => count for the WHOLE year in one call
    // ---------------------------------------------------------------
    if ($method === 'GET' && $action === 'year') {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-01-01', $year + 1);

        $stmt = $pdo->prepare(
            'SELECT reminder_date, COUNT(*) as cnt
             FROM reminders
             WHERE reminder_date >= :start AND reminder_date < :end
             GROUP BY reminder_date'
        );
        $stmt->execute(['start' => $start, 'end' => $end]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['reminder_date']] = (int) $row['cnt'];
        }

        respond($result);
    }

    // ---------------------------------------------------------------
    // GET ?action=day&date=YYYY-MM-DD
    // Returns list of reminders for a specific date
    // ---------------------------------------------------------------
    if ($method === 'GET' && $action === 'day') {
        $date = $_GET['date'] ?? '';
        if (!$date || !is_valid_reminder_date($date)) {
            respond(['error' => 'a valid date (YYYY-MM-DD) is required'], 400);
        }

        $stmt = $pdo->prepare(
            'SELECT id, reminder_date, title, notes, created_at, updated_at
             FROM reminders
             WHERE reminder_date = :date
             ORDER BY id ASC'
        );
        $stmt->execute(['date' => $date]);

        respond($stmt->fetchAll());
    }

    // ---------------------------------------------------------------
    // POST ?action=save
    // Body: { "date": "YYYY-MM-DD", "items": [ {"id": null|int, "title": "..", "notes": ".."}, ... ] }
    // Inserts items without an id, updates items with an id.
    // Used by the "Save All" button to persist the whole form at once.
    // ---------------------------------------------------------------
    if ($method === 'POST' && $action === 'save') {
        $body = readJsonBody();
        $date = $body['date'] ?? '';
        $items = $body['items'] ?? [];

        if (!$date || !is_valid_reminder_date($date)) {
            respond(['error' => 'a valid date (YYYY-MM-DD) is required'], 400);
        }
        if (!is_array($items) || count($items) === 0) {
            respond(['error' => 'at least one item is required'], 400);
        }

        $items = filter_valid_reminder_items($items);
        if (count($items) === 0) {
            respond(['error' => 'at least one item with a non-empty title is required'], 400);
        }

        $pdo->beginTransaction();

        $insertStmt = $pdo->prepare(
            'INSERT INTO reminders (reminder_date, title, notes) VALUES (:date, :title, :notes)'
        );
        $updateStmt = $pdo->prepare(
            'UPDATE reminders SET title = :title, notes = :notes, updated_at = NOW()
             WHERE id = :id AND reminder_date = :date'
        );

        foreach ($items as $item) {
            $title = $item['title']; // already normalized by filter_valid_reminder_items()
            $notes = $item['notes'] ?? null;
            $id = $item['id'] ?? null;

            if ($id) {
                $updateStmt->execute([
                    'title' => $title,
                    'notes' => $notes,
                    'id' => $id,
                    'date' => $date,
                ]);
            } else {
                $insertStmt->execute([
                    'date' => $date,
                    'title' => $title,
                    'notes' => $notes,
                ]);
            }
        }

        $pdo->commit();

        $stmt = $pdo->prepare(
            'SELECT id, reminder_date, title, notes, created_at, updated_at
             FROM reminders WHERE reminder_date = :date ORDER BY id ASC'
        );
        $stmt->execute(['date' => $date]);

        respond(['success' => true, 'items' => $stmt->fetchAll()]);
    }

    // ---------------------------------------------------------------
    // PUT ?action=update
    // Body: { "id": int, "title": "..", "notes": ".." }
    // Edits a single existing reminder
    // ---------------------------------------------------------------
    if ($method === 'PUT' && $action === 'update') {
        $body = readJsonBody();
        $id = $body['id'] ?? null;
        $title = normalize_title((string) ($body['title'] ?? ''));
        $notes = $body['notes'] ?? null;

        if (!$id || $title === '') {
            respond(['error' => 'id and title are required'], 400);
        }

        $stmt = $pdo->prepare(
            'UPDATE reminders SET title = :title, notes = :notes, updated_at = NOW()
             WHERE id = :id RETURNING id, reminder_date, title, notes, created_at, updated_at'
        );
        $stmt->execute(['title' => $title, 'notes' => $notes, 'id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            respond(['error' => 'reminder not found'], 404);
        }

        respond(['success' => true, 'item' => $row]);
    }

    // ---------------------------------------------------------------
    // DELETE ?action=delete
    // Body: { "id": int }
    // ---------------------------------------------------------------
    if ($method === 'DELETE' && $action === 'delete') {
        $body = readJsonBody();
        $id = $body['id'] ?? ($_GET['id'] ?? null);

        if (!$id) {
            respond(['error' => 'id is required'], 400);
        }

        $stmt = $pdo->prepare('DELETE FROM reminders WHERE id = :id');
        $stmt->execute(['id' => $id]);

        respond(['success' => true, 'deleted' => $stmt->rowCount() > 0]);
    }

    respond(['error' => 'Unknown action or method'], 404);
} catch (Throwable $e) {
    respond(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
