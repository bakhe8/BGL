<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($findingId) ?> - DesignLab</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 32px;
            line-height: 1.6;
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .back-link:hover { color: #1e293b; }
        
        .paper {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 48px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        
        /* Markdown Styles Simulation */
        h1 { font-size: 28px; font-weight: 800; margin-bottom: 24px; border-bottom: 2px solid #f1f5f9; padding-bottom: 16px; }
        h2 { font-size: 20px; font-weight: 700; margin-top: 32px; margin-bottom: 16px; color: #334155; }
        h3 { font-size: 16px; font-weight: 700; margin-top: 24px; margin-bottom: 12px; }
        p { margin-bottom: 16px; color: #475569; }
        ul, ol { margin-bottom: 16px; padding-right: 24px; color: #475569; }
        li { margin-bottom: 8px; }
        blockquote { border-right: 4px solid #3b82f6; margin: 24px 0; padding: 8px 16px; background: #eff6ff; color: #1e40af; border-radius: 4px; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; color: #0f172a; }
        pre { background: #1e293b; color: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; margin-bottom: 24px; direction: ltr; }
        pre code { background: none; color: inherit; padding: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: right; }
        th { background: #f8fafc; font-weight: 700; color: #475569; }
        
        .meta-header {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 32px;
            font-size: 13px;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .raw-content { white-space: pre-wrap; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/lab/findings" class="back-link">← العودة للقائمة</a>
        
        <div class="paper">
            <?php
            // Simple Markdown Parser (Very Basic)
            $html = htmlspecialchars($findingContent);
            
            // Headers
            $html = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $html);
            $html = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $html);
            $html = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $html);
            
            // Bold
            $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
            
            // List Items
            $html = preg_replace('/^- (.*)$/m', '<li>$1</li>', $html);
            
            // Quote
            $html = preg_replace('/^> (.*)$/m', '<blockquote>$1</blockquote>', $html);
            
            // Table (very loose approximation for display)
            $html = str_replace('|', ' | ', $html);
            
            // Output
            // Note: This is not safe for user input, but this is a dev lab tool.
            echo nl2br($html); 
            ?>
            
            <!-- Fallback to raw if complex -->
            <!-- <div class="raw-content"><?= htmlspecialchars($findingContent) ?></div> -->
        </div>
    </div>
</body>
</html>
