<?php
/**
 * pages/dashboard.php
 * صفحة رئيسية بعد تسجيل الدخول مع روابط حسب الدور.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAuth(['admin', 'engineer', 'technician', 'employee']);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>لوحة التحكم - نظام الدعم الفني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; background: #eef6ff; margin: 0; padding: 0; }
        .app-shell { min-height: 100vh; }
        .page { max-width: 1080px; margin: 32px auto; padding: 28px; background: #ffffff; border-radius: 22px; box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08); }
        .card-action { transition: transform .2s ease, box-shadow .2s ease; }
        .card-action:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12); }
        .card-action a { color: #1d4ed8; text-decoration: none; font-weight: 700; }
        .hero { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; }
        .hero h1 { margin: 0; font-size: clamp(2rem, 2.5vw, 2.75rem); }
        .hero p { margin: 0; color: #475569; }
    </style>
</head>
<body class="app-shell">
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-semibold" href="../index.php">نظام دعم فني</a>
    <div class="d-flex align-items-center">
      <a class="btn btn-outline-danger" href="logout.php">تسجيل خروج</a>
    </div>
  </div>
</nav>
<div class="page">
    <div class="hero mb-4">
        <div>
            <h1>مرحباً، <?= htmlspecialchars($user['full_name']) ?></h1>
            <p>لوحة تحكم مبسطة لإدارة التذاكر والدعم الفني.</p>
        </div>
        <div class="badge bg-primary text-white py-2 px-3 rounded-pill">دورك: <?= htmlspecialchars($user['role']) ?></div>
    </div>
    <div class="row g-4">
        <?php if ($user['role'] === 'admin'): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-action p-4 border-0 rounded-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="badge bg-info text-dark rounded-circle p-3">A</span>
                        <div>
                            <h5>إدارة النظام</h5>
                            <p class="mb-0 text-secondary">أنشئ مستخدمين، وتابع صلاحيات النظام.</p>
                        </div>
                    </div>
                    <div class="mt-3"><a href="admin.php">فتح الصفحة</a></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['admin', 'engineer'], true)): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-action p-4 border-0 rounded-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="badge bg-warning text-dark rounded-circle p-3">T</span>
                        <div>
                            <h5>لوحة الفني</h5>
                            <p class="mb-0 text-secondary">عرض التذاكر المفتوحة والمخصصة وإغلاقها.</p>
                        </div>
                    </div>
                    <div class="mt-3"><a href="technician.php">فتح الصفحة</a></div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card card-action p-4 border-0 rounded-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="badge bg-success text-white rounded-circle p-3">U</span>
                    <div>
                        <h5>واجهة الموظف</h5>
                        <p class="mb-0 text-secondary">فتح تذاكر، والعثور على حلول سريعة، ومتابعة الطلبات.</p>
                    </div>
                </div>
                <div class="mt-3"><a href="employee.php">فتح الصفحة</a></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card card-action p-4 border-0 rounded-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="badge bg-secondary text-white rounded-circle p-3">R</span>
                    <div>
                        <h5>تقارير</h5>
                        <p class="mb-0 text-secondary">راجع حالة التذاكر والإحصاءات الأساسية للنظام.</p>
                    </div>
                </div>
                <div class="mt-3"><a href="reports.php">فتح الصفحة</a></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
