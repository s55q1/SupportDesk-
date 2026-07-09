<?php
/**
 * includes/auth.php
 * يدير تسجيل الدخول والتحقق من الصلاحيات.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300;

/**
 * تحقق مما إذا كان مسموحاً بمحاولة تسجيل دخول جديدة (حماية من brute-force).
 */
function loginAttemptsAllowed(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? null;
    if (!$attempts) {
        return true;
    }
    if ($attempts['count'] >= LOGIN_MAX_ATTEMPTS && (time() - $attempts['last']) < LOGIN_LOCKOUT_SECONDS) {
        return false;
    }
    if ((time() - $attempts['last']) >= LOGIN_LOCKOUT_SECONDS) {
        unset($_SESSION['login_attempts']);
    }
    return true;
}

function recordFailedLoginAttempt(): void
{
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'last' => time()];
    $attempts['count']++;
    $attempts['last'] = time();
    $_SESSION['login_attempts'] = $attempts;
}

/**
 * محاولة تسجيل الدخول بمستخدم وكلمة مرور.
 */
function loginUser(PDO $pdo, string $username, string $password): ?array
{
    if (!loginAttemptsAllowed()) {
        return null;
    }

    // الآن جدول `users` يرتبط بـ `roles` عبر `role_id`، لذا نستعلم باسم الدور
    $sql = 'SELECT u.id, u.username, u.password, r.name AS role, u.full_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = :username
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    if (!$user) {
        recordFailedLoginAttempt();
        return null;
    }

    $stored = $user['password'];

    // دعم طرق التحقق القديمة (hex SHA-256) والجديدة (password_hash)
    $isPasswordOk = false;
    if (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon') === 0) {
        // hash() الجديد
        if (password_verify($password, $stored)) {
            $isPasswordOk = true;
        }
    } else {
        // افتراض SHA-256 hex قديم
        if (hash('sha256', $password) === $stored) {
            $isPasswordOk = true;
            // ترقية الهاش إلى password_hash بدون تدخل المستخدم
            try {
                $pdoUpgrade = getDatabaseConnection();
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $uStmt = $pdoUpgrade->prepare('UPDATE users SET password = :pwd WHERE id = :id');
                $uStmt->execute(['pwd' => $newHash, 'id' => $user['id']]);
            } catch (Exception $e) {
                // لا نفشل عملية الدخول إن لم تتم الترقية بنجاح
            }
        }
    }

    if (! $isPasswordOk) {
        recordFailedLoginAttempt();
        return null;
    }

    unset($_SESSION['login_attempts']);
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'full_name' => $user['full_name'],
    ];

    return $_SESSION['user'];
}

/**
 * الحصول على بيانات المستخدم الحالي من الجلسة.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * التحقق من أن المستخدم مسجل دخول وله صلاحية واحدة على الأقل.
 */
function requireAuth(array $roles = [])
{
    $user = currentUser();
    if (!$user) {
        header('Location: ../pages/login.php');
        exit;
    }

    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo '<h1>403 - غير مسموح</h1><p>ليس لديك صلاحية الوصول لهذه الصفحة.</p>';
        exit;
    }
}

/**
 * تسجيل الخروج وحذف الجلسة.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
