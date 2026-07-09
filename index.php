<?php
/**
 * index.php
 * صفحة البداية للمشروع مع روابط لواجهة الموظف ولوحة الفني.
 */
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>نظام دعم فني - مشروع تخرج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; text-align: center; background: #f4f7fb; margin: 0; padding: 0; }
        .hero { max-width: 700px; margin: 70px auto; padding: 24px; background: #fff; border-radius: 14px; box-shadow: 0 0 20px rgba(0,0,0,.08); }
        h1 { margin-top: 0; }
        .links { display: grid; gap: 16px; margin-top: 24px; }
        a.cta { display: block; padding: 14px 18px; border-radius: 10px; background: #3498db; color: #fff; text-decoration: none; font-size: 18px; }
        a.cta:hover { background: #2c81c9; }
        p { color: #555; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">نظام دعم فني</a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="pages/login.php">تسجيل دخول</a></li>
            </ul>
        </div>
    </div>
</nav>
<main class="hero">
        <h1>نظام دعم فني - مشروع التخرج</h1>
        <p>مرحبا بك في نسخة المشروع البسيطة والمقسمة إلى ملفات مرتبة. اختر الواجهة المناسبة للاستمرار.</p>
        <div class="links">
                <a class="cta" href="pages/login.php">تسجيل دخول</a>
                <a class="cta" href="pages/login.php">فتح النظام الكامل</a>
        </div>
        <p style="margin-top:20px; color:#555;">استخدم حساب المدير أو المهندس أو الفني أو الموظف لتسجيل الدخول.</p>
</main>
</body>
</html>
