<?php
/**
 * includes/notifications.php
 * وظائف إشعارات بسيطة: محاولة إرسال بريد ثم تسجيل في ملف لو لم تنجح.
 */
require_once __DIR__ . '/../config/database.php';

function notifyUserByEmail(PDO $pdo, int $userId, string $subject, string $message): bool
{
    // جلب البريد من جدول users
    $stmt = $pdo->prepare('SELECT email, full_name FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['email'])) {
        // سجل فقط
        return logNotificationToFile($userId, $subject, $message, 'no-email');
    }

    $to = $row['email'];
    $headers = 'From: no-reply@localhost' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
    $sent = false;
    try {
        // محاولة الإرسال البسيطة؛ قد لا تعمل على بيئة محلية بدون SMTP
        $sent = mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        $sent = false;
    }

    if (! $sent) {
        return logNotificationToFile($userId, $subject, $message, 'mail-failed');
    }
    return true;
}

function logNotificationToFile(int $userId, string $subject, string $message, string $reason = ''): bool
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/notifications.log';
    $line = sprintf("%s | user:%d | reason:%s | subj:%s | msg:%s\n", date('Y-m-d H:i:s'), $userId, $reason, str_replace("\n", ' ', $subject), str_replace("\n", ' ', $message));
    return (bool)file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
