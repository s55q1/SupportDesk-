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

function handleTaskAttachmentUpload(): ?string
{
    if (empty($_FILES['attachment']['name']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
    if (!in_array($ext, $allowedExt, true) || $_FILES['attachment']['size'] >= 8 * 1024 * 1024) {
        return null;
    }
    $dir = __DIR__ . '/../uploads';
    $fname = 'task_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $fname)) {
        return 'uploads/' . $fname;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['start_task'])) {
        startTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم تسجيل بدء العمل على المهمة.';
    } elseif (isset($_POST['complete_task'])) {
        completeTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم تسجيل إنجاز المهمة، بانتظار اعتماد المرسل.';
    } elseif (isset($_POST['confirm_task'])) {
        confirmTask($pdo, (int)$_POST['task_id'], (int)$current['id']);
        $successMessage = 'تم اعتماد إنجاز المهمة وإغلاقها.';
    } elseif (isset($_POST['delete_task'])) {
        if (deleteTask($pdo, (int)$_POST['task_id'], (int)$current['id'])) {
            header('Location: tasks.php');
            exit;
        }
        $errorMessage = 'تعذّر حذف المهمة.';
    } elseif (isset($_POST['edit_task'])) {
        $taskId = (int)$_POST['task_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        if ($title === '') {
            $errorMessage = 'عنوان المهمة مطلوب.';
        } elseif (editTaskDetails($pdo, $taskId, (int)$current['id'], $title, $description, $priority)) {
            $successMessage = 'تم حفظ تعديلات المهمة.';
        } else {
            $errorMessage = 'تعذّر تعديل المهمة (قد تكون بدأت بالفعل).';
        }
    } elseif (isset($_POST['add_task_comment'])) {
        $taskId = (int)$_POST['task_id'];
        $text = trim($_POST['comment_text'] ?? '');
        $task = getTaskById($pdo, $taskId);
        $isParty = $task && ((int)$task['created_by'] === (int)$current['id'] || (int)$task['assigned_to'] === (int)$current['id']);
        if ($isParty) {
            $attachmentPath = handleTaskAttachmentUpload();
            if ($text !== '' || $attachmentPath) {
                addTaskComment($pdo, $taskId, $current['full_name'], $text, $attachmentPath);
                $successMessage = 'تم إضافة الملاحظة.';
            }
        }
    }
}

$otherUsers = getOtherUsers($pdo, (int)$current['id']);
$receivedTasks = getReceivedTasks($pdo, (int)$current['id']);
$sentTasks = getSentTasks($pdo, (int)$current['id']);
$priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عاجلة'];
$statusLabels = ['pending' => 'بانتظار البدء', 'started' => 'قيد التنفيذ', 'completed' => 'بانتظار الاعتماد', 'confirmed' => 'مكتملة ومعتمدة'];

