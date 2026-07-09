<?php
/**
 * pages/reports.php
 * تقارير وإحصاءات عامة عن حالة الطلبات.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
requireAuth(['admin']);

$pdo = getDatabaseConnection();
$user = currentUser();

$totalTickets = (int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
$closedTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'closed'")->fetchColumn();
$categoryCounts = $pdo->query('SELECT c.name, COUNT(t.id) AS ticket_count FROM categories c LEFT JOIN tickets t ON t.category_id = c.id GROUP BY c.id ORDER BY ticket_count DESC')->fetchAll();

$pageTitle = 'التقارير والإحصاءات';
$activeNav = 'reports';
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="page-head">
    <div><h1>التقارير والإحصاءات</h1><p>نظرة عامة على حجم الطلبات وتوزيعها حسب الفئة.</p></div>
</div>

<div class="stat-row">
    <div class="stat-tile tone-accent"><div class="l">إجمالي الطلبات</div><div class="n"><?= $totalTickets ?></div></div>
    <div class="stat-tile tone-warning"><div class="l">طلبات مفتوحة</div><div class="n"><?= $openTickets ?></div></div>
    <div class="stat-tile tone-success"><div class="l">طلبات مغلقة</div><div class="n"><?= $closedTickets ?></div></div>
</div>

<div class="card">
    <h2>توزيع الطلبات حسب الفئة</h2>
    <p class="sub">عدد الطلبات المسجّلة لكل فئة عطل منذ إنشاء النظام.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الفئة</th><th>عدد الطلبات</th></tr></thead>
            <tbody>
            <?php foreach ($categoryCounts as $row): ?>
                <tr><td><?= htmlspecialchars($row['name']) ?></td><td class="num"><?= (int)$row['ticket_count'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
