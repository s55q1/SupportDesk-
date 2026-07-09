<?php
/**
 * pages/employee.php
 * واجهة الموظف: بحث عن حلول سريعة، فتح تذكرة، متابعة حالتها، تعليق، تأكيد الإغلاق.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
requireAuth(['employee', 'admin', 'engineer', 'technician']);

$pdo = getDatabaseConnection();
$user = currentUser();
$categories = getCategories($pdo);
$quickSolutions = null;
$successMessage = '';
$errorMessage = '';
$selectedCategory = 0;
$searchKeyword = '';

$statusLabels = ['open' => 'بانتظار التوجيه', 'assigned' => 'قيد المعالجة', 'resolved' => 'بانتظار تأكيدك', 'closed' => 'مغلقة', 'cancelled' => 'ملغاة'];
$priorityLabels = ['high' => 'عالية', 'medium' => 'متوسطة', 'low' => 'منخفضة'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['search_quick'])) {
        $selectedCategory = (int)($_POST['category_id'] ?? 0);
        $searchKeyword = trim($_POST['keyword'] ?? '');
        if ($selectedCategory > 0) {
            $quickSolutions = findQuickSolutions($pdo, $selectedCategory, $searchKeyword);
        } else {
            $errorMessage = 'يرجى اختيار فئة العطل لعرض الحلول السريعة.';
        }
    } elseif (isset($_POST['open_ticket'])) {
        $catId = (int)($_POST['category_id'] ?? 0);
        $title = trim($_POST['ticket_title'] ?? '');
        $desc = trim($_POST['ticket_description'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
        if ($catId === 0 || $title === '' || $desc === '') {
            $errorMessage = 'يرجى تعبئة جميع الحقول لفتح تذكرة.';
        } else {
            $attachmentPath = null;
            if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) && $_FILES['attachment']['size'] < 3 * 1024 * 1024) {
                    $dir = __DIR__ . '/../uploads';
                    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
                    $fname = uniqid('att_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $fname)) {
                        $attachmentPath = 'uploads/' . $fname;
                    }
                }
            }
            $ticketId = createTicket($pdo, $catId, $title, $desc, $priority, (int)$user['id'], $attachmentPath);
            $successMessage = 'تم فتح التذكرة بنجاح. رقم التذكرة: #' . $ticketId;
        }
    } elseif (isset($_POST['confirm_close'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if (confirmCloseTicket($pdo, $ticketId, (int)$user['id'], $user['full_name'])) {
            $successMessage = 'تم إغلاق الطلب #' . $ticketId . ' بنجاح.';
        } else {
            $errorMessage = 'تعذّر إغلاق الطلب.';
        }
    } elseif (isset($_POST['cancel_ticket'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if (cancelTicket($pdo, $ticketId, (int)$user['id'], $user['full_name'])) {
            $successMessage = 'تم إلغاء التذكرة #' . $ticketId . '.';
        } else {
            $errorMessage = 'تعذّر إلغاء التذكرة (ربما تم توجيهها بالفعل).';
        }
    } elseif (isset($_POST['edit_ticket'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $title = trim($_POST['edit_title'] ?? '');
        $desc = trim($_POST['edit_description'] ?? '');
        if ($title !== '' && $desc !== '' && editTicketDetails($pdo, $ticketId, (int)$user['id'], $user['full_name'], $title, $desc)) {
            $successMessage = 'تم حفظ تعديلات التذكرة #' . $ticketId . '.';
        } else {
            $errorMessage = 'تعذّر حفظ التعديل.';
        }
    } elseif (isset($_POST['add_comment'])) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $text = trim($_POST['comment_text'] ?? '');
        if ($text !== '') {
            addTicketComment($pdo, $ticketId, $user['full_name'], $text);
            $successMessage = 'تم إضافة التعليق.';
        }
    }
}

$userTickets = getUserTickets($pdo, (int)$user['id']);
$selectedTicketId = (int)($_GET['ticket_id'] ?? 0);
$selectedTicket = null;
foreach ($userTickets as $t) { if ((int)$t['id'] === $selectedTicketId) { $selectedTicket = $t; break; } }

$pageTitle = 'طلبات الدعم الفني';
$activeNav = 'employee';
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>طلبات الدعم الفني</h1><p>تسجيل طلب دعم جديد، والبحث في الحلول المتوفرة، ومتابعة حالة الطلبات السابقة.</p></div>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2>البحث في الحلول المتوفرة</h2>
        <p class="sub">اختر فئة العطل وكلمة مفتاحية لعرض الحلول الجاهزة قبل تسجيل طلب جديد.</p>
        <form method="post">
            <?= csrfField() ?>
            <div class="field">
                <label for="category_id">فئة العطل</label>
                <select id="category_id" name="category_id">
                    <option value="0">— اختر فئة —</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $category['id'] == $selectedCategory ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="keyword">كلمة مفتاحية</label>
                <input type="text" id="keyword" name="keyword" placeholder="مثال: انقطاع الإنترنت" value="<?= htmlspecialchars($searchKeyword) ?>" />
            </div>
            <button type="submit" name="search_quick" class="btn btn-primary">عرض الحلول</button>
        </form>
        <?php if ($quickSolutions !== null): ?>
            <div style="margin-top:16px">
                <?php if (empty($quickSolutions)): ?>
                    <div class="alert alert-info" style="margin:0">لا توجد حلول مطابقة. يمكنك تسجيل طلب دعم جديد.</div>
                <?php else: foreach ($quickSolutions as $solution): ?>
                    <div class="ticket-card st-closed">
                        <div><?= htmlspecialchars($solution['description']) ?></div>
                        <div class="ticket-meta">كلمات مفتاحية: <?= htmlspecialchars($solution['keywords']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>تسجيل طلب دعم جديد</h2>
        <p class="sub">يرجى تعبئة البيانات التالية بدقة لتسريع معالجة الطلب.</p>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="field">
                <label for="ticket_category">فئة العطل</label>
                <select id="ticket_category" name="category_id" required>
                    <option value="">— اختر فئة —</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ticket_title">عنوان الطلب</label>
                <input type="text" id="ticket_title" name="ticket_title" placeholder="مثال: تعذر الاتصال بالشبكة" required />
            </div>
            <div class="field">
                <label for="ticket_description">وصف تفصيلي للعطل</label>
                <textarea id="ticket_description" name="ticket_description" rows="4" placeholder="يرجى وصف المشكلة بالتفصيل" required></textarea>
            </div>
            <div class="field">
                <label for="priority">درجة الأولوية</label>
                <select id="priority" name="priority">
                    <option value="low">منخفضة</option>
                    <option value="medium" selected>متوسطة</option>
                    <option value="high">عالية</option>
                </select>
            </div>
            <div class="field">
                <label for="attachment">إرفاق صورة (اختياري)</label>
                <input type="file" id="attachment" name="attachment" accept="image/*" />
            </div>
            <button type="submit" name="open_ticket" class="btn btn-success">تسجيل الطلب</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:22px">
    <div class="card-head">
        <div><h2>طلباتي</h2><p class="sub" style="margin:0">إجمالي الطلبات المسجّلة: <?= count($userTickets) ?></p></div>
    </div>
    <?php if (empty($userTickets)): ?>
        <div class="alert alert-info" style="margin:0">لا توجد طلبات مسجّلة حتى الآن.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الرقم</th><th>عنوان الطلب</th><th>الفئة</th><th>الأولوية</th><th>الحالة</th><th>تاريخ التسجيل</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($userTickets as $ticket): ?>
                    <tr>
                        <td class="num">#<?= (int)$ticket['id'] ?></td>
                        <td><?= htmlspecialchars($ticket['title']) ?></td>
                        <td><?= htmlspecialchars($ticket['category_name']) ?></td>
                        <td><?= $priorityLabels[$ticket['priority']] ?></td>
                        <td><span class="badge badge-<?= $ticket['status'] ?>"><?= $statusLabels[$ticket['status']] ?></span></td>
                        <td class="num"><?= htmlspecialchars($ticket['created_at']) ?></td>
                        <td><a class="btn btn-ghost btn-sm" href="?ticket_id=<?= (int)$ticket['id'] ?>">عرض التفاصيل</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($selectedTicket): $ticket = $selectedTicket; ?>
<div class="card" style="margin-top:22px">
    <h2>تفاصيل الطلب #<?= (int)$ticket['id'] ?></h2>
    <div class="ticket-card st-<?= $ticket['status'] ?>" style="margin-top:14px">
        <div class="ticket-top">
            <span class="ticket-title"><?= htmlspecialchars($ticket['title']) ?></span>
            <span class="badge badge-<?= $ticket['status'] ?>"><?= $statusLabels[$ticket['status']] ?></span>
        </div>
        <div class="ticket-meta"><?= htmlspecialchars($ticket['category_name']) ?> · أولوية <?= $priorityLabels[$ticket['priority']] ?> · <?= htmlspecialchars($ticket['created_at']) ?></div>
        <div style="margin-top:10px"><?= nl2br(htmlspecialchars($ticket['description'])) ?></div>
        <?php if ($ticket['attachment']): ?>
            <div style="margin-top:10px"><img src="../<?= htmlspecialchars($ticket['attachment']) ?>" style="max-width:240px;border-radius:8px;border:1px solid var(--line)" /></div>
        <?php endif; ?>
        <?php if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed'): ?>
            <div class="alert alert-<?= $ticket['status'] === 'resolved' ? 'info' : 'success' ?>" style="margin:12px 0 0;padding:10px 14px">
                <strong>الحل المقدَّم:</strong> <?= htmlspecialchars($ticket['solution'] ?? '—') ?>
            </div>
        <?php endif; ?>

        <div class="ticket-actions">
            <?php if ($ticket['status'] === 'resolved'): ?>
                <form method="post"><?= csrfField() ?><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                    <button type="submit" name="confirm_close" class="btn btn-success btn-sm">اعتماد الحل وإغلاق الطلب</button>
                </form>
            <?php endif; ?>
            <?php if ($ticket['status'] === 'open'): ?>
                <button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('edit-box').open=true">تعديل بيانات الطلب</button>
                <form method="post" onsubmit="return confirm('تأكيد إلغاء الطلب؟');"><?= csrfField() ?><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                    <button type="submit" name="cancel_ticket" class="btn btn-ghost btn-sm">إلغاء الطلب</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($ticket['status'] === 'open'): ?>
    <details class="collapse-box" id="edit-box" style="margin-top:14px">
        <summary>تعديل عنوان الطلب ووصفه</summary>
        <div class="body">
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                <div class="field"><input name="edit_title" value="<?= htmlspecialchars($ticket['title']) ?>" required /></div>
                <div class="field"><textarea name="edit_description" rows="3" required><?= htmlspecialchars($ticket['description']) ?></textarea></div>
                <button type="submit" name="edit_ticket" class="btn btn-primary btn-sm">حفظ التعديل</button>
            </form>
        </div>
    </details>
    <?php endif; ?>

    <details class="collapse-box" style="margin-top:14px">
        <summary>سجل الإجراءات والملاحظات</summary>
        <div class="body">
            <div style="font-weight:700;font-size:.82rem;margin-bottom:10px">سجل الإجراءات</div>
            <?php foreach (getTicketHistory($pdo, (int)$ticket['id']) as $h): ?>
                <div class="ticket-meta">• <?= htmlspecialchars($h['action']) ?> — <?= htmlspecialchars($h['by_name']) ?> (<?= htmlspecialchars($h['created_at']) ?>)</div>
            <?php endforeach; ?>
            <div style="font-weight:700;font-size:.82rem;margin:16px 0 10px">الملاحظات</div>
            <?php $comments = getTicketComments($pdo, (int)$ticket['id']); if (empty($comments)): ?>
                <div class="ticket-meta">لا توجد ملاحظات مسجّلة.</div>
            <?php else: foreach ($comments as $c): ?>
                <div class="comment-item"><div class="meta"><?= htmlspecialchars($c['by_name']) ?> — <?= htmlspecialchars($c['created_at']) ?></div><?= htmlspecialchars($c['text']) ?></div>
            <?php endforeach; endif; ?>
            <?php if ($ticket['status'] !== 'cancelled'): ?>
            <form method="post" style="display:flex;gap:8px;margin-top:12px">
                <?= csrfField() ?>
                <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                <input type="text" name="comment_text" placeholder="إضافة ملاحظة..." required style="flex:1;padding:10px 13px;border-radius:8px;border:1px solid var(--line);font-family:inherit" />
                <button type="submit" name="add_comment" class="btn btn-primary btn-sm">إرسال</button>
            </form>
            <?php endif; ?>
        </div>
    </details>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
