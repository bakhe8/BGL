<?php
// Backup database before cleanup
$timestamp = date('Ymd_His');
$source = 'bgl.sqlite';
$backup = "bgl_backup_{$timestamp}.sqlite";

if (copy($source, $backup)) {
    echo "✅ Backup created: $backup\n";
} else {
    echo "❌ Backup failed\n";
    exit(1);
}

// Open database
$db = new PDO('sqlite:bgl.sqlite');

// Delete old modifications
$stmt = $db->prepare('DELETE FROM imported_records WHERE record_type = "modification" AND created_at < "2025-12-20"');
$stmt->execute();
echo "✅ Deleted " . $stmt->rowCount() . " old modifications\n";

// Delete test timeline events
$stmt = $db->prepare('DELETE FROM guarantee_timeline_events WHERE guarantee_number LIKE "TEST%" AND created_at < "2025-12-20"');
$stmt->execute();
echo "✅ Deleted " . $stmt->rowCount() . " test events\n";

// Optimize database
$db->exec('VACUUM');
echo "✅ Database optimized\n";

echo "\n✅ Cleanup complete!\n";
