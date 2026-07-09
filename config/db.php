<?php
/**
 * config/db.php
 * واجهة بسيطة للوصول إلى اتصال قاعدة البيانات.
 * هذا الملف يبقي التسمية شائعة (`config/db.php`) ويعيد استخدام
 * `config/database.php` الموجود بالفعل.
 */
require_once __DIR__ . '/database.php';

/**
 * إرجاع كائن PDO جاهز للاستخدام
 */
function db(): PDO
{
    return getDatabaseConnection();
}
