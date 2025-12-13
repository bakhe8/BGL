<?php
require 'vendor/autoload.php';
// Bootstrapping manually if needed, or just relying on autoload
// Assuming simple app structure based on previous errors


use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Services\CandidateService;
use App\Support\Settings;

$recordId = 8408;
$record = ImportedRecordRepository::find($recordId);

if (!$record) {
    die("Record $recordId not found.\n");
}

echo "=== Record Data ===\n";
echo "ID: " . $record->id . "\n";
echo "Raw Supplier: '" . $record->rawSupplierName . "'\n";
echo "Raw Bank: '" . $record->rawBankName . "'\n";
echo "Current SupplierID: " . ($record->supplier_id ?? 'NULL') . "\n";
echo "match_status: " . $record->matchStatus . "\n\n";

echo "=== Matching Logic Check ===\n";
$service = new CandidateService(
    new SupplierRepository(),
    new \App\Repositories\SupplierAlternativeNameRepository()
);

// Force debug output of candidates
$result = $service->supplierCandidates($record->rawSupplierName);
echo "Candidates Found: " . count($result['candidates']) . "\n";
foreach ($result['candidates'] as $c) {
    echo " - " . $c['name'] . " (Score: " . $c['score'] . ")\n";
}

echo "\n=== Settings Check ===\n";
$settings = new Settings();
echo "MATCH_REVIEW_THRESHOLD: " . $settings->get('MATCH_REVIEW_THRESHOLD', 0.70) . "\n";
echo "MATCH_AUTO_THRESHOLD: " . $settings->get('MATCH_AUTO_THRESHOLD', 0.90) . "\n";

echo "\n=== Top 5 Closest Suppliers (Manual Check) ===\n";
$all = SupplierRepository::allNormalized();
$raw = $record->rawSupplierName;
$normalizedRaw = (new \App\Support\Normalizer())->normalizeSupplierName($raw);
echo "Normalized Raw: '$normalizedRaw'\n";

$scores = [];
foreach ($all as $s) {
    // Simple similar_text or levenshtein for debug
    $dist = levenshtein($normalizedRaw, $s['normalized_name']);
    $len = max(strlen($normalizedRaw), strlen($s['normalized_name']));
    $ratio = 1 - ($dist / $len);
    $scores[] = ['name' => $s['official_name'], 'norm' => $s['normalized_name'], 'score' => $ratio];
}

usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

foreach (array_slice($scores, 0, 5) as $s) {
    echo " - " . $s['name'] . " ('" . $s['norm'] . "') => Score: " . number_format($s['score'], 4) . "\n";
}
