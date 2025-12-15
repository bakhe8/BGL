-- =============================================================================
-- BGL Database Schema - قاعدة بيانات نظام إدارة خطابات الضمان
-- =============================================================================
-- 
-- آخر تحديث: 2025-12-13
-- النسخة: 1.0
-- نوع قاعدة البيانات: SQLite 3
-- 
-- ملاحظات هامة:
-- --------------
-- 1. هذا الملف يحتوي على تعريف كامل لجميع الجداول
-- 2. يمكن استخدامه لإنشاء قاعدة بيانات جديدة من الصفر
-- 3. Foreign Keys مفعّلة عبر: PRAGMA foreign_keys = ON;
-- 4. جميع الأعمدة الاختيارية محددة بوضوح بـ NULL
-- 
-- =============================================================================

-- تفعيل Foreign Keys (يجب تشغيله قبل أي عملية على القاعدة)
PRAGMA foreign_keys = ON;

-- =============================================================================
-- جداول الموردين (Suppliers)
-- =============================================================================

-- الجدول الرئيسي للموردين الرسميين
CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    official_name TEXT NOT NULL,                    -- الاسم الرسمي الكامل
    display_name TEXT NULL,                         -- اسم العرض (اختياري)
    normalized_name TEXT NOT NULL UNIQUE,           -- الاسم المطبع (فريد)
    supplier_normalized_key TEXT NOT NULL,          -- مفتاح البحث (بدون مسافات)
    vat_number TEXT NULL,                           -- الرقم الضريبي (اختياري)
    is_confirmed INTEGER DEFAULT 1,                 -- مؤكد/غير مؤكد
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- فهرس للبحث السريع
CREATE INDEX IF NOT EXISTS idx_suppliers_normalized 
ON suppliers(normalized_name);

CREATE INDEX IF NOT EXISTS idx_suppliers_key 
ON suppliers(supplier_normalized_key);

