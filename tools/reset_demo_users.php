<?php
declare(strict_types=1);

// Re-hashes the two synthetic lab accounts with password_hash().
// Run from the project folder:  php tools/reset_demo_users.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

$accounts = [
    'alex@example.test' => 'LabOnly-Alex-123',
    'sam@example.test'  => 'LabOnly-Sam-456',
];

$stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
foreach ($accounts as $email => $password) {
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
    echo "Reset {$email}\n";
}