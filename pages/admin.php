<?php
/**
 * pages/admin.php
 * لوحة المدير: إدارة المستخدمين، تعيين التذاكر، تتبّع الكل، سجل الأحداث.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
$userSession = currentUser();
requireAuth(['admin']);

$pdo = getDatabaseConnection();
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $roleName = trim($_POST['role'] ?? '');
        if ($username === '' || $password === '' || $fullName === '' || $roleName === '') {
            $errorMessage = 'يرجى تعبئة جميع الحقول.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,}$/', $username)) {
            $errorMessage = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل بدون مسافات.';
        } elseif (strlen($password) < 4) {
            $errorMessage = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل.';
        } elseif (findUserByUsername($pdo, $username)) {
            $errorMessage = 'اسم المستخدم موجود مسبقاً.';
        } else {
            createUserAccount($pdo, $username, $password, $fullName, $roleName);
            logAudit($pdo, 'user_created', (int)$userSession['id'], null, $username . ' (' . $roleName . ')');
            $successMessage = 'تم إنشاء المستخدم بنجاح.';
        }
    } elseif (isset($_POST['edit_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $role = trim($_POST['role'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $stmtCurRole = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
        $stmtCurRole->execute(['id' => $uid]);
        $currentRole = $stmtCurRole->fetchColumn();

        if ($uid === (int)$userSession['id'] && $role !== 'admin') {
            $errorMessage = 'لا يمكنك تغيير دور حسابك الحالي.';
        } elseif ($role === '') {
            $errorMessage = 'اختر دوراً صحيحاً.';
        } elseif ($currentRole === 'admin' && $role !== 'admin' && countAdmins($pdo) <= 1) {
            $errorMessage = 'لا يمكن تغيير دور آخر حساب مدير بالنظام.';
        } else {
            updateUserAccount($pdo, $uid, $role, $newPass ?: null);
            logAudit($pdo, 'user_updated', (int)$userSession['id'], null, 'user_id:' . $uid);
            $successMessage = 'تم حفظ التعديلات.';
        }
    } elseif (isset($_POST['delete_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $stmtDelRole = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
        $stmtDelRole->execute(['id' => $uid]);
        $delRole = $stmtDelRole->fetchColumn();
        if ($uid === (int)$userSession['id']) {
            $errorMessage = 'لا يمكنك حذف حسابك الحالي.';
        } elseif ($delRole === 'admin' && countAdmins($pdo) <= 1) {
            $errorMessage = 'لا يمكن حذف آخر حساب مدير بالنظام.';
        } else {
            deleteUserAccount($pdo, $uid);
            logAudit($pdo, 'user_deleted', (int)$userSession['id'], null, 'user_id:' . $uid);
            $successMessage = 'تم حذف المستخدم.';
        }
    } elseif (isset($_POST['assign_bulk'])) {
        $technicianId = (int)($_POST['technician_id'] ?? 0);
        $ticketIds = isset($_POST['ticket_ids']) && is_array($_POST['ticket_ids']) ? array_map('intval', $_POST['ticket_ids']) : [];
        if ($technicianId <= 0 || empty($ticketIds)) {
            $errorMessage = 'اختر تذكرة واحدة على الأقل وفنياً للتعيين.';
        } else {
            $stmtName = $pdo->prepare('SELECT full_name FROM users WHERE id = :id');
            $stmtName->execute(['id' => $technicianId]);
            $techName = $stmtName->fetchColumn() ?: '';
            $count = 0;
            foreach ($ticketIds as $tid) {
                if (assignTicket($pdo, $tid, $technicianId, (int)$userSession['id'], $userSession['full_name'], $techName)) {
                    $count++;
                }
            }
            $successMessage = "تم تعيين $count تذكرة إلى $techName.";
        }
    }
}

$users = getAllUsers($pdo);
$assignees = techniciansAndEngineers($pdo);
$openTickets = getOpenTickets($pdo);
$allTickets = getAllTicketsForTracking($pdo);
$closedTickets = array_values(array_filter($allTickets, fn($t) => $t['status'] === 'closed'));
$overdueTickets = array_values(array_filter($allTickets, 'isTicketOverdue'));
$auditLogs = getRecentAuditLogs($pdo, 30);

$roleLabels = ['admin' => 'مدير / IT', 'engineer' => 'مهندس', 'technician' => 'فني', 'employee' => 'موظف'];
$statusLabels = ['open' => 'بانتظار التوجيه', 'assigned' => 'قيد المعالجة', 'resolved' => 'بانتظار التأكيد', 'closed' => 'مغلقة', 'cancelled' => 'ملغاة'];
$adminCount = countAdmins($pdo);

$pageTitle = 'لوحة التحكم الرئيسية';
$activeNav = 'admin';
$user = $userSession;
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>لوحة التحكم الرئيسية</h1><p>إدارة حسابات المستخدمين، إسناد الطلبات، ومتابعة مؤشرات الأداء العامة للنظام.</p></div>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div class="stat-row">
    <div class="stat-tile tone-warning"><div class="l">طلبات بانتظار التوجيه</div><div class="n"><?= count($openTickets) ?></div></div>
    <div class="stat-tile tone-danger"><div class="l">طلبات متأخرة</div><div class="n"><?= count($overdueTickets) ?></div></div>
    <div class="stat-tile tone-success"><div class="l">طلبات مغلقة</div><div class="n"><?= count($closedTickets) ?></div></div>
    <div class="stat-tile tone-accent"><div class="l">إجمالي المستخدمين</div><div class="n"><?= count($users) ?></div></div>
</div>

<?php if (!empty($overdueTickets)): ?>
<div class="card" style="border-color:var(--danger)">
    <h2 style="color:var(--danger)">تنبيه: طلبات متأخرة عن الوقت المستهدف</h2>
    <p class="sub"><?= count($overdueTickets) ?> طلب يتجاوز مهلة المعالجة المحددة حسب الأولوية</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الرقم</th><th>العنوان</th><th>المسؤول</th><th>تاريخ الاستحقاق</th></tr></thead>
            <tbody>
            <?php foreach ($overdueTickets as $ticket): ?>
                <tr>
                    <td class="num">#<?= (int)$ticket['id'] ?></td>
                    <td><?= htmlspecialchars($ticket['title']) ?></td>
                    <td><?= htmlspecialchars($ticket['assigned_to_name'] ?? '—') ?></td>
                    <td class="num"><?= htmlspecialchars($ticket['due_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="grid-weighted" style="margin-top:22px">
    <div class="card">
        <div class="card-head"><div><h2>سجل المستخدمين</h2><p class="sub" style="margin:0"><?= count($users) ?> حساب مسجّل</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): $isSelf = (int)$u['id'] === (int)$userSession['id']; $isLastAdmin = $u['role'] === 'admin' && $adminCount <= 1; ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td class="num"><?= htmlspecialchars($u['username']) ?></td>
                        <td><span class="badge badge-neutral"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
                        <td style="white-space:nowrap">
                            <button class="btn btn-ghost btn-sm" type="button" onclick="var d=document.getElementById('edit-user-<?= (int)$u['id'] ?>');d.open=!d.open;">تعديل</button>
                            <?php if (!$isSelf && !$isLastAdmin): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('تأكيد حذف هذا المستخدم؟');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>" />
                                    <button class="btn btn-ghost btn-sm" type="submit" name="delete_user">حذف</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><td colspan="4" style="padding:0;border:none">
                        <details class="collapse-box" id="edit-user-<?= (int)$u['id'] ?>">
                            <summary style="display:none"></summary>
                            <form method="post" class="body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>" />
                                <div class="field" style="margin-bottom:0">
                                    <label>الدور</label>
                                    <select name="role" <?= $isLastAdmin ? 'disabled' : '' ?>>
                                        <?php foreach (['employee','technician','engineer','admin'] as $r): ?>
                                            <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $roleLabels[$r] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field" style="margin-bottom:0">
                                    <label>كلمة مرور جديدة (اختياري)</label>
                                    <input type="password" name="new_password" placeholder="اتركه فارغاً لعدم التغيير" />
                                </div>
                                <button class="btn btn-success btn-sm" type="submit" name="edit_user">حفظ التعديلات</button>
                            </form>
                        </details>
                    </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>إنشاء حساب مستخدم</h2>
        <p class="sub">تسجيل حساب جديد لموظف، فني، مهندس، أو مدير نظام.</p>
        <form method="post">
            <?= csrfField() ?>
            <div class="field"><label>اسم المستخدم</label><input name="username" required /></div>
            <div class="field"><label>كلمة المرور</label><input type="password" name="password" required /></div>
            <div class="field"><label>الاسم الكامل</label><input name="full_name" required /></div>
            <div class="field">
                <label>الصلاحية</label>
                <select name="role" required>
                    <option value="">— اختر —</option>
                    <option value="employee">موظف</option>
                    <option value="technician">فني دعم فني</option>
                    <option value="engineer">مهندس دعم فني</option>
                    <option value="admin">مدير نظام</option>
                </select>
            </div>
            <button type="submit" name="create_user" class="btn btn-primary btn-block">إنشاء الحساب</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head"><div><h2>إسناد الطلبات المفتوحة</h2><p class="sub" style="margin:0">تحديد الطلبات المراد إسنادها إلى فني أو مهندس دعم فني</p></div></div>
    <?php if (empty($openTickets)): ?>
        <div class="alert alert-info" style="margin:0">لا توجد طلبات مفتوحة بانتظار الإسناد.</div>
    <?php else: ?>
        <form method="post">
            <?= csrfField() ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th style="width:36px"></th><th>الرقم</th><th>عنوان الطلب</th><th>الفئة</th><th>مقدَّم من</th></tr></thead>
                    <tbody>
                    <?php foreach ($openTickets as $ticket): ?>
                        <tr>
                            <td><input type="checkbox" name="ticket_ids[]" value="<?= (int)$ticket['id'] ?>" /></td>
                            <td class="num">#<?= (int)$ticket['id'] ?></td>
                            <td><?= htmlspecialchars($ticket['title']) ?></td>
                            <td><?= htmlspecialchars($ticket['category_name']) ?></td>
                            <td><?= htmlspecialchars($ticket['created_by_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:18px">
                <div class="field" style="flex:1;min-width:240px;margin-bottom:0">
                    <label>إسناد إلى</label>
                    <select name="technician_id" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($assignees as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['full_name']) ?> — <?= $roleLabels[$a['role']] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-success" type="submit" name="assign_bulk">إسناد الطلبات المحددة</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><div><h2>سجل جميع الطلبات</h2><p class="sub" style="margin:0"><?= count($allTickets) ?> طلب إجمالاً</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الرقم</th><th>عنوان الطلب</th><th>الحالة</th><th>المسؤول</th></tr></thead>
            <tbody>
            <?php foreach ($allTickets as $ticket): ?>
                <tr>
                    <td class="num">#<?= (int)$ticket['id'] ?></td>
                    <td><?= htmlspecialchars($ticket['title']) ?></td>
                    <td><span class="badge badge-<?= $ticket['status'] ?>"><?= $statusLabels[$ticket['status']] ?></span> <?php if (isTicketOverdue($ticket)): ?><span class="badge badge-danger">متأخر</span><?php endif; ?></td>
                    <td><?= htmlspecialchars($ticket['assigned_to_name'] ?? 'غير مسند') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>سجل الأحداث</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>التاريخ والوقت</th><th>الحدث</th><th>التفاصيل</th></tr></thead>
            <tbody>
            <?php if (empty($auditLogs)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--muted)">لا يوجد نشاط مسجّل بعد</td></tr>
            <?php else: foreach ($auditLogs as $log): ?>
                <tr><td class="num"><?= htmlspecialchars($log['created_at']) ?></td><td><?= htmlspecialchars($log['action']) ?></td><td><?= htmlspecialchars($log['details']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
