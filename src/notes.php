<?php
declare(strict_types=1);

// All note queries are prepared AND scoped to the owner.

function notes_for_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function find_note_for_user(PDO $db, int $noteId, int $userId): array|false
{
    $stmt = $db->prepare('SELECT id, title, body FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$noteId, $userId]);
    return $stmt->fetch();
}

function create_note(PDO $db, int $userId, string $title, string $body): void
{
    $stmt = $db->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $title, $body]);
}

// Returns true only if the note exists AND belongs to $userId.
function update_note(PDO $db, int $noteId, int $userId, string $title, string $body): bool
{
    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $body, $noteId, $userId]);
    return $stmt->rowCount() === 1;
}

function delete_note(PDO $db, int $noteId, int $userId): bool
{
    $stmt = $db->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$noteId, $userId]);
    return $stmt->rowCount() === 1;
}

// Returns an error message, or null when the input is valid.
function validate_note(mixed $title, mixed $body): ?string
{
    if (!is_string($title) || !is_string($body)) {
        return 'Title and body must be text.';
    }
    $title = trim($title);
    if ($title === '' || mb_strlen($title) > 120) {
        return 'Title must be 1-120 characters.';
    }
    if (trim($body) === '' || mb_strlen($body) > 5000) {
        return 'Body must be 1-5000 characters.';
    }
    return null;
}