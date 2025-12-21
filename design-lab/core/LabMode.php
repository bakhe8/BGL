<?php
/**
 * Lab Mode Manager
 * 
 * يضمن أن المختبر في وضع القراءة فقط
 */

class LabMode {
    
    /**
     * تفعيل وضع القراءة فقط
     */
    public static function enableReadOnlyMode() {
        // منع أي عمليات كتابة
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || 
            $_SERVER['REQUEST_METHOD'] === 'PUT' || 
            $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            
            // السماح فقط للـ metrics logging
            if (!self::isMetricsEndpoint()) {
                self::blockWriteOperation();
            }
        }
    }
    
    /**
     * منع عملية كتابة
     */
    private static function blockWriteOperation() {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Write operations not allowed in DesignLab',
            'mode' => 'readonly',
            'message' => '🧪 المختبر في وضع القراءة فقط - استخدم النظام الحقيقي للتعديل'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * التحقق إذا كان metrics endpoint
     */
    private static function isMetricsEndpoint() {
        $uri = $_SERVER['REQUEST_URI'];
        return strpos($uri, '/lab/api/metrics') !== false;
    }
    
    /**
     * عرض تنبيه بصري
     */
    public static function renderModeBadge() {
        echo '<div class="lab-mode-badge">🧪 وضع المختبر - للتجربة فقط</div>';
    }
    
    /**
     * التحقق من وضع المختبر
     */
    public static function isLabMode() {
        return defined('LAB_MODE') && LAB_MODE === true;
    }
    
    /**
     * الحصول على warning message
     */
    public static function getWarningMessage() {
        return '⚠️ تحذير: أنت في وضع المختبر. لن يتم حفظ أي تغييرات.';
    }
}
