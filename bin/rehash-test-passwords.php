<?php

$pdo = new PDO('pgsql:host=127.0.0.1;dbname=one4all', 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$accounts = [
    'superadmin@one4all.cm' => 'SuperAdmin123!',
    'admin@one4all.cm' => 'Admin123!',
    'client@one4all.cm' => 'Client123!',
    'agency@one4all.cm' => 'Agency123!',
    'driver@one4all.cm' => 'Driver123!',
];

$stmt = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');

foreach ($accounts as $email => $plain) {
    $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]);
    $stmt->execute(['password' => $hash, 'email' => $email]);
    echo "Updated $email\n";
}

echo "Done.\n";
