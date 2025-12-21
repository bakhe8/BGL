<?php
/**
 * Data Access Layer  - القراءة الآمنة
 * 
 * ⚠️ قراءة فقط - لا كتابة
 */

use App\Support\Database;

class LabDataAccess {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    /**
     * جلب سجل ضمان
     */
    public function getGuaranteeRecord($recordId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM imported_records WHERE id = ?"
        );
        $stmt->execute([$recordId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب كل السجلات (مع limit للأمان)
     */
    public function getAllRecords($limit = 100) {
        $stmt = $this->db->prepare(
            "SELECT * FROM imported_records ORDER BY import_date DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب Timeline
     */
    public function getTimeline($sessionId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM guarantee_timeline_events WHERE session_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب القرارات
     */
    public function getDecisions($sessionId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_decisions WHERE session_id = ?"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب توصية AI
     * (محاكاة - في الواقع سيستدعي AIEngine)
     */
    public function getAIRecommendation($recordId) {
        // TODO: استدعاء AIEngine الحقيقي
        // في الوقت الحالي، محاكاة بسيطة
        
        return [
            'decision' => 'approve',
            'confidence' => 0.95,
            'reasons' => [
                '18 حالة مشابهة تمت الموافقة عليها',
                'المورد موثوق (10 عقود ناجحة)',
                'المبلغ ضمن الحدود الطبيعية'
            ],
            'risk_level' => 'low',
            'similar_cases' => 18
        ];
    }
    
    /**
     * جلب حالات مشابهة
     */
    public function getSimilarCases($recordId, $limit = 5) {
        // محاكاة - في الواقع سيبحث في السجلات المشابهة
        return [
            [
                'record_id' => 14001,
                'supplier' => 'شركة المراعي',
                'decision' => 'approved',
                'days_ago' => 45
            ],
            [
                'record_id' => 14002,
                'supplier' => 'شركة نادك',
                'decision' => 'approved',
                'days_ago' => 60
            ]
        ];
    }
    
    /**
     * ⚠️ منع الكتابة تماماً
     */
    public function save($data) {
        throw new Exception("Write operations not allowed in DesignLab. Use the production system for modifications.");
    }
    
    /**
     * ⚠️ منع التحديث
     */
    public function update($id, $data) {
        throw new Exception("Write operations not allowed in DesignLab. Use the production system for modifications.");
    }
    
    /**
     * ⚠️ منع الحذف
     */
    public function delete($id) {
        throw new Exception("Delete operations not allowed in DesignLab. Use the production system for modifications.");
    }
}
