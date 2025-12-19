<?php
require_once __DIR__ . '/app/Support/autoload.php';

$db = App\Support\Database::connect();

echo "=== حالة قاعدة البيانات ===\n\n";

echo "الجداول الجديدة:\n";
$tables = ['import_batches', 'action_sessions', 'guarantees', 'guarantee_actions'];
foreach ($tables as $table) {
    $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "  $table: $count سجل\n";
}

echo "\nالجدول القديم:\n";
$old = $db->query("SELECT COUNT(*) FROM imported_records")->fetchColumn();
echo "  imported_records: $old سجل\n";

echo "\nالأعمدة الانتقالية في imported_records:\n";
$stmt = $db->query("PRAGMA table_info(imported_records)");
$hasColumns = ['migrated_guarantee_id' => false, 'migrated_action_id' => false, 'import_batch_id' => false];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($hasColumns[$row['name']])) {
        $hasColumns[$row['name']] = true;
    }
}
foreach ($hasColumns as $col => $exists) {
    echo "  $col: " . ($exists ? "✓ موجود" : "✗ غير موجود") . "\n";
}

echo "\n✅ النظام جاهز للاستخدام!\n";
