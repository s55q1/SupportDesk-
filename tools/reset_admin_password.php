<?php
/**
 * tools/reset_admin_password.php
 * سكربت صغير لإعادة تعيين كلمة مرور المستخدم 'admin' إلى 'admin123'
 * استخدم هذا عبر سطر الأوامر: php tools/reset_admin_password.php
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    $new = 'admin123';
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE username = 'admin'");
    $stmt->execute(['pwd' => $hash]);
    echo "تم إعادة تعيين كلمة مرور 'admin' إلى: admin123\n";
    echo "سجل الدخول ثم غيّر كلمة المرور فوراً من واجهة الإدارة.\n";
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
