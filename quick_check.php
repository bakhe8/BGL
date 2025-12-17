<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

// 1. Check record 12014
$records = new ImportedRecordRepository();
$record = $records->find(12014);

echo "Record 12014:\n";
echo "  Raw: " . ($record->rawSupplierName ?? 'N/A') . "\n";
echo "  Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
echo "  Match Status: " . ($record->matchStatus ?? 'N/A') . "\n\n";

// 2. Get supplier info
if ($record && $record->supplierId) {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT id, official_name FROM suppliers WHERE id = ?');
    $stmt->execute([$record->supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($supplier) {
        echo "Assigned Supplier:\n";
        echo "  ID: {$supplier['id']}\n";
        echo "  Name: {$supplier['official_name']}\n\n";
    }
}

// 3. Check learning records
$db = Database::getInstance();
$stmt = $db->prepare('SELECT COUNT(*) as cnt FROM supplier_learning WHERE raw_name LIKE ?');
$stmt->execute(['%ZIMMO%']);
$lr = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Learning records with 'ZIMMO': {$lr['cnt']}\n\n";

// 4. Get candidates
$candidateService = new CandidateService(
    new SupplierRepository(),
    new SupplierAlternativeNameRepository(),
    new Normalizer(),
    new BankRepository()
);

$result = $candidateService->supplierCandidates($record->rawSupplierName);
$candidates = $result['candidates'] ?? [];

echo "Candidates generated: " . count($candidates) . "\n";
if (!empty($candidates)) {
    $top = $candidates[0];
    $score = round(($top['score_raw'] ?? $top['score']) * 100);
    echo "  Top: {$top['name']} ({$score}%)\n";
    echo "  Source: " . ($top['source'] ?? 'unknown') . "\n";
    echo "  Supplier ID: " . ($top['supplier_id'] ?? 'N/A') . "\n";
}
