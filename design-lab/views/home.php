<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 DesignLab - مختبر التصميم</title>
    <link rel="stylesheet" href="/design-lab/assets/css/tokens.css">
    <link rel="stylesheet" href="/design-lab/assets/css/base.css">
</head>
<body class="lab-mode">
    
    <?php LabMode::renderModeBadge(); ?>
    
    <!-- Version Switcher -->
    <div class="version-switcher">
        <span>النسخة:</span>
        <a href="/">الحالية</a>
        <span class="separator">|</span>
        <a href="/lab" class="active">🧪 المختبر</a>
    </div>
    
    <div class="lab-container">
        <header class="lab-header">
            <h1>🧪 مختبر التصميم</h1>
            <p>تجربة واجهات جديدة دون التأثير على النظام الحالي</p>
        </header>
        
        <section class="experiments-list">
            <h2>📊 التجارب المتاحة</h2>
            
            <div class="experiment-card">
                <h3>Experiment 01: AI-First Decision Flow</h3>
                <p>تركيز على توصية الذكاء الاصطناعي كبطل الصفحة، مع إخفاء التفاصيل الأقل أهمية</p>
                <div class="experiment-meta">
                    <span>🟢 Status: Active</span>
                    <span>📅 Started: 2025-12-21</span>
                    <span>🎯 Goal: -75% time to decision</span>
                </div>
                <a href="/lab/experiments/ai-first" class="btn-primary">
                    فتح التجربة →
                </a>
            </div>
            
            <div class="experiment-card" style="opacity: 0.6;">
                <h3>Experiment 02: Timeline Integrated</h3>
                <p>دمج Timeline مع القرارات في واجهة واحدة متماسكة</p>
                <div class="experiment-meta">
                    <span>⏸️ Status: Planned</span>
                    <span>📅 Planned: TBD</span>
                </div>
                <button class="btn-secondary" disabled>قريباً</button>
            </div>
            
            <div class="experiment-card" style="opacity: 0.6;">
                <h3>Experiment 03: Minimal Flow</h3>
                <p>أبسط تدفق ممكن - تركيز على السرعة القصوى</p>
                <div class="experiment-meta">
                    <span>⏸️ Status: Planned</span>
                    <span>📅 Planned: TBD</span>
                </div>
                <button class="btn-secondary" disabled>قريباً</button>
            </div>
        </section>
        
        <section class="lab-tools" style="margin-top: 4rem;">
            <h2>🛠️ الأدوات</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                <a href="/lab/findings" class="experiment-card" style="text-decoration: none;">
                    <h3 style="font-size: 1.25rem;">📋 Design Findings</h3>
                    <p style="font-size: 0.875rem;">المشاكل المكتشفة من التجارب</p>
                </a>
                
                <a href="/lab/metrics" class="experiment-card" style="text-decoration: none;">
                    <h3 style="font-size: 1.25rem;">📊 Metrics Dashboard</h3>
                    <p style="font-size: 0.875rem;">قياسات ومقارنات الأداء</p>
                </a>
                
                <a href="/lab/docs" class="experiment-card" style="text-decoration: none;">
                    <h3 style="font-size: 1.25rem;">📚 التوثيق</h3>
                    <p style="font-size: 0.875rem;">المبادئ والنظام الوثائقي</p>
                </a>
            </div>
        </section>
        
        <section style="margin-top: 4rem; padding: 2rem; background: rgba(99, 102, 241, 0.1); border-radius: 1rem; border: 1px solid rgba(99, 102, 241, 0.3);">
            <h3 style="margin-bottom: 1rem;">ℹ️ نبذة عن المختبر</h3>
            <p style="color: var(--color-text-secondary); margin-bottom: 1rem;">
                DesignLab هو بيئة منفصلة تماماً لتجربة تصميمات جديدة دون التأثير على النظام الحالي.
                يستخدم نفس البيانات لكن في وضع <strong>القراءة فقط</strong>.
            </p>
            <p style="color: var(--color-text-secondary); margin-bottom: 1rem;">
                كل تجربة تمر بـ <strong>3 وثائق إلزامية</strong> قبل التنفيذ:
            </p>
            <ol style="color: var(--color-text-secondary); padding-right: 1.5rem;">
                <li><strong>Design Finding</strong> - إثبات المشكلة</li>
                <li><strong>Logic Impact Note</strong> - تحليل التأثير</li>
                <li><strong>Decision Record</strong> - قرار الموافقة/الرفض</li>
            </ol>
        </section>
    </div>
    
    <footer class="lab-footer">
        <a href="/" class="back-to-production">← العودة للنظام الحالي</a>
        <p style="margin-top: 1rem; color: var(--color-text-muted); font-size: 0.875rem;">
            DesignLab v1.0 | Built with the Three-Document System
        </p>
    </footer>
    
</body>
</html>
