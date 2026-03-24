<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$user = getCurrentUser();

if ($user) {
    json_response([
        'loggedIn' => true,
        'id'       => (int) $user['id'],
        'name'     => $user['name'],
        'email'    => $user['email'],
    ]);
}

json_response([
    'loggedIn' => false,
    'name'     => null,
]);
