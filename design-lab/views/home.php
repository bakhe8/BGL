<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 DesignLab - مختبر التصميم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f1f5f9;
            min-height: 100vh;
            padding: 32px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 48px;
            padding-bottom: 32px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 10px 30px -10px rgba(59, 130, 246, 0.5);
        }
        
        .logo-text {
            font-size: 32px;
            font-weight: 800;
        }
        
        .header-subtitle {
            color: #64748b;
            font-size: 16px;
        }
        
        /* Section */
        .section {
            margin-bottom: 48px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-count {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 50px;
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
        }
        
        /* Experiments Grid */
        .experiments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        
        /* Experiment Card */
        .experiment-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .experiment-card:hover {
            border-color: rgba(255,255,255,0.15);
            transform: translateY(-4px);
            background: rgba(255,255,255,0.05);
        }
        
        .experiment-card.featured {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.05));
        }
        
        .experiment-card.featured::before {
            content: '⭐ الأفضل';
            position: absolute;
            top: 16px;
            left: 16px;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        
        .card-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .card-icon.blue { background: rgba(59, 130, 246, 0.2); }
        .card-icon.purple { background: rgba(139, 92, 246, 0.2); }
        .card-icon.green { background: rgba(34, 197, 94, 0.2); }
        .card-icon.orange { background: rgba(249, 115, 22, 0.2); }
        .card-icon.pink { background: rgba(236, 72, 153, 0.2); }
        
        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .card-subtitle {
            font-size: 12px;
            color: #64748b;
        }
        
        .card-desc {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        
        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .tag {
            font-size: 10px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 50px;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
        }
        
        .tag.active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .tag.archived { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .tag.reference { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        
        .card-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .card-action.primary {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
        }
        
        .card-action.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -8px rgba(59, 130, 246, 0.5);
        }
        
        .card-action.secondary {
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
        }
        
        .card-action.secondary:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        /* Tools Section */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .tool-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        
        .tool-card:hover {
            border-color: rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
        }
        
        .tool-icon { font-size: 24px; margin-bottom: 12px; }
        .tool-title { font-weight: 700; margin-bottom: 4px; }
        .tool-desc { font-size: 12px; color: #64748b; }
        
        /* Footer */
        .footer {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .back-link:hover { color: white; }
        
        .footer-info { font-size: 12px; color: #475569; }

        /* Feedback Annotations */
        .feedback-section {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.5;
        }
        .feedback-section strong { display: block; margin-bottom: 4px; }
        .feedback-section ul { padding-right: 16px; margin: 0; }
        
        .feedback-section.success {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .feedback-section.error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .feedback-section.idea {
            background: rgba(168, 85, 247, 0.1);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }
    </style>
</head>
<body>

    <?php if (class_exists('LabMode')) LabMode::renderModeBadge(); ?>

    <div class="container">
        
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-icon">🧪</div>
                <span class="logo-text">مختبر التصميم</span>
            </div>
            <p class="header-subtitle">تجربة واجهات جديدة دون التأثير على النظام الحالي</p>
        </header>
        

        
        <!-- All Experiments -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">📊 جميع التجارب</h2>
                <span class="section-count">12 تجربة</span>
            </div>
            
            <div class="experiments-grid">
            
                <!-- Chronos (Pro) - Was Featured -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon blue">✨</div>
                        <div>
                            <h3 class="card-title">Chronos (Pro)</h3>
                            <p class="card-subtitle">الجيل القادم من الواجهات</p>
                        </div>
                    </div>
                    <p class="card-desc">
                        تصميم مبني من الصفر يجمع بين "التايم لاين التفاعلي" و "الهوية البصرية النظيفة".
                        ألوان فاتحة، مساحات واسعة، وتفاعل مبهـر.
                    </p>
                    <div class="card-tags">
                        <span class="tag active">💎 الإصدار الذهبي</span>
                        <span class="tag">Light Mode</span>
                        <span class="tag">Interactive</span>
                    </div>
                    <a href="/lab/experiments/timeline-pro" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>وضوح التايم لاين</li>
                            <li>التفريق الجيد بين الإجراءات</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات للتعديل:</strong>
                        <ul>
                            <li>القائمة الرئيسية غير مفهومة</li>
                            <li>المعاينة لا تمثل مقاس A4</li>
                            <li>الهيدر الموحد يترك مساحات فارغة (Header)</li>
                            <li>كثرة الألوان والزوايا المائلة توحي بعدم الجدية (ترفيهي وليس عملي)</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Unified Practical (New Hybrid) -->
                <div class="experiment-card featured" style="border-color: #8b5cf6; background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.05));">
                    <div class="card-header">
                        <div class="card-icon purple">✨</div>
                        <div>
                            <h3 class="card-title">Unified Practical</h3>
                            <p class="card-subtitle">الدمج العملي</p>
                        </div>
                    </div>
                    <p class="card-desc">
                        يجمع بين توزيع "Unified Workflow" وتصميم "Improved Current".
                        واجهة يومية سريعة وعملية.
                    </p>
                    <div class="card-tags">
                        <span class="tag active">🚀 جديد</span>
                        <span class="tag">Hybrid</span>
                    </div>
                    <a href="/lab/experiments/unified-practical" class="card-action primary" style="background: linear-gradient(135deg, #8b5cf6, #6366f1);">
                        عرض التجربة
                    </a>
                    <div class="feedback-section success">
                        <strong>🎯 الهدف:</strong>
                        <ul>
                            <li>سهولة الوصول للمعلومة (Unified Layout)</li>
                            <li>وضوح بصري (Clean UI)</li>
                        </ul>
                    </div>
                </div>

                <!-- Improved Current (Key Reference) -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon blue">📈</div>
                        <div>
                            <h3 class="card-title">Improved Current</h3>
                            <p class="card-subtitle">التطوير التدريجي</p>
                        </div>
                    </div>
                    <p class="card-desc">تحسين الواجهة الحالية بدلاً من إعادة التصميم الكامل (Better UI Elements).</p>
                    <div class="card-tags">
                        <span class="tag reference">مرجع أساسي</span>
                    </div>
                    <a href="/lab/experiments/improved-current" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات:</strong>
                        <ul>
                            <li>نظافة التصميم</li>
                            <li>وضوح العناصر</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>كثرة الاطارات داخل بعضها البعض</li>
                        </ul>
                    </div>
                </div>

                <!-- Focused Workflow -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon orange">🎯</div>
                        <div>
                            <h3 class="card-title">Focused Workflow</h3>
                            <p class="card-subtitle">التركيز العالي</p>
                        </div>
                    </div>
                    <p class="card-desc">واجهة تركز على مهمة واحدة في كل مرة.</p>
                    <div class="card-tags">
                        <span class="tag">تجريبي</span>
                    </div>
                    <a href="/lab/experiments/focused-workflow" class="card-action secondary">عرض</a>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>عدم استغلال المساحات بشكل كافي</li>
                        </ul>
                    </div>
                </div>

                <!-- Timeline Action (New) -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon purple">⚡</div>
                        <div>
                            <h3 class="card-title">Timeline Action</h3>
                            <p class="card-subtitle">التايم لاين كمحرك للنظام</p>
                        </div>
                    </div>
                    <p class="card-desc">نقلة نوعية: واجهة تعتمد على إضافة أحداث مباشرة في التايم لاين.</p>
                    <div class="card-tags">
                        <span class="tag active">🚀 جديد</span>
                    </div>
                    <a href="/lab/experiments/timeline-action" class="card-action secondary">عرض</a>
                    <div class="feedback-section error">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>الألوان الغامقة غير مرغوبة</li>
                            <li>استخدام سيء للألوان</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Unified Workflow -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon purple">🔗</div>
                        <div>
                            <h3 class="card-title">Unified Workflow</h3>
                            <p class="card-subtitle">تصميم ثلاثي الأعمدة</p>
                        </div>
                    </div>
                    <p class="card-desc">تخطيط ثلاثي: sidebar + منطقة عمل + تايم لاين دائم</p>
                    <div class="card-tags">
                        <span class="tag reference">مرجع</span>
                    </div>
                    <a href="/lab/experiments/unified-workflow" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>معلومات المورد والمقترحات (Excel)</li>
                            <li>معلومات البنك واضحة وكافية</li>
                            <li>تخطيط الشاشة (يمين قائمة / يسار تايم لاين)</li>
                            <li>حدة التمايز بين المكونات (بدون فراغات أو إطارات زائدة)</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>وجود قائمة رئيسية غير مرغوب فيها ولا حاجة لها</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Unified Workflow Light -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon green">☀️</div>
                        <div>
                            <h3 class="card-title">Unified Workflow Light</h3>
                            <p class="card-subtitle">التصميم الفاتح الأصلي</p>
                        </div>
                    </div>
                    <p class="card-desc">تصميم فاتح وبسيط مع بطاقات نظيفة</p>
                    <div class="card-tags">
                        <span class="tag reference">مرجع</span>
                    </div>
                    <a href="/lab/experiments/unified-workflow-light" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>أفضل كرت للبيانات الأساسية</li>
                            <li>احتواء الكرت على كامل معلومات الضمان</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Unified Workflow Dark -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon pink">🌙</div>
                        <div>
                            <h3 class="card-title">Unified Workflow Dark</h3>
                            <p class="card-subtitle">التصميم الداكن Premium</p>
                        </div>
                    </div>
                    <p class="card-desc">تصميم داكن مع glassmorphism وتأثيرات بصرية</p>
                    <div class="card-tags">
                        <span class="tag archived">مؤرشف</span>
                    </div>
                    <a href="/lab/experiments/unified-workflow-dark" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>فكرة زر المعاينة</li>
                            <li>طريقة عرض المعاينة (جميلة)</li>
                            <li>الوضوح التام للنصوص والأزرار</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>استخدام الوضع الداكن (Dark Mode)</li>
                            <li>القائمة الرئيسية</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Integrated View -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon blue">📐</div>
                        <div>
                            <h3 class="card-title">Integrated View</h3>
                            <p class="card-subtitle">دمج التايم لاين مع القرار</p>
                        </div>
                    </div>
                    <p class="card-desc">تخطيط ثلاثي: بطاقة + معاينة + تايم لاين</p>
                    <div class="card-tags">
                        <span class="tag reference">مرجع</span>
                        <span class="tag">التوزيع المفضل</span>
                    </div>
                    <a href="/lab/experiments/integrated-view" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>المرجع الافضل في التطبيق العملي الوظيفي البحت (Model Reference)</li>
                            <li>إظهار البرنامج بالشكل الصحيح</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>وجود القائمة الرئيسية</li>
                            <li>تشتت بسبب وجود فراغات غير مستخدمة</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Clean UI -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon green">✨</div>
                        <div>
                            <h3 class="card-title">Clean UI</h3>
                            <p class="card-subtitle">هوية بصرية نظيفة</p>
                        </div>
                    </div>
                    <p class="card-desc">تركيز على بطاقة واحدة مع معاينة المستند</p>
                    <div class="card-tags">
                        <span class="tag reference">مرجع</span>
                        <span class="tag">التركيز المفضل</span>
                    </div>
                    <a href="/lab/experiments/clean-ui" class="card-action secondary">عرض</a>
                    <div class="feedback-section success">
                        <strong>👍 مميزات أعجبتني:</strong>
                        <ul>
                            <li>فصل جزء المعاينة عن العمل</li>
                            <li>بساطة لوحة البيانات</li>
                            <li>قلة المدخلات المطلوبة</li>
                            <li>توحيد مظهر الكروت الداخلية (جميل جداً)</li>
                            <li>تصميم كرت المعاينة وأدواته</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Timeline View -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon purple">⏱️</div>
                        <div>
                            <h3 class="card-title">Timeline View</h3>
                            <p class="card-subtitle">تايم لاين كمحور</p>
                        </div>
                    </div>
                    <p class="card-desc">التسلسل الزمني كعمود فقري للعمليات</p>
                    <div class="card-tags">
                        <span class="tag archived">مؤرشف</span>
                    </div>
                    <a href="/lab/experiments/timeline-view" class="card-action secondary">عرض</a>
                    <div class="feedback-section idea">
                        <strong>💡 فكرة مبتكرة:</strong>
                        <ul>
                            <li>إجراء العمليات داخل التايم لاين مباشرة</li>
                        </ul>
                    </div>
                    <div class="feedback-section error" style="margin-top: 8px">
                        <strong>👎 ملاحظات:</strong>
                        <ul>
                            <li>تصميم الواجهة عموماً سيء</li>
                            <li>الألوان الغامقة غير مرغوبة</li>
                        </ul>
                    </div>
                </div>
                
                <!-- AI First -->
                <div class="experiment-card">
                    <div class="card-header">
                        <div class="card-icon orange">🤖</div>
                        <div>
                            <h3 class="card-title">AI-First</h3>
                            <p class="card-subtitle">الذكاء الاصطناعي يقود</p>
                        </div>
                    </div>
                    <p class="card-desc">توصية AI كبطل الصفحة مع إخفاء التفاصيل</p>
                    <div class="card-tags">
                        <span class="tag">تجريبي</span>
                    </div>
                    <a href="/lab/experiments/ai-first" class="card-action secondary">عرض</a>
                    <div class="feedback-section error">
                        <strong>⛔ مرفوض:</strong>
                        <ul>
                            <li>تم رفض فكرة الاعتماد الكامل على AI</li>
                            <li>التجربة غير مقبولة للمستخدم</li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </section>
        
        
        <!-- Workflow Tools -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">🎯 أدوات العمل</h2>
            </div>
            
            <div class="tools-grid">
                <a href="/lab/docs/workflow-guide.md" class="tool-card" style="border-color: #3b82f6; background: rgba(59, 130, 246, 0.05);">
                    <div class="tool-icon">📖</div>
                    <div class="tool-title">دليل العمل</div>
                    <div class="tool-desc">كيف تعمل داخل المختبر</div>
                </a>
                <a href="/lab/templates/experiment-template.md" class="tool-card" style="border-color: #8b5cf6; background: rgba(139, 92, 246, 0.05);">
                    <div class="tool-icon">📝</div>
                    <div class="tool-title">قالب التجربة</div>
                    <div class="tool-desc">ابدأ تجربة جديدة</div>
                </a>
            </div>
        </section>
        
        <!-- Tools -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">🛠️ الأدوات</h2>
            </div>
            
            <div class="tools-grid">
                <a href="/lab/findings" class="tool-card">
                    <div class="tool-icon">📋</div>
                    <div class="tool-title">Design Findings</div>
                    <div class="tool-desc">المشاكل المكتشفة</div>
                </a>
                <a href="/lab/metrics" class="tool-card">
                    <div class="tool-icon">📊</div>
                    <div class="tool-title">Metrics</div>
                    <div class="tool-desc">قياسات الأداء</div>
                </a>
                <a href="/lab/docs" class="tool-card">
                    <div class="tool-icon">📚</div>
                    <div class="tool-title">التوثيق</div>
                    <div class="tool-desc">المبادئ والنظام</div>
                </a>
            </div>
        </section>

        
        <!-- Footer -->
        <footer class="footer">
            <a href="/" class="back-link">← العودة للنظام الحالي</a>
            <span class="footer-info">DesignLab v1.0 | 12 تجربة متاحة</span>
        </footer>
        
    </div>

</body>
</html>
