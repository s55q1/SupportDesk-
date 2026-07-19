<?php
/**
 * database/seed.php
 * بيانات أولية: أدوار، مستخدمون، فئات، حلول، وتذاكر تجريبية.
 */
function seedDatabase(PDO $pdo): void
{
    $pdo->exec("INSERT INTO roles (name) VALUES ('admin'), ('engineer'), ('technician'), ('employee')");

    $roleId = [];
    foreach ($pdo->query('SELECT id, name FROM roles') as $row) {
        $roleId[$row['name']] = (int)$row['id'];
    }

    $users = [
        ['nasser.admin', 'admin', 'سعود الشمري'],
        ['talal.eng',    'engineer', 'طلال الحربي'],
        ['khaled.tech',  'technician', 'خالد الزهراني'],
        ['fahad.tech',   'technician', 'فهد العتيبي'],
        ['faisal.emp',   'employee', 'فيصل الشمري'],
    ];
    $insUser = $pdo->prepare('INSERT INTO users (username, password, full_name, role_id) VALUES (:u, :p, :f, :r)');
    foreach ($users as [$username, $role, $fullName]) {
        $insUser->execute([
            'u' => $username,
            'p' => password_hash('1234', PASSWORD_DEFAULT),
            'f' => $fullName,
            'r' => $roleId[$role],
        ]);
    }

    $categories = [
        ['شبكة وإنترنت', 'network'],
        ['أجهزة ومعدات', 'device'],
        ['برامج وأنظمة', 'app'],
        ['صلاحيات ووصول', 'key'],
    ];
    $insCat = $pdo->prepare('INSERT INTO categories (name, icon) VALUES (:n, :i)');
    foreach ($categories as [$name, $icon]) {
        $insCat->execute(['n' => $name, 'i' => $icon]);
    }

    $pdo->exec("INSERT INTO solutions (category_id, description, keywords) VALUES
        (1, 'أعد تشغيل الراوتر واختبر الكيبل، ثم افحص إعدادات DNS.', 'إنترنت راوتر شبكة'),
        (2, 'تأكد من توصيل كبل الطاقة وجرّب منفذ كهرباء آخر.', 'جهاز طاقة تشغيل'),
        (3, 'امسح الكاش وأعد تسجيل الدخول للنظام.', 'تسجيل دخول بطء نظام')");

    $empId = $pdo->query("SELECT id FROM users WHERE username = 'faisal.emp'")->fetchColumn();

    $insTicket = $pdo->prepare('INSERT INTO tickets
        (category_id, created_by, title, description, priority, status, created_at)
        VALUES (:cat, :by, :title, :desc, :prio, :status, :created)');
    $insTicket->execute([
        'cat' => 1, 'by' => $empId,
        'title' => 'لا يوجد إنترنت في الطابق الثاني',
        'desc' => 'انقطع الاتصال منذ الصباح في جميع مكاتب الطابق الثاني.',
        'prio' => 'high', 'status' => 'open', 'created' => date('Y-m-d H:i:s'),
    ]);
    $t1 = (int)$pdo->lastInsertId();
    $insTicket->execute([
        'cat' => 3, 'by' => $empId,
        'title' => 'النظام المحاسبي بطيء جداً',
        'desc' => 'الشاشات تستغرق أكثر من دقيقة للتحميل عند فتح التقارير.',
        'prio' => 'medium', 'status' => 'open', 'created' => date('Y-m-d H:i:s'),
    ]);
    $t2 = (int)$pdo->lastInsertId();

    $insHist = $pdo->prepare('INSERT INTO ticket_history (ticket_id, action, by_name) VALUES (:t, :a, :b)');
    $insHist->execute(['t' => $t1, 'a' => 'فتح التذكرة', 'b' => 'فيصل الشمري']);
    $insHist->execute(['t' => $t2, 'a' => 'فتح التذكرة', 'b' => 'فيصل الشمري']);
}