-- الأسماء البديلة للموردين (Aliases)
CREATE TABLE IF NOT EXISTS supplier_alternative_names (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,                   -- معرف المورد الرئيسي
    raw_name TEXT NOT NULL,                         -- الاسم البديل الخام
    normalized_raw_name TEXT NOT NULL UNIQUE,       -- الاسم البديل المطبع
    source TEXT DEFAULT 'manual',                   -- المصدر: manual, import, learning
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_supplier_alts_normalized 
ON supplier_alternative_names(normalized_raw_name);

CREATE INDEX IF NOT EXISTS idx_supplier_alts_supplier 
ON supplier_alternative_names(supplier_id);

-- الأسماء المتجاوزة يدوياً (Overrides)
-- لربط أسماء معينة بموردين معينين بشكل يدوي
CREATE TABLE IF NOT EXISTS supplier_overrides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    override_name TEXT NOT NULL,
    normalized_override TEXT,
    notes TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- التعلم من قرارات المستخدم (Supplier Learning)
-- يخزن آخر قرار للمستخدم لكل اسم مُطبّع
CREATE TABLE IF NOT EXISTS supplier_aliases_learning (
    learning_id INTEGER PRIMARY KEY AUTOINCREMENT,
    original_supplier_name TEXT NOT NULL,           -- الاسم الأصلي من الملف
    normalized_supplier_name TEXT NOT NULL UNIQUE,  -- الاسم المطبع (فريد)
    learning_status TEXT NOT NULL CHECK(learning_status IN ('supplier_alias','supplier_blocked')),
    linked_supplier_id INTEGER NOT NULL,            -- المورد المرتبط
    learning_source TEXT DEFAULT 'review',          -- المصدر: review, import
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (linked_supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_supplier_learning_norm 
ON supplier_aliases_learning(normalized_supplier_name);

-- =============================================================================
-- جداول البنوك (Banks)
-- =============================================================================

-- الجدول الرئيسي للبنوك
CREATE TABLE IF NOT EXISTS banks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    official_name TEXT NOT NULL,                    -- الاسم الرسمي بالعربي
    official_name_en TEXT NULL,                     -- الاسم الرسمي بالإنجليزي
    normalized_key TEXT NOT NULL UNIQUE,            -- مفتاح البحث المطبع (بدون مسافات)
    short_code TEXT NULL,                           -- الرمز المختصر (مثل: RJHI, SABB)
    swift_code TEXT NULL,                           -- رمز SWIFT (اختياري)
    department TEXT NULL,                           -- القسم (مثل: إدارة الضمانات)
    address_line_1 TEXT NULL,                       -- العنوان 1
    address_line_2 TEXT NULL,                       -- العنوان 2
    contact_email TEXT NULL,                        -- البريد الإلكتروني للتواصل
    is_confirmed INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_banks_normalized 
ON banks(normalized_key);

CREATE INDEX IF NOT EXISTS idx_banks_short 
ON banks(short_code);

-- التعلم من قرارات المستخدم (Bank Learning)
-- يخزن آخر قرار للمستخدم لكل اسم بنك
CREATE TABLE IF NOT EXISTS bank_aliases_learning (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    input_name TEXT NOT NULL,                       -- الاسم الخام
    normalized_input TEXT NOT NULL UNIQUE,          -- الاسم المطبع (فريد)
    status TEXT CHECK( status IN ('alias', 'blocked') ),
    bank_id INTEGER NULL,                           -- البنك المرتبط (NULL للحظر العام)
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bank_learning_norm 
ON bank_aliases_learning(normalized_input);

-- =============================================================================
-- جداول السجلات المستوردة (Imported Records)
-- =============================================================================

-- جلسات الاستيراد
CREATE TABLE IF NOT EXISTS import_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_type TEXT DEFAULT 'excel',               -- نوع المصدر
    filename TEXT NULL,                             -- اسم الملف المستورد
    records_count INTEGER DEFAULT 0,                -- عدد السجلات
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- السجلات المستوردة (خطابات الضمان)
CREATE TABLE IF NOT EXISTS imported_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,                    -- معرف الجلسة
    
    -- البيانات الخام من Excel
    raw_supplier_name TEXT NOT NULL,                -- اسم المورد (خام)
    raw_bank_name TEXT NOT NULL,                    -- اسم البنك (خام)
    guarantee_number TEXT NOT NULL,                 -- رقم الضمان
    contract_number TEXT NULL,                      -- رقم العقد
    contract_source TEXT NULL,                      -- مصدر العقد: contract, po
    amount REAL NULL,                               -- المبلغ
    expiry_date TEXT NULL,                          -- تاريخ الانتهاء
    issue_date TEXT NULL,                           -- تاريخ الإصدار
    type TEXT NULL,                                 -- نوع الضمان
    comment TEXT NULL,                              -- ملاحظات
    
    -- المطابقة التلقائية
    match_status TEXT DEFAULT 'needs_review' CHECK(match_status IN ('ready', 'needs_review')),
    supplier_id INTEGER NULL,                       -- المورد المطابق
    bank_id INTEGER NULL,                           -- البنك المطابق
    normalized_supplier TEXT NULL,                  -- اسم المورد المطبع
    normalized_bank TEXT NULL,                      -- اسم البنك المطبع
    
    -- للعرض (Display Freeze)
    supplier_display_name TEXT NULL,                -- اسم المورد المجمد للعرض
    bank_display TEXT NULL,                         -- اسم البنك المجمد للعرض
    
    -- التواريخ
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (session_id) REFERENCES import_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_records_session 
ON imported_records(session_id);

CREATE INDEX IF NOT EXISTS idx_records_supplier 
ON imported_records(supplier_id);

CREATE INDEX IF NOT EXISTS idx_records_bank 
ON imported_records(bank_id);

CREATE INDEX IF NOT EXISTS idx_records_status 
ON imported_records(match_status);

-- =============================================================================
-- سجلات التعلم التاريخية (Learning Logs)
-- =============================================================================

-- سجل التعلم للموردين (تاريخي - للتحليلات)
CREATE TABLE IF NOT EXISTS learning_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    raw_input TEXT NOT NULL,
    normalized_input TEXT NOT NULL,
    suggested_supplier_id INTEGER NULL,
    decision_result TEXT NULL,                      -- ready, needs_review, approved
    candidate_source TEXT NULL,                     -- import, review
    score REAL NULL,
    score_raw REAL NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (suggested_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- سجل التعلم للبنوك (تاريخي - للتحليلات)
CREATE TABLE IF NOT EXISTS bank_learning_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    raw_input TEXT NOT NULL,
    normalized_input TEXT NOT NULL,
    suggested_bank_id INTEGER NULL,
    decision_result TEXT NULL,
    candidate_source TEXT NULL,
    score REAL NULL,
    score_raw REAL NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (suggested_bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

-- =============================================================================
-- الإعدادات (Settings)
-- =============================================================================

CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- البيانات الأولية (Initial Data)
-- =============================================================================

-- إدراج الإعدادات الافتراضية
INSERT OR IGNORE INTO settings (setting_key, setting_value, description) VALUES
    ('MATCH_AUTO_THRESHOLD', '0.90', 'عتبة القبول التلقائي'),
    ('MATCH_REVIEW_THRESHOLD', '0.70', 'الحد الأدنى للظهور في القائمة'),
    ('MATCH_WEAK_THRESHOLD', '0.70', 'عتبة التطابق الضعيف'),
    ('CONFLICT_DELTA', '0.05', 'فرق الدرجات لاكتشاف التعارض'),
    ('WEIGHT_OFFICIAL', '1.0', 'وزن المطابقة الرسمية'),
    ('WEIGHT_ALT_CONFIRMED', '0.95', 'وزن الاسم البديل المؤكد'),
    ('WEIGHT_FUZZY', '0.85', 'وزن المطابقة الغامضة'),
    ('CANDIDATES_LIMIT', '20', 'الحد الأقصى للمرشحين');

-- =============================================================================
-- الملاحظات النهائية
-- =============================================================================

-- لاستخدام هذا الملف:
-- 1. sqlite3 storage/database/app.sqlite < storage/database/schema.sql
-- 2. أو من PHP: $pdo->exec(file_get_contents('storage/database/schema.sql'));

-- للنسخ الاحتياطي:
-- sqlite3 storage/database/app.sqlite .dump > backup.sql

-- لاستعادة النسخة الاحتياطية:
-- sqlite3 storage/database/app.sqlite < backup.sql
