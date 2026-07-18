<?php
/**
 * config/database.php
 * اتصال SQLite عبر PDO — ملف واحد بدون سيرفر قاعدة بيانات منفصل.
 * ينشئ القاعدة ويبذرها تلقائياً أول مرة تشتغل.
 */
function getDatabaseConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../database/helpdesk.sqlite';
    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);
        require __DIR__ . '/../database/seed.php';
        seedDatabase($pdo);
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            priority TEXT NOT NULL DEFAULT 'medium' CHECK (priority IN ('low','medium','high')),
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','started','completed','confirmed')),
            created_by INTEGER NOT NULL,
            assigned_to INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            started_at TEXT,
            completed_at TEXT,
            confirmed_at TEXT,
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (assigned_to) REFERENCES users(id)
        )");
        $hasAttachment = $pdo->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('attachment', $hasAttachment, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN attachment TEXT');
        }
    }

    return $pdo;
}
