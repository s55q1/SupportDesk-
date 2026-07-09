-- database.sql
-- قاعدة بيانات نظام دعم فني احترافي لمشروع تخرج

CREATE DATABASE IF NOT EXISTS helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE helpdesk;

-- جدول الفئات الرئيسية للمشاكل
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    CREATE DATABASE IF NOT EXISTS helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    USE helpdesk;

    -- جدول الفئات الرئيسية للمشاكل
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- جدول الأدوار (لمزيد من الوضوح والمرونة)
    CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- جدول المستخدمين وصلاحياتهم
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(120) NOT NULL,
        role_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- جدول التذاكر المفتوحة والمغلقة
    CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        created_by INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('open', 'assigned', 'in_progress', 'closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        closed_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- جدول تعيين التذاكر (من يقوم بالتعيين ولمن ومتى)
    CREATE TABLE IF NOT EXISTS ticket_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        assigned_to INT NOT NULL,
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- جدول الحلول المسجلة والمقترحة
    CREATE TABLE IF NOT EXISTS solutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        description TEXT NOT NULL,
        keywords VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- بيانات تجريبية أولية
    INSERT INTO categories (name) VALUES
    ('جهاز لا يشغل'),
    ('بطء النظام'),
    ('عدم الاتصال بالشبكة'),
    ('طباعة لا تعمل'),
    ('شاشة زرقاء');

    INSERT INTO roles (name) VALUES ('admin'), ('engineer'), ('technician'), ('employee');

    -- حسابات اختبارية (كلمات المرور مخزنة على شكل SHA-256)
    INSERT INTO users (username, password, full_name, role_id) VALUES
    ( 'admin',    '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'مدير النظام', (SELECT id FROM roles WHERE name = 'admin')),
    ( 'engineer', '80ca306ac6e68366dd0a26125c9647e0c61fac6668cec6016f5fe30fb12e99bd', 'مهندس الدعم', (SELECT id FROM roles WHERE name = 'engineer')),
    ( 'technician','3ac40463b419a7de590185c7121f0bfbe411d6168699e8014f521b050b1d6653','الفني الميداني', (SELECT id FROM roles WHERE name = 'technician')),
    ( 'employee', '5b2f8e27e2e5b4081c03ce70b288c87bd1263140cbd1bd9ae078123509b7caff', 'موظف النظام', (SELECT id FROM roles WHERE name = 'employee'));

    INSERT INTO solutions (category_id, description, keywords) VALUES
    (1, 'تحقق من توصيل كابل الطاقة وتأكد من مصدر الطاقة. افصل جميع الملحقات ثم حاول التشغيل.', 'طاقة كابل تشغيل'),
    (2, 'افحص إدارة المهام لإغلاق العمليات غير الضرورية، وتفقد المساحة الحرة على القرص.', 'بطيء ذاكرة CPU قرص'),
    (3, 'تأكد من إعدادات الواي فاي، جرب إعادة تهيئة الشبكة، وتحقق من إعدادات DNS.', 'شبكة واي فاي DNS اتصال'),
    (4, 'تأكد من وجود ورق وحبر، أعد تثبيت تعريف الطابعة إن لزم.', 'طابعة ورق حبر تعريف'),
    (5, 'اجمع معلومات الخطأ وانظر سجلات النظام، ثم حدث التعريفات والويندوز.', 'شاشة زرقاء خطأ تعريف');

    -- فهرس FULLTEXT لتحسين البحث عن الحلول (إن دعمته نسختك من MySQL)
    ALTER TABLE solutions ADD FULLTEXT KEY ft_solution_description_keywords (description, keywords);

    -- إضافة حقل البريد للمستخدمين (لقب استقبال الإشعارات)
    ALTER TABLE users ADD COLUMN email VARCHAR(200) NULL DEFAULT NULL;

    -- جدول سجل التدقيق لتتبّع الأفعال المهمة
    CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        ticket_id INT NULL,
        action VARCHAR(120) NOT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
