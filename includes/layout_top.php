<?php
/**
 * includes/layout_top.php
 * رأس مشترك: يفتح <html>...<body> والقشرة والشريط الجانبي.
 * يتوقع تعريف: $pageTitle, $activeNav, $user (من currentUser()) قبل الاستدعاء.
 */
$navByRole = [
    'admin' => [
        ['id' => 'admin', 'href' => 'admin.php', 'label' => 'لوحة التحكم الرئيسية'],
        ['id' => 'tasks', 'href' => 'tasks.php', 'label' => 'المهام بين المستخدمين'],
        ['id' => 'reports', 'href' => 'reports.php', 'label' => 'التقارير والإحصاءات'],
    ],
    'engineer' => [
        ['id' => 'technician', 'href' => 'technician.php', 'label' => 'إدارة الطلبات المسندة'],
        ['id' => 'tasks', 'href' => 'tasks.php', 'label' => 'المهام بين المستخدمين'],
    ],
    'technician' => [
        ['id' => 'technician', 'href' => 'technician.php', 'label' => 'إدارة الطلبات المسندة'],
        ['id' => 'tasks', 'href' => 'tasks.php', 'label' => 'المهام بين المستخدمين'],
    ],
    'employee' => [
        ['id' => 'employee', 'href' => 'employee.php', 'label' => 'طلبات الدعم الفني'],
        ['id' => 'tasks', 'href' => 'tasks.php', 'label' => 'المهام بين المستخدمين'],
    ],
];
$roleLabelsShared = ['admin' => 'مدير النظام', 'engineer' => 'مهندس دعم فني', 'technician' => 'فني دعم فني', 'employee' => 'موظف'];
$navItems = $navByRole[$user['role']] ?? [];
$initialsOf = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
};
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?> — نظام إدارة طلبات الدعم الفني</title>
    <link rel="stylesheet" href="../assets/css/app.css" />
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="../assets/images/logo-hrsd-transparent.png" alt="شعار الموارد البشرية والتنمية الاجتماعية" />
            <span>نظام إدارة الدعم الفني</span>
        </div>
        <div class="nav-section-label">أقسام النظام</div>
        <?php foreach ($navItems as $item): ?>
            <a class="nav-item <?= $activeNav === $item['id'] ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                <span class="dot"></span><span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <div class="sidebar-foot">
            <div class="who-card">
                <div class="avatar"><?= htmlspecialchars($initialsOf($user['full_name'])) ?></div>
                <div>
                    <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="role"><?= htmlspecialchars($roleLabelsShared[$user['role']] ?? $user['role']) ?></div>
                </div>
            </div>
            <a class="btn btn-ghost btn-sm btn-block" href="logout.php" style="color:#fff;border-color:rgba(255,255,255,.16)">تسجيل الخروج</a>
        </div>
    </aside>
    <div class="main">
        <div class="topbar">
            <div class="crumb"><b><?= htmlspecialchars($roleLabelsShared[$user['role']] ?? '') ?></b> / <?= htmlspecialchars($pageTitle) ?></div>
        </div>
        <div class="wrap">
