<?php
/**
 * Design Lab - Main Index
 * =======================
 * 
 * دليل شامل لجميع التجارب التصميمية في المختبر
 * مع المواصفات الكاملة والتشابهات والفروقات والتفضيلات الشخصية
 */

$experiments = [
    [
        'id' => 'experiment-ultimate-enhanced',
        'name' => 'Ultimate Unified Interface - Enhanced',
        'status' => 'قيد التطوير',
        'priority' => 'عالية جداً',
        'description' => 'النسخة المحسّنة النهائية التي تجمع أفضل الميزات من جميع التجارب',
        'features' => [
            'Top bar عام مع الإحصائيات والإعدادات فقط',
            'Context bar مع معلومات السجل',
            'توزيع ثلاثي: Timeline (يمين) + Decision Panel (وسط) + Sidebar (يسار)',
            'Progress bar في أعلى Sidebar',
            'Footer بعرض كامل مع زر "المزيد" وأزرار التنقل',
            'المستندات والملاحظات في كروت منفصلة',
            'معاينة inline في المنتصف',
            'محتوى form-section من improved-current'
        ],
        'preferences' => [
            '✅ استخدام محتوى improved-current (المورد + البنك في قسم واحد)',
            '✅ Footer منفصل بعرض كامل',
            '✅ Progress bar في Sidebar',
            '✅ المستندات والملاحظات في كروت',
            '⏳ Timeline من unified-workflow (قيد التنفيذ)'
        ],
        'color' => '#3b82f6'
    ],
    [
        'id' => 'improved-current',
        'name' => 'التطوير التدريجي (Improved Current)',
        'status' => 'مكتمل',
        'priority' => 'مرجعية',
        'description' => 'تحسين الواجهة الحالية بدلاً من إعادة تصميم كاملة',
        'features' => [
            'Top bar عام',
            'Context bar مع معلومات السجل والتقدم',
            'توزيع ثلاثي: Timeline (يمين) + Main (وسط) + Attachments (يسار)',
            'Progress bar في Context bar',
            'Timeline تفاعلي مع أحداث قابلة للنقر',
            'Chips مع مصدر البيانات (Excel / استخدمته X مرة)',
            'معاينة A4 دقيقة في modal',
            'Action bar في أسفل Main panel'
        ],
        'preferences' => [
            '✅ محتوى form-section ممتاز (تم نسخه)',
            '✅ Chips مع المصادر واضحة',
            '✅ Select dropdown للبنك',
            '⚠️ Progress bar في Context bar (تم نقله إلى Sidebar)',
            '⚠️ Action bar داخل panel (تم تحويله إلى Footer)'
        ],
        'color' => '#10b981'
    ],
    [
        'id' => 'unified-workflow',
        'name' => 'Unified Workflow',
        'status' => 'مكتمل',
        'priority' => 'مرجعية',
        'description' => 'تصميم نظيف وعملي مع رؤية شاملة',
        'features' => [
            'Sidebar أيمن داكن مع إحصائيات وقائمة انتظار',
            'Top bar مع عنوان السجل وأزرار',
            'Decision card في الوسط مع header وbody وfooter',
            'Timeline على اليسار بتصميم حاد ونظيف',
            'معاينة inline تحت البيانات',
            'خلفية بيضاء نظيفة',
            'Timeline مع أيقونات ونقاط ملونة'
        ],
        'preferences' => [
            '✅ Timeline حاد وعملي ومنظم (تم اختياره للنسخ)',
            '✅ تصميم نظيف وبسيط',
            '✅ معاينة inline',
            '⚠️ Sidebar داكن (غير مفضل)',
            '⚠️ توزيع مختلف عن المطلوب'
        ],
        'color' => '#8b5cf6'
    ],
    [
        'id' => 'experiment-ultimate-v2',
        'name' => 'Ultimate Unified Interface V2',
        'status' => 'مكتمل',
        'priority' => 'متوسطة',
        'description' => 'نسخة سابقة من Ultimate Enhanced',
        'features' => [
            'Top bar + Context bar',
            'توزيع ثلاثي',
            'Timeline على اليمين',
            'Progress bar في Context bar',
            'Action bar داخل Decision panel'
        ],
        'preferences' => [
            '⚠️ تم تطويره إلى experiment-ultimate-enhanced',
            '⚠️ بنية قديمة'
        ],
        'color' => '#64748b'
    ],
    [
        'id' => 'experiment-ultimate',
        'name' => 'Ultimate Unified Interface V1',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'النسخة الأولى من التصميم الموحد',
        'features' => [
            'توزيع ثلاثي أساسي',
            'Timeline على اليمين',
            'Decision panel في الوسط',
            'Sidebar على اليسار'
        ],
        'preferences' => [
            '⚠️ نسخة أولية تم تطويرها',
            '⚠️ استخدم V2 أو Enhanced بدلاً منها'
        ],
        'color' => '#94a3b8'
    ],
    [
        'id' => 'unified-workflow-light',
        'name' => 'Unified Workflow - Light',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'نسخة فاتحة من Unified Workflow',
        'features' => [
            'نفس بنية unified-workflow',
            'ألوان فاتحة',
            'Sidebar فاتح'
        ],
        'preferences' => [
            '✅ ألوان فاتحة أفضل من الداكنة',
            '⚠️ تم دمجها في unified-workflow'
        ],
        'color' => '#f1f5f9'
    ],
    [
        'id' => 'unified-workflow-dark',
        'name' => 'Unified Workflow - Dark',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'نسخة داكنة من Unified Workflow',
        'features' => [
            'نفس بنية unified-workflow',
            'ألوان داكنة',
            'Sidebar داكن'
        ],
        'preferences' => [
            '❌ الألوان الداكنة غير مفضلة',
            '⚠️ تم دمجها في unified-workflow'
        ],
        'color' => '#1e293b'
    ],
    [
        'id' => 'unified-practical',
        'name' => 'Unified Practical',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'تصميم عملي مبسط',
        'features' => [
            'تركيز على العملية',
            'تصميم مبسط',
            'واجهة مباشرة'
        ],
        'preferences' => [
            '⚠️ تصميم أساسي جداً',
            '⚠️ تم تطويره في نسخ أحدث'
        ],
        'color' => '#6b7280'
    ],
    [
        'id' => 'integrated-view',
        'name' => 'Integrated View',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'عرض متكامل مع Timeline',
        'features' => [
            'توزيع ثلاثي',
            'Timeline متكامل',
            'عرض شامل'
        ],
        'preferences' => [
            '⚠️ تم دمج أفكاره في التصاميم الأحدث'
        ],
        'color' => '#059669'
    ],
    [
        'id' => 'focused-workflow',
        'name' => 'Focused Workflow',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'سير عمل مركّز',
        'features' => [
            'تركيز على المهمة الحالية',
            'تقليل المشتتات',
            'واجهة نظيفة'
        ],
        'preferences' => [
            '⚠️ مفهوم جيد لكن تم دمجه في تصاميم أخرى'
        ],
        'color' => '#0891b2'
    ],
    [
        'id' => 'timeline-pro',
        'name' => 'Timeline Pro',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'Timeline احترافي مع تأثيرات بصرية',
        'features' => [
            'Timeline مع تأثيرات متقدمة',
            'رسوم متحركة',
            'تصميم احترافي'
        ],
        'preferences' => [
            '⚠️ تأثيرات كثيرة قد تكون مشتتة',
            '⚠️ Timeline من unified-workflow أبسط وأفضل'
        ],
        'color' => '#7c3aed'
    ],
    [
        'id' => 'timeline-action',
        'name' => 'Timeline Action',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'Timeline مع أزرار إجراءات',
        'features' => [
            'Timeline تفاعلي',
            'أزرار إجراءات مباشرة',
            'تفاعل سريع'
        ],
        'preferences' => [
            '⚠️ مفهوم جيد لكن معقد'
        ],
        'color' => '#dc2626'
    ],
    [
        'id' => 'timeline-view',
        'name' => 'Timeline View',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'عرض Timeline أساسي',
        'features' => [
            'Timeline بسيط',
            'عرض زمني',
            'تصميم أساسي'
        ],
        'preferences' => [
            '⚠️ تصميم أساسي جداً'
        ],
        'color' => '#84cc16'
    ],
    [
        'id' => 'clean-ui',
        'name' => 'Clean UI',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'واجهة نظيفة ومبسطة',
        'features' => [
            'تصميم نظيف',
            'بسيط ومباشر',
            'تركيز على المحتوى'
        ],
        'preferences' => [
            '✅ النظافة مهمة',
            '⚠️ لكن يحتاج ميزات أكثر'
        ],
        'color' => '#06b6d4'
    ],
    [
        'id' => 'ai-first',
        'name' => 'AI First',
        'status' => 'مكتمل',
        'priority' => 'منخفضة',
        'description' => 'تصميم يركز على الذكاء الاصطناعي',
        'features' => [
            'اقتراحات ذكية',
            'مطابقة آلية',
            'تعلم من الاستخدام'
        ],
        'preferences' => [
            '✅ الذكاء الاصطناعي مهم',
            '⚠️ لكن التصميم يحتاج تحسين'
        ],
        'color' => '#f59e0b'
    ]
];