$selectedTaskId = (int)($_GET['task_id'] ?? 0);
$selectedTask = null;
if ($selectedTaskId) {
    $t = getTaskById($pdo, $selectedTaskId);
    if ($t && ((int)$t['created_by'] === (int)$current['id'] || (int)$t['assigned_to'] === (int)$current['id'])) {
        $selectedTask = $t;
    }
}
$selectedComments = $selectedTask ? getTaskComments($pdo, (int)$selectedTask['id']) : [];

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
                <a class="btn btn-ghost btn-sm" href="?task_id=<?= (int)$task['id'] ?>">التفاصيل والملاحظات</a>
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
                        <td style="white-space:nowrap">
                            <?php if ($task['status'] === 'completed'): ?>
                                <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>" />
                                    <button class="btn btn-success btn-sm" type="submit" name="confirm_task">اعتماد الإنجاز</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-ghost btn-sm" href="?task_id=<?= (int)$task['id'] ?>">التفاصيل</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($selectedTask): $isCreator = (int)$selectedTask['created_by'] === (int)$current['id']; ?>
    <div class="card" style="margin-top:22px">
        <div class="card-head">
            <div><h2>تفاصيل المهمة #<?= (int)$selectedTask['id'] ?></h2><p class="sub" style="margin:0"><?= htmlspecialchars($selectedTask['title']) ?></p></div>
            <a class="btn btn-ghost btn-sm" href="tasks.php">إغلاق</a>
        </div>

        <div class="ticket-card st-<?= $selectedTask['status'] === 'confirmed' ? 'closed' : ($selectedTask['status'] === 'pending' ? 'open' : 'assigned') ?>">
            <div class="ticket-top">
                <span class="ticket-title">من: <?= htmlspecialchars($selectedTask['created_by_name']) ?> ← إلى: <?= htmlspecialchars($selectedTask['assigned_to_name']) ?></span>
                <span class="badge badge-<?= $selectedTask['status'] ?>"><?= $statusLabels[$selectedTask['status']] ?></span>
            </div>
            <?php if ($selectedTask['description']): ?><div class="ticket-meta" style="margin-top:4px"><?= nl2br(htmlspecialchars($selectedTask['description'])) ?></div><?php endif; ?>
            <?= renderTaskAttachment($selectedTask['attachment']) ?>
            <div style="margin-top:14px;overflow-x:auto"><?= renderTaskStepper($selectedTask['status']) ?></div>
        </div>

        <?php if ($isCreator): ?>
            <div class="ticket-actions" style="margin-top:14px">
                <?php if ($selectedTask['status'] === 'pending'): ?>
                    <button class="btn btn-ghost btn-sm" type="button" onclick="var d=document.getElementById('edit-task-box');d.open=!d.open;">تعديل المهمة</button>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('تأكيد حذف هذي المهمة نهائياً؟');">
                    <?= csrfField() ?>
                    <input type="hidden" name="task_id" value="<?= (int)$selectedTask['id'] ?>" />
                    <button class="btn btn-danger btn-sm" type="submit" name="delete_task">حذف المهمة</button>
                </form>
            </div>
            <?php if ($selectedTask['status'] === 'pending'): ?>
                <details class="collapse-box" id="edit-task-box" style="margin-top:14px">
                    <summary style="display:none"></summary>
                    <form method="post" class="body">
                        <?= csrfField() ?>
                        <input type="hidden" name="task_id" value="<?= (int)$selectedTask['id'] ?>" />
                        <div class="field"><label>عنوان المهمة</label><input name="title" value="<?= htmlspecialchars($selectedTask['title']) ?>" required /></div>
                        <div class="field"><label>تفاصيل المهمة</label><textarea name="description" rows="3"><?= htmlspecialchars($selectedTask['description']) ?></textarea></div>
                        <div class="field">
                            <label>الأولوية</label>
                            <select name="priority">
                                <?php foreach ($priorityLabels as $pv => $pl): ?>
                                    <option value="<?= $pv ?>" <?= $selectedTask['priority'] === $pv ? 'selected' : '' ?>><?= $pl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="edit_task" class="btn btn-success btn-sm">حفظ التعديلات</button>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <h2 style="margin-top:22px;font-size:1rem">الملاحظات والردود</h2>
        <?php if (empty($selectedComments)): ?>
            <div class="alert alert-info" style="margin-top:10px">لا توجد ملاحظات على هذي المهمة بعد.</div>
        <?php else: ?>
            <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($selectedComments as $c): ?>
                    <div class="comment-item">
                        <div class="meta"><?= htmlspecialchars($c['by_name']) ?> — <?= htmlspecialchars($c['created_at']) ?></div>
                        <?php if ($c['text']): ?><div><?= nl2br(htmlspecialchars($c['text'])) ?></div><?php endif; ?>
                        <?= renderTaskAttachment($c['attachment']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="margin-top:14px">
            <?= csrfField() ?>
            <input type="hidden" name="task_id" value="<?= (int)$selectedTask['id'] ?>" />
            <div class="field"><label>ملاحظة أو رد</label><textarea name="comment_text" rows="2" placeholder="اكتب ردك هنا..."></textarea></div>
            <div class="field">
                <label>إرفاق صورة أو ملف (اختياري)</label>
                <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" />
            </div>
            <button type="submit" name="add_task_comment" class="btn btn-primary btn-sm">إرسال الرد</button>
        </form>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
