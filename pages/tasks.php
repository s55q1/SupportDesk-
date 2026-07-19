<?php
/**
 * pages/tasks.php
 * إرسال ومتابعة المهام بين جميع المستخدمين بأي اتجاه، بشريط تتبع مراحل موحّد.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
requireAuth(['admin', 'engineer', 'technician', 'employee']);

$pdo = getDatabaseConnection();
$current = currentUser();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['create_task'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        if ($title === '' || $assignedTo <= 0) {
            $errorMessage = 'يرجى إدخال عنوان المهمة واختيار المستلم.';
        } elseif ($assignedTo === (int)$current['id']) {
            $errorMessage = 'لا يمكن إرسال مهمة لنفسك.';
        } else {
            $attachmentPath = null;
            if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
                if (in_array($ext, $allowedExt, true) && $_FILES['attachment']['size'] < 8 * 1024 * 1024) {
                    $dir = __DIR__ . '/../uploads';
                    $fname = 'task_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $fname)) {
                        $attachmentPath = 'uploads/' . $fname;
                    }
                } else {
                    $errorMessage = 'صيغة الملف غير مدعومة أو الحجم يتجاوز 8 ميجابايت.';
                }
            }
            if ($errorMessage === '') {
                createTask($pdo, $title, $description, $priority, (int)$current['id'], $assignedTo, $attachmentPath);
                $successMessage = 'تم إرسال المهمة بنجاح.';
            }
        }
    } elseif (isset($_POST['start_task'])) {
        startTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم تسجيل بدء العمل على المهمة.';
    } elseif (isset($_POST['complete_task'])) {
        completeTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم تسجيل إنجاز المهمة، بانتظار اعتماد المرسل.';
    } elseif (isset($_POST['confirm_task'])) {
        confirmTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم اعتماد إنجاز المهمة وإغلاقها.';
    }
}

$otherUsers = getOtherUsers($pdo, (int)$current['id']);
$receivedTasks = getReceivedTasks($pdo, (int)$current['id']);
$sentTasks = getSentTasks($pdo, (int)$current['id']);
$priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عاجلة'];
$statusLabels = ['pending' => 'بانتظار البدء', 'started' => 'قيد التنفيذ', 'completed' => 'بانتظار الاعتماد', 'confirmed' => 'مكتملة ومعتمدة'];

$pageTitle = 'المهام بين المستخدمين';
$activeNav = 'tasks';
$user = $current;
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>المهام بين المستخدمين</h1><p>إرسال مهمة إلى أي مستخدم بالنظام ومتابعة مراحل تنفيذها لحظياً.</p></div>
    <a class="btn btn-primary" href="task_new.php">+ إرسال مهمة</a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div class="stat-row">
    <div class="stat-tile tone-accent"><div class="l">مهام واردة إليك</div><div class="n"><?= count($receivedTasks) ?></div></div>
    <div class="stat-tile tone-warning"><div class="l">مهام أرسلتها</div><div class="n"><?= count($sentTasks) ?></div></div>
</div>

<div class="card">
    <div class="card-head"><div><h2>المهام الواردة إليك</h2><p class="sub" style="margin:0">مهام أوكلها لك مستخدمون آخرون</p></div></div>
    <?php if (empty($receivedTasks)): ?>
        <div class="alert alert-info" style="margin:0">لا توجد مهام واردة إليك حالياً.</div>
    <?php else: foreach ($receivedTasks as $task): ?>
        <div class="ticket-card st-<?= $task['status'] === 'confirmed' ? 'closed' : ($task['status'] === 'pending' ? 'open' : 'assigned') ?>">
            <div class="ticket-top">
                <span class="ticket-title">#<?= (int)$task['id'] ?> — <?= htmlspecialchars($task['title']) ?></span>
                <span class="badge badge-<?= $task['status'] ?>"><?= $statusLabels[$task['status']] ?></span>
            </div>
            <div class="ticket-meta">من: <?= htmlspecialchars($task['created_by_name']) ?> · أولوية: <?= $priorityLabels[$task['priority']] ?></div>
            <?php if ($task['description']): ?><div class="ticket-meta" style="margin-top:4px"><?= nl2br(htmlspecialchars($task['description'])) ?></div><?php endif; ?>
            <?= renderTaskAttachment($task['attachment']) ?>
            <div style="margin-top:14px;overflow-x:auto"><?= renderTaskStepper($task['status']) ?></div>
            <div class="ticket-actions">
                <?php if ($task['status'] === 'pending'): ?>
                    <form method="post"><?= csrfField() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>" />
                        <button class="btn btn-primary btn-sm" type="submit" name="start_task">بدء العمل</button>
                    </form>
                <?php elseif ($task['status'] === 'started'): ?>
                    <form method="post"><?= csrfField() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>" />
                        <button class="btn btn-success btn-sm" type="submit" name="complete_task">إنهاء المهمة</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<div class="card">
    <div class="card-head"><div><h2>المهام التي أرسلتها</h2><p class="sub" style="margin:0">تتبع حالة المهام الموكَلة لمستخدمين آخرين</p></div></div>
    <?php if (empty($sentTasks)): ?>
        <div class="alert alert-info" style="margin:0">لم ترسل أي مهام بعد.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الرقم</th><th>العنوان</th><th>المستلم</th><th>الحالة</th><th>تتبع المراحل</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sentTasks as $task): ?>
                    <tr>
                        <td class="num">#<?= (int)$task['id'] ?></td>
                        <td><?= htmlspecialchars($task['title']) ?></td>
                        <td><?= htmlspecialchars($task['assigned_to_name']) ?></td>
                        <td><span class="badge badge-<?= $task['status'] ?>"><?= $statusLabels[$task['status']] ?></span><?= renderTaskAttachment($task['attachment']) ?></td>
                        <td style="min-width:280px"><?= renderTaskStepper($task['status']) ?></td>
                        <td>
                            <?php if ($task['status'] === 'completed'): ?>
                                <form method="post"><?= csrfField() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>" />
                                    <button class="btn btn-success btn-sm" type="submit" name="confirm_task">اعتماد الإنجاز</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
