<?php
/**
 * includes/search.php
 * منطق التذاكر: الفئات، الحلول، دورة حياة التذكرة، السجل، التعليقات.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit.php';

const DUE_HOURS = ['high' => 4, 'medium' => 24, 'low' => 72];

function getCategories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, icon FROM categories ORDER BY name')->fetchAll();
}

function findQuickSolutions(PDO $pdo, int $categoryId, string $keywords = ''): array
{
    $keywords = trim($keywords);
    if ($keywords === '') {
        $stmt = $pdo->prepare('SELECT s.id, s.description, s.keywords, c.name AS category_name
            FROM solutions s JOIN categories c ON s.category_id = c.id
            WHERE s.category_id = :cat ORDER BY s.created_at DESC LIMIT 8');
        $stmt->execute(['cat' => $categoryId]);
        return $stmt->fetchAll();
    }
    $term = '%' . $keywords . '%';
    $stmt = $pdo->prepare('SELECT s.id, s.description, s.keywords, c.name AS category_name
        FROM solutions s JOIN categories c ON s.category_id = c.id
        WHERE s.category_id = :cat AND (s.keywords LIKE :term OR s.description LIKE :term)
        ORDER BY s.created_at DESC LIMIT 8');
    $stmt->execute(['cat' => $categoryId, 'term' => $term]);
    return $stmt->fetchAll();
}

function saveSolution(PDO $pdo, int $categoryId, string $description, string $keywords): int
{
    $stmt = $pdo->prepare('INSERT INTO solutions (category_id, description, keywords) VALUES (:c, :d, :k)');
    $stmt->execute(['c' => $categoryId, 'd' => $description, 'k' => $keywords ?: $description]);
    return (int)$pdo->lastInsertId();
}

function pushTicketHistory(PDO $pdo, int $ticketId, string $action, string $byName): void
{
    $stmt = $pdo->prepare('INSERT INTO ticket_history (ticket_id, action, by_name) VALUES (:t, :a, :b)');
    $stmt->execute(['t' => $ticketId, 'a' => $action, 'b' => $byName]);
}

function getTicketHistory(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare('SELECT action, by_name, created_at FROM ticket_history WHERE ticket_id = :t ORDER BY id DESC');
    $stmt->execute(['t' => $ticketId]);
    return $stmt->fetchAll();
}

function getTicketComments(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare('SELECT by_name, text, created_at FROM ticket_comments WHERE ticket_id = :t ORDER BY id ASC');
    $stmt->execute(['t' => $ticketId]);
    return $stmt->fetchAll();
}

function addTicketComment(PDO $pdo, int $ticketId, string $byName, string $text): void
{
    $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, by_name, text) VALUES (:t, :b, :x)');
    $stmt->execute(['t' => $ticketId, 'b' => $byName, 'x' => $text]);
    pushTicketHistory($pdo, $ticketId, 'أضاف تعليقاً', $byName);
}

const TICKET_SELECT = "SELECT t.*, c.name AS category_name, c.icon AS category_icon,
    creator.full_name AS created_by_name, assignee.full_name AS assigned_to_name, assignee.username AS assigned_to_username,
    creator.username AS created_by_username
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN users creator ON t.created_by = creator.id
    LEFT JOIN users assignee ON t.assigned_to = assignee.id";

function createTicket(PDO $pdo, int $categoryId, string $title, string $description, string $priority, int $createdBy, ?string $attachment = null): int
{
    $stmt = $pdo->prepare('INSERT INTO tickets (category_id, created_by, title, description, priority, status, attachment)
        VALUES (:cat, :by, :title, :desc, :prio, "open", :att)');
    $stmt->execute([
        'cat' => $categoryId, 'by' => $createdBy, 'title' => $title,
        'desc' => $description, 'prio' => $priority, 'att' => $attachment,
    ]);
    $id = (int)$pdo->lastInsertId();
    $userName = $pdo->query('SELECT full_name FROM users WHERE id = ' . $createdBy)->fetchColumn();
    pushTicketHistory($pdo, $id, 'فتح التذكرة', $userName ?: '');
    logAudit($pdo, 'ticket_created', $createdBy, $id, 'عنوان: ' . $title);
    return $id;
}

function getTicketById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(TICKET_SELECT . ' WHERE t.id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getOpenTickets(PDO $pdo): array
{
    return $pdo->query(TICKET_SELECT . " WHERE t.status = 'open' ORDER BY t.id DESC")->fetchAll();
}

function getUserTickets(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(TICKET_SELECT . ' WHERE t.created_by = :uid ORDER BY t.id DESC');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function getAssignedTickets(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(TICKET_SELECT . " WHERE t.assigned_to = :uid AND t.status != 'closed' ORDER BY t.id DESC");
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function getClosedTicketsBy(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(TICKET_SELECT . " WHERE t.assigned_to = :uid AND t.status = 'closed' ORDER BY t.id DESC");
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function getAllClosedTickets(PDO $pdo): array
{
    return $pdo->query(TICKET_SELECT . " WHERE t.status = 'closed' ORDER BY t.id DESC")->fetchAll();
}

function getAllTicketsForTracking(PDO $pdo): array
{
    return $pdo->query(TICKET_SELECT . ' ORDER BY t.id DESC')->fetchAll();
}

function isTicketOverdue(array $ticket): bool
{
    if ($ticket['status'] !== 'assigned' || empty($ticket['due_at'])) {
        return false;
    }
    return strtotime($ticket['due_at']) < time();
}

function assignTicket(PDO $pdo, int $ticketId, int $assignedTo, int $assignedBy, string $assignedByName, string $assignedToName): bool
{
    $ticket = getTicketById($pdo, $ticketId);
    if (!$ticket) {
        return false;
    }
    $dueAt = date('Y-m-d H:i:s', time() + (DUE_HOURS[$ticket['priority']] ?? 24) * 3600);
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'assigned', assigned_to = :a, due_at = :d WHERE id = :id");
    $stmt->execute(['a' => $assignedTo, 'd' => $dueAt, 'id' => $ticketId]);
    pushTicketHistory($pdo, $ticketId, 'تعيين إلى ' . $assignedToName, $assignedByName);
    logAudit($pdo, 'ticket_assigned', $assignedBy, $ticketId, 'assigned_to:' . $assignedTo);
    return true;
}

function startTicketWork(PDO $pdo, int $ticketId, int $userId, string $userName): bool
{
    $stmt = $pdo->prepare("UPDATE tickets SET started = 1 WHERE id = :id AND assigned_to = :u");
    $stmt->execute(['id' => $ticketId, 'u' => $userId]);
    if ($stmt->rowCount() > 0) {
        pushTicketHistory($pdo, $ticketId, 'بدء العمل', $userName);
        return true;
    }
    return false;
}

function resolveTicket(PDO $pdo, int $ticketId, int $userId, string $userName, string $solution, bool $saveArchive): bool
{
    $ticket = getTicketById($pdo, $ticketId);
    if (!$ticket || (int)$ticket['assigned_to'] !== $userId) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'resolved', started = 1, solution = :s WHERE id = :id");
    $stmt->execute(['s' => $solution, 'id' => $ticketId]);
    if ($saveArchive) {
        saveSolution($pdo, (int)$ticket['category_id'], $solution, $ticket['title']);
    }
    pushTicketHistory($pdo, $ticketId, 'تحديد الحل - بانتظار تأكيد الموظف', $userName);
    logAudit($pdo, 'ticket_resolved', $userId, $ticketId, '');
    return true;
}

function confirmCloseTicket(PDO $pdo, int $ticketId, int $userId, string $userName): bool
{
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_at = :c WHERE id = :id AND created_by = :u AND status = 'resolved'");
    $stmt->execute(['c' => date('Y-m-d H:i:s'), 'id' => $ticketId, 'u' => $userId]);
    if ($stmt->rowCount() > 0) {
        pushTicketHistory($pdo, $ticketId, 'تأكيد الحل وإغلاق الطلب', $userName);
        logAudit($pdo, 'ticket_closed', $userId, $ticketId, '');
        return true;
    }
    return false;
}

function cancelTicket(PDO $pdo, int $ticketId, int $userId, string $userName): bool
{
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = :id AND created_by = :u AND status = 'open'");
    $stmt->execute(['id' => $ticketId, 'u' => $userId]);
    if ($stmt->rowCount() > 0) {
        pushTicketHistory($pdo, $ticketId, 'إلغاء التذكرة', $userName);
        return true;
    }
    return false;
}

function editTicketDetails(PDO $pdo, int $ticketId, int $userId, string $userName, string $title, string $description): bool
{
    $stmt = $pdo->prepare("UPDATE tickets SET title = :t, description = :d WHERE id = :id AND created_by = :u AND status = 'open'");
    $stmt->execute(['t' => $title, 'd' => $description, 'id' => $ticketId, 'u' => $userId]);
    if ($stmt->rowCount() > 0) {
        pushTicketHistory($pdo, $ticketId, 'تعديل بيانات التذكرة', $userName);
        return true;
    }
    return false;
}

function getSuggestedSolutions(PDO $pdo, int $categoryId): array
{
    $stmt = $pdo->prepare('SELECT id, description, keywords FROM solutions WHERE category_id = :c ORDER BY created_at DESC LIMIT 6');
    $stmt->execute(['c' => $categoryId]);
    return $stmt->fetchAll();
}

function techniciansAndEngineers(PDO $pdo): array
{
    return $pdo->query("SELECT u.id, u.username, u.full_name, r.name AS role
        FROM users u JOIN roles r ON u.role_id = r.id
        WHERE r.name IN ('technician','engineer') ORDER BY u.full_name")->fetchAll();
}

function getAllUsers(PDO $pdo): array
{
    return $pdo->query('SELECT u.id, u.username, u.full_name, r.name AS role
        FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC')->fetchAll();
}

function countAdmins(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.name='admin'")->fetchColumn();
}

function roleId(PDO $pdo, string $role): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :n');
    $stmt->execute(['n' => $role]);
    return (int)$stmt->fetchColumn();
}

function createUserAccount(PDO $pdo, string $username, string $password, string $fullName, string $role): bool
{
    $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, role_id) VALUES (:u, :p, :f, :r)');
    return $stmt->execute([
        'u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT),
        'f' => $fullName, 'r' => roleId($pdo, $role),
    ]);
}

function updateUserAccount(PDO $pdo, int $userId, string $role, ?string $newPassword, ?string $fullName = null): void
{
    $stmt = $pdo->prepare('UPDATE users SET role_id = :r WHERE id = :id');
    $stmt->execute(['r' => roleId($pdo, $role), 'id' => $userId]);
    if ($newPassword) {
        $stmt2 = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
        $stmt2->execute(['p' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
    }
    if ($fullName !== null && trim($fullName) !== '') {
        $stmt3 = $pdo->prepare('UPDATE users SET full_name = :f WHERE id = :id');
        $stmt3->execute(['f' => trim($fullName), 'id' => $userId]);
    }
}

function deleteUserAccount(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE tickets SET assigned_to = NULL, status = \'open\', due_at = NULL WHERE assigned_to = :id')
        ->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
}

function findUserByUsername(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, full_name FROM users WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getRecentAuditLogs(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare('SELECT * FROM audit_logs ORDER BY id DESC LIMIT :lim');
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* ======================= المهام بين المستخدمين ======================= */

