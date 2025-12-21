<?php
/**
 * Lab Router - التوجيه داخل المختبر
 */

class LabRouter {
    
    /**
     * توجيه الطلب
     */
    public function route($uri) {
        // إزالة /lab من البداية
        $path = str_replace('/lab', '', parse_url($uri, PHP_URL_PATH));
        $path = trim($path, '/');
        
        // الصفحة الرئيسية
        if (empty($path)) {
            $this->renderHome();
            return;
        }
        
        // التجارب
        if (strpos($path, 'experiments/') === 0) {
            $experiment = str_replace('experiments/', '', $path);
            $this->renderExperiment($experiment);
            return;
        }
        
        // Findings
        if ($path === 'findings') {
            $this->renderFindings();
            return;
        }
        
        // Metrics
        if ($path === 'metrics') {
            $this->renderMetrics();
            return;
        }
        
        // Docs
        if ($path === 'docs') {
            $this->renderDocs();
            return;
        }
        
        // 404
        $this->render404();
    }
    
    /**
     * عرض الصفحة الرئيسية
     */
    private function renderHome() {
        require __DIR__ . '/../views/home.php';
    }
    
    /**
     * عرض تجربة
     */
    private function renderExperiment($name) {
        $file = __DIR__ . "/../experiments/{$name}.php";
        if (!file_exists($file)) {
            $this->render404();
            return;
        }
        
        // تعيين اسم التجربة globally
        $GLOBALS['EXPERIMENT_NAME'] = $name;
        
        require $file;
    }
    
    /**
     * عرض Findings
     */
    private function renderFindings() {
        require __DIR__ . '/../views/findings.php';
    }
    
    /**
     * عرض Metrics
     */
    private function renderMetrics() {
        require __DIR__ . '/../views/metrics.php';
    }
    
    /**
     * عرض Docs
     */
    private function renderDocs() {
        header('Location: /design-lab/docs/');
    }
    
    /**
     * 404
     */
    private function render404() {
        http_response_code(404);
        echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>404 - DesignLab</title>
    <link rel="stylesheet" href="/design-lab/assets/css/tokens.css">
    <link rel="stylesheet" href="/design-lab/assets/css/base.css">
</head>
<body>
    <div class="lab-container">
        <h1>404</h1>
        <p>الصفحة غير موجودة في المختبر</p>
        <a href="/lab" class="btn-primary">العودة للرئيسية</a>
    </div>
</body>
</html>';
    }
}
