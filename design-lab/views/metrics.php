<?php
// إعداد البيانات
$experimentsDir = __DIR__ . '/../experiments';
$files = glob($experimentsDir . '/*.php');
$stats = [];
$totalSize = 0;
$totalLines = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $size = filesize($file);
    $lines = substr_count($content, "\n");
    $name = basename($file, '.php');
    
    // تصنيف بسيط بناءً على الحجم
    $complexity = 'Low';
    if ($size > 30000) $complexity = 'High';
    elseif ($size > 20000) $complexity = 'Medium';
    
    $stats[] = [
        'name' => $name,
        'size' => $size,
        'lines' => $lines,
        'complexity' => $complexity
    ];
    
    $totalSize += $size;
    $totalLines += $lines;
}

// ترتيب حسب الحجم
usort($stats, function($a, $b) {
    return $b['size'] - $a['size'];
});

$avgSize = count($files) > 0 ? $totalSize / count($files) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليلات الأداء - DesignLab</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/design-lab/assets/css/base.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 32px;
            direction: rtl;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header { margin-bottom: 40px; display: flex; align-items: center; justify-content: space-between; }
        .title { font-size: 28px; font-weight: 800; color: #0f172a; }
        .subtitle { color: #64748b; font-size: 14px; }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .kpi-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        
        .kpi-label { font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 8px; }
        .kpi-value { font-size: 32px; font-weight: 800; color: #3b82f6; }
        .kpi-unit { font-size: 14px; color: #94a3b8; font-weight: 500; }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        
        table { width: 100%; border-collapse: collapse; }
        
        th { 
            background: #f8fafc; 
            padding: 16px; 
            text-align: right; 
            font-size: 12px; 
            font-weight: 800; 
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        
        td { 
            padding: 16px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 14px; 
        }
        
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f8fafc; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge.High { background: #fee2e2; color: #ef4444; }
        .badge.Medium { background: #ffedd5; color: #f97316; }
        .badge.Low { background: #dcfce7; color: #22c55e; }
        
        .progress-bar {
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
            width: 100px;
        }
        .progress-value { height: 100%; background: #3b82f6; border-radius: 3px; }

        .back-link {
            display: inline-block;
            margin-bottom: 16px;
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
            <div>
                <h1 class="title">📊 إحصائيات المختبر (Metrics)</h1>
                <p class="subtitle">تحليل فني للكود وحجم الملفات</p>
            </div>
            <div style="text-align: left;">
                <div style="font-size: 12px; color: #94a3b8; font-weight: 700;">LIVE DATA</div>
            </div>
        </header>
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">عدد التجارب</div>
                <div class="kpi-value"><?= count($files) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">إجمالي حجم الكود</div>
                <div class="kpi-value"><?= number_format($totalSize / 1024, 1) ?> <span class="kpi-unit">KB</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">إجمالي الأسطر</div>
                <div class="kpi-value"><?= number_format($totalLines) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">متوسط حجم الملف</div>
                <div class="kpi-value"><?= number_format(($avgSize / 1024), 1) ?> <span class="kpi-unit">KB</span></div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>اسم التجربة</th>
                        <th>الحجم</th>
                        <th>عدد الأسطر</th>
                        <th>التعقيد (Complexity)</th>
                        <th>نسبة الحجم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats as $stat): 
                        $percent = ($stat['size'] / $totalSize) * 100;
                    ?>
                    <tr>
                        <td style="font-weight: 700; font-family: monospace; dir: ltr;"><?= $stat['name'] ?></td>
                        <td><?= number_format($stat['size'] / 1024, 1) ?> KB</td>
                        <td><?= $stat['lines'] ?></td>
                        <td><span class="badge <?= $stat['complexity'] ?>"><?= $stat['complexity'] ?></span></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-value" style="width: <?= $percent ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 40px; text-align: center; color: #94a3b8; font-size: 12px;">
            تم توليد هذه الصفحة تلقائياً بناءً على تحليل ملفات المجلد <code>/experiments</code>
        </div>
    </div>
</body>
</html>
