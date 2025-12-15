<?php
namespace App\Controllers;

use App\Support\Database;

class ReportController
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function index()
    {
        // Serve HTML page
        echo file_get_contents(__DIR__ . '/../../www/reports.html');
    }

    /**
     * API: Get operational efficiency stats
     */
    public function getEfficiencyStats()
    {
        // 1. Total imported sessions
        $sessions = $this->pdo->query("SELECT COUNT(*) FROM import_sessions")->fetchColumn();

        // 2. Total records
        $records = $this->pdo->query("SELECT COUNT(*) FROM imported_records")->fetchColumn();

        // 3. Approved vs Pending
        $stats = $this->pdo->query("
            SELECT 
                SUM(CASE WHEN match_status = 'approved' OR match_status = 'ready' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN match_status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM imported_records
        ")->fetch(\PDO::FETCH_ASSOC);

        $this->json([
            'sessions_count' => $sessions,
            'records_count' => $records,
            'approved_count' => $stats['approved_count'] ?? 0,
            'pending_count' => $stats['pending_count'] ?? 0,
            'completion_rate' => $records > 0 ? round(($stats['approved_count'] / $records) * 100, 1) : 0
        ]);
    }

    /**
     * API: Get Bank Distribution
     */
    public function getBankStats()
    {
        $stmt = $this->pdo->query("
            SELECT 
                b.official_name as name,
                COUNT(r.id) as count,
                SUM(CAST(REPLACE(r.amount, ',', '') AS REAL)) as total_amount
            FROM imported_records r
            JOIN banks b ON r.bank_id = b.id
            WHERE r.bank_id IS NOT NULL
            GROUP BY r.bank_id
            ORDER BY count DESC
            LIMIT 10
        ");

        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * API: Top Suppliers
     */
    public function getTopSuppliers()
    {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(s.official_name, r.raw_supplier_name) as name,
                COUNT(r.id) as count
            FROM imported_records r
            LEFT JOIN suppliers s ON r.supplier_id = s.id
            WHERE r.raw_supplier_name IS NOT NULL
            GROUP BY name
            ORDER BY count DESC
            LIMIT 10
        ");

        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}
