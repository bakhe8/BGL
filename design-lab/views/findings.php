<?php
$findings = [
    'Chronos (Pro)' => [
        'type' => 'pro',
        'pros' => ['وضوح التايم لاين', 'التفريق الجيد بين الإجراءات'],
        'cons' => ['القائمة الرئيسية غير مفهومة', 'المعاينة لا تمثل مقاس A4', 'الهيدر الموحد يترك مساحات فارغة', 'كثرة الألوان والزوايا المائلة توحي بعدم الجدية']
    ],
    'Unified Practical' => [
        'type' => 'hybrid',
        'pros' => ['سهولة الوصول للمعلومة (Unified Layout)', 'وضوح بصري (Clean UI)'],
        'cons' => []
    ],
    'Improved Current' => [
        'type' => 'ref',
        'pros' => ['نظافة التصميم', 'وضوح العناصر'],
        'cons' => ['كثرة الاطارات داخل بعضها البعض']
    ],
    'Focused Workflow' => [
        'type' => 'exp',
        'pros' => [],
        'cons' => ['عدم استغلال المساحات بشكل كافي']
    ],
    'Timeline Action' => [
        'type' => 'exp',
        'pros' => [],
        'cons' => ['الألوان الغامقة غير مرغوبة', 'استخدام سيء للألوان']
    ],
    'Unified Workflow' => [
        'type' => 'ref',
        'pros' => ['معلومات المورد والمقترحات واضحة', 'تخطيط الشاشة (يمين قائمة / يسار تايم لاين)', 'حدة التمايز بين المكونات (بدون فراغات)'],
        'cons' => ['وجود قائمة رئيسية غير مرغوب فيها']
    ],
    'Unified Workflow Light' => [
        'type' => 'ref',
        'pros' => ['أفضل كرت للبيانات الأساسية', 'احتواء الكرت على كامل معلومات الضمان'],
        'cons' => []
    ],
    'Unified Workflow Dark' => [
        'type' => 'archived',
        'pros' => ['فكرة زر المعاينة', 'طريقة عرض المعاينة (جميلة)', 'الوضوح التام للنصوص والأزرار'],
        'cons' => ['استخدام الوضع الداكن (Dark Mode)', 'القائمة الرئيسية']
    ],
    'Integrated View' => [
        'type' => 'ref',
        'pros' => ['المرجع الافضل في التطبيق العملي الوظيفي البحت', 'إظهار البرنامج بالشكل الصحيح'],
        'cons' => ['وجود القائمة الرئيسية', 'تشتت بسبب وجود فراغات غير مستخدمة']
    ],
    'Clean UI' => [
        'type' => 'ref',
        'pros' => ['فصل جزء المعاينة عن العمل', 'بساطة لوحة البيانات', 'توحيد مظهر الكروت الداخلية', 'تصميم كرت المعاينة وأدواته'],
        'cons' => []
    ],
    'Timeline View' => [
        'type' => 'archived',
        'pros' => ['إجراء العمليات داخل التايم لاين مباشرة (فكرة)'],
        'cons' => ['تصميم الواجهة عموماً سيء', 'الألوان الغامقة غير مرغوبة']
    ],
    'AI-First' => [
        'type' => 'rejected',
        'pros' => [],
        'cons' => ['تم رفض فكرة الاعتماد الكامل على AI', 'التجربة غير مقبولة للمستخدم']
    ]
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتائج المختبر - Design Findings</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 32px;
            direction: rtl;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 40px; text-align: center; }
        .title { font-size: 32px; font-weight: 800; margin-bottom: 8px; color: #0f172a; }
        .subtitle { color: #64748b; font-size: 16px; }
        
        .findings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .finding-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafafa;
        }
        
        .exp-title { font-size: 18px; font-weight: 700; color: #334155; }
        .exp-type { font-size: 12px; color: #94a3b8; margin-top: 4px; display: inline-block; }
        
        .card-body { padding: 20px; flex: 1; }
        
        .list-group { margin-bottom: 16px; }
        .list-group:last-child { margin-bottom: 0; }
        
        .list-title { 
            font-size: 12px; font-weight: 800; margin-bottom: 8px; 
            display: flex; align-items: center; gap: 6px;
        }
        .list-title.pros { color: #16a34a; }
        .list-title.cons { color: #dc2626; }
        
        .list-items { list-style: none; padding: 0; margin: 0; }
        .list-items li {
            position: relative;
            padding-right: 20px;
            margin-bottom: 6px;
            font-size: 13px;
            line-height: 1.5;
            color: #475569;
        }
        .list-items li::before {
            content: '';
            position: absolute;
            right: 0;
            top: 8px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .pros .list-items li::before { background: #86efac; }
        .cons .list-items li::before { background: #fca5a5; }

        .back-link {
            display: inline-block;
            margin-bottom: 32px;
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
        }
        .back-link:hover { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/lab" class="back-link">← العودة للمختبر</a>
        
        <header class="header">
            <h1 class="title">مخلص نتائج التجارب</h1>
            <p class="subtitle">تحليل المميزات والعيوب لكل تجربة في المختبر</p>
        </header>

        <div class="findings-grid">
            <?php foreach($findings as $name => $data): ?>
            <div class="finding-card">
                <div class="card-header">
                    <div class="exp-title"><?= $name ?></div>
                </div>
                <div class="card-body">
                    <?php if(!empty($data['pros'])): ?>
                    <div class="list-group pros">
                        <div class="list-title pros">👍 مميزات</div>
                        <ul class="list-items">
                            <?php foreach($data['pros'] as $item): ?>
                            <li><?= $item ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($data['cons'])): ?>
                    <div class="list-group cons">
                        <div class="list-title cons">👎 ملاحظات</div>
                        <ul class="list-items">
                            <?php foreach($data['cons'] as $item): ?>
                            <li><?= $item ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
