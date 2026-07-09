<?php
/**
 * pages/technician.php
 * لوحة الفني/المهندس: الطلبات المسندة، الطابور العام، استلام، بدء العمل، تحديد الحل.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
requireAuth(['admin', 'engineer', 'technician']);

$pdo = getDatabaseConnection();
$current = currentUser();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $ticketId = (int)($_POST['ticket_id'] ?? 0);

    if (isset($_POST['claim_ticket'])) {
        if (assignTicket($pdo, $ticketId, (int)$current['id'], (int)$current['id'], $current['full_name'], $current['full_name'])) {
            $successMessage = 'تم استلام الطلب رقم #' . $ticketId . '.';
        } else {
            $errorMessage = 'تعذّر استلام الطلب.';
        }
    } elseif (isset($_POST['start_ticket'])) {
        if (startTicketWork($pdo, $ticketId, (int)$current['id'], $current['full_name'])) {
            $successMessage = 'تم تسجيل بدء العمل على الطلب رقم #' . $ticketId . '.';
        }
    } elseif (isset($_POST['resolve_ticket'])) {
        $solution = trim($_POST['final_solution'] ?? '');
        $saveArchive = isset($_POST['save_solution']);
        if ($solution === '') {
            $errorMessage = 'يرجى إدخال وصف الحل أولاً.';
        } elseif (resolveTicket($pdo, $ticketId, (int)$current['id'], $current['full_name'], $solution, $saveArchive)) {
            $successMessage = 'تم إرسال الحل، وهو الآن بانتظار اعتماد صاحب الطلب.';
        } else {
            $errorMessage = 'تعذّر تحديد الحل لهذا الطلب.';
        }
    }
}

$assignedTickets = getAssignedTickets($pdo, (int)$current['id']);
$openPool = getOpenTickets($pdo);
$selectedTicketId = (int)($_GET['ticket_id'] ?? 0);
$selectedTicket = $selectedTicketId ? getTicketById($pdo, $selectedTicketId) : null;
$suggestedSolutions = $selectedTicket ? getSuggestedSolutions($pdo, (int)$selectedTicket['category_id']) : [];

$statusLabels = ['open' => 'بانتظار التوجيه', 'assigned' => 'قيد المعالجة', 'resolved' => 'بانتظار اعتماد الحل', 'closed' => 'مغلقة', 'cancelled' => 'ملغاة'];
$roleName = $current['role'] === 'engineer' ? 'المهندس' : 'الفني';

$pageTitle = 'إدارة الطلبات المسندة';
$activeNav = 'technician';
$user = $current;
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>إدارة الطلبات المسندة</h1><p>مراجعة الطلبات المسندة إليك أو استلام طلبات من الطابور العام، ثم تحديد الحل المناسب.</p></div>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div class="stat-row">
    <div class="stat-tile tone-accent"><div class="l">الطلبات المسندة إليك</div><div class="n"><?= count($assignedTickets) ?></div></div>
    <div class="stat-tile tone-warning"><div class="l">الطابور العام</div><div class="n"><?= count($openPool) ?></div></div>
</div>

<div class="grid-weighted">
    <div class="card">
        <div class="card-head"><div><h2>الطلبات المسندة إليك</h2><p class="sub" style="margin:0"><?= count($assignedTickets) ?> طلب</p></div></div>
        <?php if (empty($assignedTickets)): ?>
            <div class="alert alert-info" style="margin:0">لا توجد طلبات مسندة إليك حالياً.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>الرقم</th><th>عنوان الطلب</th><th>الحالة</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($assignedTickets as $ticket): ?>
                        <tr>
                            <td class="num">#<?= (int)$ticket['id'] ?></td>
                            <td><?= htmlspecialchars($ticket['title']) ?><?php if (isTicketOverdue($ticket)): ?> <span class="badge badge-danger">متأخر</span><?php endif; ?></td>
                            <td><span class="badge badge-<?= $ticket['status'] ?>"><?= $statusLabels[$ticket['status']] ?></span></td>
                            <td style="white-space:nowrap">
                                <?php if (!$ticket['started'] && $ticket['status'] === 'assigned'): ?>
                                <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                                    <button class="btn btn-primary btn-sm" type="submit" name="start_ticket">بدء العمل</button>
                                </form>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="?ticket_id=<?= (int)$ticket['id'] ?>">التفاصيل</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-head"><div><h2>الطابور العام</h2><p class="sub" style="margin:0"><?= count($openPool) ?> طلب مفتوح</p></div></div>
        <?php if (empty($openPool)): ?>
            <div class="alert alert-info" style="margin:0">لا توجد طلبات مفتوحة بالطابور العام.</div>
        <?php else: foreach ($openPool as $ticket): ?>
            <div class="ticket-card st-open">
                <div class="ticket-top">
                    <span class="ticket-title">#<?= (int)$ticket['id'] ?> — <?= htmlspecialchars($ticket['title']) ?></span>
                    <span class="badge badge-open"><?= $statusLabels['open'] ?></span>
                </div>
                <div class="ticket-meta"><?= htmlspecialchars($ticket['category_name']) ?> · مقدَّم من <?= htmlspecialchars($ticket['created_by_name']) ?></div>
                <div class="ticket-actions">
                    <form method="post"><?= csrfField() ?><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
                        <button class="btn btn-primary btn-sm" type="submit" name="claim_ticket">استلام الطلب</button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="?ticket_id=<?= (int)$ticket['id'] ?>">التفاصيل</a>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php if ($selectedTicket): ?>
    <div class="card" style="margin-top:22px">
        <h2>تفاصيل الطلب #<?= (int)$selectedTicket['id'] ?></h2>
        <div class="ticket-card st-<?= $selectedTicket['status'] ?>" style="margin-top:14px">
            <div><strong>الفئة:</strong> <?= htmlspecialchars($selectedTicket['category_name']) ?></div>
            <div style="margin-top:6px"><strong>الوصف:</strong> <?= nl2br(htmlspecialchars($selectedTicket['description'])) ?></div>
            <?php if ($selectedTicket['attachment']): ?>
                <div style="margin-top:10px"><img src="../<?= htmlspecialchars($selectedTicket['attachment']) ?>" style="max-width:260px;border-radius:8px;border:1px solid var(--line)" /></div>
            <?php endif; ?>
            <div class="ticket-meta" style="margin-top:8px"><strong>مقدَّم من:</strong> <?= htmlspecialchars($selectedTicket['created_by_name']) ?> • <strong>الحالة:</strong> <?= $statusLabels[$selectedTicket['status']] ?></div>
        </div>

        <details class="collapse-box" style="margin-top:16px">
            <summary>سجل الإجراءات</summary>
            <div class="body">
                <?php foreach (getTicketHistory($pdo, (int)$selectedTicket['id']) as $h): ?>
                    <div class="ticket-meta">• <?= htmlspecialchars($h['action']) ?> — <?= htmlspecialchars($h['by_name']) ?> (<?= htmlspecialchars($h['created_at']) ?>)</div>
                <?php endforeach; ?>
            </div>
        </details>

        <h2 style="margin-top:20px;font-size:1rem">الحلول المرجعية لهذه الفئة</h2>
        <?php if (empty($suggestedSolutions)): ?>
            <div class="alert alert-info" style="margin-top:10px">لا توجد حلول مرجعية مسجّلة لهذه الفئة حتى الآن.</div>
        <?php else: foreach ($suggestedSolutions as $solution): ?>
            <div class="ticket-card st-closed" style="margin-top:10px">
                <div><?= htmlspecialchars($solution['description']) ?></div>
                <div class="ticket-meta">كلمات مفتاحية: <?= htmlspecialchars($solution['keywords']) ?></div>
            </div>
        <?php endforeach; endif; ?>

        <?php if ((int)$selectedTicket['assigned_to'] === (int)$current['id'] && $selectedTicket['status'] === 'assigned'): ?>
            <div class="ticket-card" style="margin-top:18px">
                <h3 style="margin-bottom:14px;font-size:.92rem">تحديد الحل النهائي</h3>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int)$selectedTicket['id'] ?>" />
                    <div class="field"><textarea name="final_solution" rows="4" placeholder="وصف الحل المطبَّق" required></textarea></div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;margin-bottom:14px">
                        <input type="checkbox" name="save_solution" value="1" checked /> حفظ الحل ضمن الحلول المرجعية
                    </label>
                    <button type="submit" name="resolve_ticket" class="btn btn-success">إرسال الحل لاعتماد صاحب الطلب</button>
                </form>
            </div>
        <?php elseif ($selectedTicket['status'] === 'resolved' || $selectedTicket['status'] === 'closed'): ?>
            <div class="alert alert-info" style="margin-top:16px"><strong>الحل المرسل:</strong> <?= htmlspecialchars($selectedTicket['solution'] ?? '—') ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
