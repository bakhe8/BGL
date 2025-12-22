<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - DesignLab</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.2.0/github-markdown-dark.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
            margin-bottom: 16px;
        }
        
        .back-link:hover { color: white; }
        
        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: #f1f5f9;
        }
        
        .markdown-body {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 40px;
            color: #e2e8f0 !important;
        }
        
        .markdown-body h1,
        .markdown-body h2,
        .markdown-body h3,
        .markdown-body h4,
        .markdown-body h5,
        .markdown-body h6 {
            color: #f1f5f9 !important;
            border-bottom-color: rgba(255,255,255,0.1) !important;
        }
        
        .markdown-body a {
            color: #60a5fa !important;
        }
        
        .markdown-body a:hover {
            color: #93c5fd !important;
        }
        
        .markdown-body code {
            background: rgba(0,0,0,0.3) !important;
            color: #fbbf24 !important;
        }
        
        .markdown-body pre {
            background: rgba(0,0,0,0.3) !important;
        }
        
        .markdown-body pre code {
            color: #e2e8f0 !important;
        }
        
        .markdown-body table {
            background: transparent !important;
        }
        
        .markdown-body table tr {
            background: rgba(255,255,255,0.02) !important;
            border-top-color: rgba(255,255,255,0.1) !important;
        }
        
        .markdown-body table tr:nth-child(2n) {
            background: rgba(255,255,255,0.04) !important;
        }
        
        .markdown-body table th,
        .markdown-body table td {
            border-color: rgba(255,255,255,0.1) !important;
        }
        
        .markdown-body blockquote {
            color: #94a3b8 !important;
            border-right-color: #3b82f6 !important;
            border-left: none !important;
            border-right: 4px solid #3b82f6 !important;
        }
        
        .markdown-body hr {
            background-color: rgba(255,255,255,0.1) !important;
        }
    </style>
</head>
<body>

    <?php if (class_exists('LabMode')) LabMode::renderModeBadge(); ?>

    <div class="container">
        
        <div class="header">
            <a href="/lab" class="back-link">← العودة للمختبر</a>
            <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>
        
        <div id="markdown-content" class="markdown-body"></div>
        
    </div>

    <script>
        // تحويل Markdown إلى HTML
        const markdownContent = <?php echo json_encode($markdownContent); ?>;
        const htmlContent = marked.parse(markdownContent);
        document.getElementById('markdown-content').innerHTML = htmlContent;
    </script>

</body>
</html>
