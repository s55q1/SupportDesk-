<?php
/**
 * includes/audit.php
 * وظائف بسيطة لتسجيل أحداث التدقيق (audit logs).
 */
require_once __DIR__ . '/../config/database.php';

function logAudit(PDO $pdo, string $action, ?int $userId = null, ?int $ticketId = null, string $details = ''): bool
{
    $sql = 'INSERT INTO audit_logs (user_id, ticket_id, action, details) VALUES (:user_id, :ticket_id, :action, :details)';
    $stmt = $pdo->prepare($sql);
    return (bool)$stmt->execute([
        'user_id' => $userId,
        'ticket_id' => $ticketId,
        'action' => $action,
        'details' => $details,
    ]);
}