const TASK_SELECT = "SELECT t.*, cb.full_name AS created_by_name, at.full_name AS assigned_to_name
    FROM tasks t
    JOIN users cb ON t.created_by = cb.id
    JOIN users at ON t.assigned_to = at.id";

function getOtherUsers(PDO $pdo, int $selfId): array
{
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, r.name AS role
        FROM users u JOIN roles r ON u.role_id = r.id
        WHERE u.id != :id ORDER BY u.full_name");
    $stmt->execute(['id' => $selfId]);
    return $stmt->fetchAll();
}

function createTask(PDO $pdo, string $title, string $description, string $priority, int $createdBy, int $assignedTo, ?string $attachment = null): bool
{
    $stmt = $pdo->prepare('INSERT INTO tasks (title, description, priority, created_by, assigned_to, attachment) VALUES (:t, :d, :p, :cb, :at, :a)');
    return $stmt->execute(['t' => $title, 'd' => $description, 'p' => $priority, 'cb' => $createdBy, 'at' => $assignedTo, 'a' => $attachment]);
}

function getSentTasks(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(TASK_SELECT . ' WHERE t.created_by = :id ORDER BY t.id DESC');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchAll();
}

function getReceivedTasks(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(TASK_SELECT . ' WHERE t.assigned_to = :id ORDER BY t.id DESC');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchAll();
}

