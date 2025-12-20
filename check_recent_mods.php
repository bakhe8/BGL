<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();

// Check for recent modifications  
$stmt = $db->query("SELECT id, guarantee_number, session_id, supplier_display_name, created_at, comment FROM imported_records WHERE record_type = 'modification' ORDER BY id DESC LIMIT 5");

echo "Recent modification records:\n";
echo str_repeat("=", 100) . "\n";

$count = 0;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo sprintf(
        "ID: %5d | Guarantee: %-15s | Session: %3d | Supplier: %-30s | Created: %s\n",
        $r['id'],
        $r['guarantee_number'],
        $r['session_id'],
        substr($r['supplier_display_name'] ?? 'NULL', 0, 30),
        $r['created_at']
    );
    
    if ($r['comment']) {
        $comment = json_decode($r['comment'], true);
        if (isset($comment['changes'])) {
            echo "  Changes: " . json_encode($comment['changes'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo str_repeat("-", 100) . "\n";
}

if ($count === 0) {
    echo "NO MODIFICATION RECORDS FOUND!\n";
} else {
    echo "\nTotal: $count modification records.\n";
}

// Check specifically for OG/CC046034
$stmt = $db->prepare("SELECT COUNT(*) as count FROM imported_records WHERE guarantee_number LIKE ? AND record_type = 'modification'");
$stmt->execute(['%OG/CC046034%']);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nModification records for OG/CC046034: {$result['count']}\n";
