<?php
/**
 * pages/task_new.php
 * صفحة مستقلة لإرسال مهمة جديدة لأي مستخدم بالنظام.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
requireAuth(['admin', 'engineer', 'technician', 'employee']);

$pdo = getDatabaseConnection();
$current = currentUser();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    requireValidCsrf();
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
            header('Location: tasks.php');
            exit;
        }
    }
}

$otherUsers = getOtherUsers($pdo, (int)$current['id']);

$pageTitle = 'إرسال مهمة جديدة';
$activeNav = 'tasks';
$user = $current;
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>إرسال مهمة جديدة</h1><p>اختر المستلم وحدد تفاصيل المهمة المطلوب إنجازها.</p></div>
    <a class="btn btn-ghost" href="tasks.php">رجوع للمهام</a>
</div>

<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="field">
            <label>المستلم</label>
            <select name="assigned_to" required>
                <option value="">— اختر مستخدماً —</option>
                <?php foreach ($otherUsers as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>عنوان المهمة</label><input name="title" required /></div>
        <div class="field"><label>تفاصيل المهمة</label><textarea name="description" rows="3"></textarea></div>
        <div class="field">
            <label>الأولوية</label>
            <select name="priority">
                <option value="low">منخفضة</option>
                <option value="medium" selected>متوسطة</option>
                <option value="high">عاجلة</option>
            </select>
        </div>
        <div class="field">
            <label>إرفاق صورة أو ملف (اختياري)</label>
            <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" />
            <div class="hint">الصيغ المدعومة: صور، PDF، Word، Excel، ZIP — بحد أقصى 8 ميجابايت</div>
        </div>
        <button type="submit" name="create_task" class="btn btn-primary btn-block">إرسال المهمة</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