// Group experiments by priority
$byPriority = [
    'عالية جداً' => [],
    'مرجعية' => [],
    'متوسطة' => [],
    'منخفضة' => []
];

foreach ($experiments as $exp) {
    $byPriority[$exp['priority']][] = $exp;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مختبر التصميم - دليل التجارب</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 60px;
        }
        
        .header h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 12px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.95;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: rgba(255,255,255,0.9);
            font-weight: 600;
        }
        
        .priority-section {
            margin-bottom: 50px;
        }
        
        .priority-header {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .priority-title {
            font-size: 24px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .experiments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
        }
        
        .experiment-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .experiment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }
        
        .card-accent {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .card-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }
        
        .card-meta {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-status {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .badge-priority {
            background: #fef3c7;
            color: #d97706;
        }
        
        .badge-priority.high {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .badge-priority.reference {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .section {
            margin-bottom: 24px;
        }
        
        .section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #475569;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .feature-list {
            list-style: none;
        }
        
        .feature-item {
            padding: 8px 0;
            padding-right: 20px;
            font-size: 13px;
            color: #64748b;
            position: relative;
            line-height: 1.5;
        }
        
        .feature-item:before {
            content: "•";
            position: absolute;
            right: 0;
            color: #94a3b8;
            font-weight: 800;
        }
        
        .preference-item {
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 600;
        }
        
        .preference-item.positive {
            background: #dcfce7;
            color: #166534;
        }
        
        .preference-item.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .preference-item.negative {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .preference-item.pending {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .card-footer {
            padding: 20px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-view {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .comparison-section {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 32px;
            margin-top: 50px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .comparison-title {
            font-size: 28px;
            font-weight: 800;
            color: white;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .comparison-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
        }
        
        .comparison-card h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 16px;
        }
        
        .comparison-card ul {
            list-style: none;
        }
        
        .comparison-card li {
            padding: 8px 0;
            padding-right: 20px;
            font-size: 14px;
            color: #64748b;
            position: relative;
        }
        
        .comparison-card li:before {
            content: "→";
            position: absolute;
            right: 0;
            color: #3b82f6;
            font-weight: 800;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 مختبر التصميم</h1>
            <p>دليل شامل لجميع التجارب التصميمية مع المواصفات والتفضيلات</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($experiments) ?></div>
                <div class="stat-label">تجربة تصميمية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($byPriority['عالية جداً']) + count($byPriority['مرجعية']) ?></div>
                <div class="stat-label">تجربة نشطة</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3</div>
                <div class="stat-label">تصاميم مرجعية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">1</div>
                <div class="stat-label">قيد التطوير</div>
            </div>
        </div>
        
        <?php foreach ($byPriority as $priority => $exps): ?>
            <?php if (empty($exps)) continue; ?>
            
            <div class="priority-section">
                <div class="priority-header">
                    <div class="priority-title">
                        <?php
                        $icons = [
                            'عالية جداً' => '🔥',
                            'مرجعية' => '⭐',
                            'متوسطة' => '📌',
                            'منخفضة' => '📁'
                        ];
                        echo $icons[$priority];
                        ?>
                        <?= $priority ?>
                    </div>
                </div>
                
                <div class="experiments-grid">
                    <?php foreach ($exps as $exp): ?>
                        <div class="experiment-card">
                            <div class="card-accent" style="background: <?= $exp['color'] ?>"></div>
                            
                            <div class="card-header">
                                <h2 class="card-title"><?= $exp['name'] ?></h2>
                                <p class="card-description"><?= $exp['description'] ?></p>
                                <div class="card-meta">
                                    <span class="badge badge-status"><?= $exp['status'] ?></span>
                                    <span class="badge badge-priority <?= $priority === 'عالية جداً' ? 'high' : ($priority === 'مرجعية' ? 'reference' : '') ?>">
                                        <?= $priority ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="section">
                                    <h3 class="section-title">✨ الميزات</h3>
                                    <ul class="feature-list">
                                        <?php foreach ($exp['features'] as $feature): ?>
                                            <li class="feature-item"><?= $feature ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                
                                <div class="section">
                                    <h3 class="section-title">💭 التفضيلات الشخصية</h3>
                                    <?php foreach ($exp['preferences'] as $pref): ?>
                                        <?php
                                        $class = 'warning';
                                        if (strpos($pref, '✅') !== false) $class = 'positive';
                                        elseif (strpos($pref, '❌') !== false) $class = 'negative';
                                        elseif (strpos($pref, '⏳') !== false) $class = 'pending';
                                        ?>
                                        <div class="preference-item <?= $class ?>">
                                            <?= $pref ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <a href="/lab/experiments/<?= $exp['id'] ?>" class="btn-view">
                                    عرض التجربة →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="comparison-section">
            <h2 class="comparison-title">📊 التشابهات والفروقات الرئيسية</h2>
            <div class="comparison-grid">
                <div class="comparison-card">
                    <h3>🎯 التوزيع الثلاثي</h3>
                    <ul>
                        <li>جميع التصاميم الرئيسية تستخدم توزيع ثلاثي</li>
                        <li>Timeline دائماً على اليمين (RTL)</li>
                        <li>Decision/Main panel في الوسط</li>
                        <li>Sidebar/Attachments على اليسار</li>
                    </ul>
                </div>
                
                <div class="comparison-card">
                    <h3>🎨 الألوان والتصميم</h3>
                    <ul>
                        <li>التصاميم الفاتحة مفضلة على الداكنة</li>
                        <li>unified-workflow: تصميم حاد ونظيف</li>
                        <li>improved-current: تصميم تقليدي محسّن</li>
                        <li>experiment-ultimate-enhanced: دمج الأفضل</li>
                    </ul>
                </div>
                
                <div class="comparison-card">
                    <h3>📍 موقع Progress Bar</h3>
                    <ul>
                        <li>improved-current: في Context bar</li>
                        <li>experiment-ultimate-enhanced: في Sidebar (مفضل)</li>
                        <li>unified-workflow: لا يوجد</li>
                    </ul>
                </div>
                
                <div class="comparison-card">
                    <h3>🎛️ Action Bar / Footer</h3>
                    <ul>
                        <li>improved-current: داخل Main panel</li>
                        <li>experiment-ultimate-enhanced: Footer منفصل (مفضل)</li>
                        <li>unified-workflow: داخل Card footer</li>
                    </ul>
                </div>
                
                <div class="comparison-card">
                    <h3>📋 محتوى Form</h3>
                    <ul>
                        <li>improved-current: قسم واحد للمورد والبنك (مفضل)</li>
                        <li>experiment-ultimate (قديم): قسمين منفصلين</li>
                        <li>unified-workflow: fields-grid مختلف</li>
                    </ul>
                </div>
                
                <div class="comparison-card">
                    <h3>⏱️ Timeline Design</h3>
                    <ul>
                        <li>unified-workflow: حاد ونظيف (مفضل)</li>
                        <li>improved-current: تفاعلي مع أحداث</li>
                        <li>timeline-pro: احترافي مع تأثيرات</li>
                        <li>experiment-ultimate-enhanced: سيستخدم unified-workflow</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