function getTaskById(PDO $pdo, int $taskId): ?array
{
    $stmt = $pdo->prepare(TASK_SELECT . ' WHERE t.id = :id');
    $stmt->execute(['id' => $taskId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function startTask(PDO $pdo, int $taskId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'started', started_at = datetime('now') WHERE id = :id AND assigned_to = :uid AND status = 'pending'");
    $stmt->execute(['id' => $taskId, 'uid' => $userId]);
    return $stmt->rowCount() > 0;
}

function completeTask(PDO $pdo, int $taskId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed', completed_at = datetime('now') WHERE id = :id AND assigned_to = :uid AND status = 'started'");
    $stmt->execute(['id' => $taskId, 'uid' => $userId]);
    return $stmt->rowCount() > 0;
}

function confirmTask(PDO $pdo, int $taskId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'confirmed', confirmed_at = datetime('now') WHERE id = :id AND created_by = :uid AND status = 'completed'");
    $stmt->execute(['id' => $taskId, 'uid' => $userId]);
    return $stmt->rowCount() > 0;
}

function renderTaskAttachment(?string $path): string
{
    if (!$path) {
        return '';
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    $url = '../' . htmlspecialchars($path);
    if ($isImage) {
        return '<div style="margin-top:10px"><img src="' . $url . '" style="max-width:220px;border-radius:8px;border:1px solid var(--line)" /></div>';
    }
    return '<div style="margin-top:8px"><a class="btn btn-ghost btn-sm" href="' . $url . '" target="_blank" rel="noopener">تحميل المرفق (' . htmlspecialchars($ext) . ')</a></div>';
}

function taskStageIndex(string $status): int
{
    return ['pending' => 0, 'started' => 1, 'completed' => 2, 'confirmed' => 3][$status] ?? 0;
}

function renderTaskStepper(string $status): string
{
    $stages = ['تم التوجيه', 'بدء العمل', 'مكتمل', 'تم الرفع'];
    $current = taskStageIndex($status);
    $html = '<div class="stepper">';
    foreach ($stages as $i => $label) {
        $done = $i <= $current;
        $html .= '<div class="step ' . ($done ? 'done' : '') . ($i === $current ? ' current' : '') . '">'
            . '<span class="step-dot"></span><span class="step-label">' . htmlspecialchars($label) . '</span></div>';
        if ($i < count($stages) - 1) {
            $html .= '<span class="step-line ' . ($i < $current ? 'done' : '') . '"></span>';
        }
    }
    $html .= '</div>';
    return $html;
}
