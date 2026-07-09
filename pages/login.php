<?php
/**
 * pages/login.php
 * بوابة الدخول الموحّدة لنظام إدارة طلبات الدعم الفني.
 */
require_once __DIR__ . '/../includes/auth.php';
$pdo = getDatabaseConnection();
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!loginAttemptsAllowed()) {
        $errorMessage = 'تم إيقاف الدخول مؤقتاً بسبب تكرار المحاولات غير الصحيحة. يرجى المحاولة لاحقاً.';
    } else {
        $user = loginUser($pdo, $username, $password);
        if ($user) {
            $landingByRole = ['admin' => 'admin.php', 'engineer' => 'technician.php', 'technician' => 'technician.php', 'employee' => 'employee.php'];
            header('Location: ' . ($landingByRole[$user['role']] ?? 'employee.php'));
            exit;
        }
        $errorMessage = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}

$demoAccounts = [
    ['nasser.admin', 'مدير النظام'],
    ['talal.eng', 'مهندس دعم فني'],
    ['khaled.tech', 'فني دعم فني'],
    ['faisal.emp', 'موظف'],
];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تسجيل الدخول — نظام إدارة طلبات الدعم الفني</title>
    <link rel="stylesheet" href="../assets/css/app.css" />
    <style>
        .login-shell{min-height:100vh;display:grid;place-items:center;padding:24px;}
        .login-card{
            width:min(100%,880px);background:var(--surface);border-radius:18px;box-shadow:var(--shadow-md);
            display:grid;grid-template-columns:1fr 1fr;overflow:hidden;border:1px solid var(--line);
        }
        @media (max-width:760px){ .login-card{grid-template-columns:1fr;} }
        .login-info{background:#132420;color:#e6efec;padding:44px 38px;display:flex;flex-direction:column;justify-content:space-between;}
        .login-info .logo-box{background:#fff;border-radius:12px;padding:10px 14px;display:inline-flex;align-self:flex-start;}
        .login-info .logo-box img{height:42px;width:auto;display:block;}
        .login-info h1{font-size:1.5rem;margin:26px 0 10px;font-weight:700;}
        .login-info p{margin:0;color:#a9beb8;font-size:.9rem;line-height:1.8;}
        .login-info ul{list-style:none;margin:22px 0 0;padding:0;display:flex;flex-direction:column;gap:12px;}
        .login-info li{font-size:.85rem;color:#c3d3ce;display:flex;gap:10px;align-items:flex-start;}
        .login-info li .dot{width:6px;height:6px;border-radius:50%;background:var(--accent);margin-top:7px;flex:none;}
        .login-form-side{padding:48px 42px;display:flex;flex-direction:column;justify-content:center;}
        .login-form-side h2{font-size:1.25rem;margin-bottom:6px;font-weight:700;}
        .login-form-side .lead{color:var(--muted);font-size:.86rem;margin-bottom:22px;}
        .demo-table{margin-top:12px;font-size:.78rem;width:100%;border-collapse:collapse;}
        .demo-table th{text-align:right;padding:4px 0;color:var(--muted);font-weight:600;}
        .demo-table td{padding:4px 0;}
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-info">
            <div>
                <div class="logo-box"><img src="../assets/images/logo-hrsd-transparent.png" alt="شعار الموارد البشرية والتنمية الاجتماعية" /></div>
                <h1>نظام إدارة طلبات الدعم الفني</h1>
                <p>منصّة داخلية لتسجيل طلبات الدعم الفني ومتابعتها ومعالجتها بين الموظفين وفرق الدعم الفني.</p>
                <ul>
                    <li><span class="dot"></span>تسجيل الطلبات ومتابعة حالتها لحظياً</li>
                    <li><span class="dot"></span>إسناد الطلبات للفرق الفنية المختصة</li>
                    <li><span class="dot"></span>سجل كامل للإجراءات والقرارات</li>
                </ul>
            </div>
            <div style="font-size:.76rem;color:#7e928c">جميع الحقوق محفوظة — إدارة تقنية المعلومات</div>
        </div>
        <div class="login-form-side">
            <h2>تسجيل الدخول</h2>
            <p class="lead">يرجى إدخال بيانات حسابك الوظيفي للوصول إلى النظام.</p>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrfField() ?>
                <div class="field">
                    <label for="username">اسم المستخدم</label>
                    <input id="username" name="username" type="text" required autofocus />
                </div>
                <div class="field">
                    <label for="password">كلمة المرور</label>
                    <input id="password" name="password" type="password" required />
                </div>
                <button class="btn btn-primary btn-block" type="submit">تسجيل الدخول</button>
            </form>
            <details style="margin-top:22px;border-top:1px solid var(--line);padding-top:16px">
                <summary style="cursor:pointer;font-size:.78rem;color:var(--muted);font-weight:600">بيانات دخول للتجربة (بيئة تطوير فقط)</summary>
                <table class="demo-table">
                    <thead><tr><th>اسم المستخدم</th><th>الدور</th></tr></thead>
                    <tbody>
                        <?php foreach ($demoAccounts as [$uname, $role]): ?>
                            <tr class="demo-row" style="cursor:pointer" data-username="<?= htmlspecialchars($uname) ?>" title="اضغط للتعبئة التلقائية"><td><?= htmlspecialchars($uname) ?></td><td><?= htmlspecialchars($role) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="hint" style="margin-top:8px">كلمة المرور لكل الحسابات: 1234 — اضغط على أي صف للتعبئة التلقائية</div>
            </details>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.demo-row').forEach(function (row) {
    row.addEventListener('click', function () {
        document.getElementById('username').value = row.getAttribute('data-username');
        document.getElementById('password').value = '1234';
    });
});
</script>
</body>
</html>
