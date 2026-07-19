<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth(['admin']);
$pdo = getDatabaseConnection();
$pdo->exec('DELETE FROM tasks');
echo 'ok';
