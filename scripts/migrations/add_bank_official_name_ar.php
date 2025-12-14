<?php
/**
 * Migration Script: Add official_name_ar to banks table
 * 
 * This script adds the official_name_ar column to the banks table
 * and copies existing official_name data to it.
 * 
 * Usage: php scripts/migrations/add_bank_official_name_ar.php
 */

declare(strict_types=1);

require __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Database;
use App\Support\Logger;

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  Migration: Add official_name_ar to banks table\n";
echo "═══════════════════════════════════════════════════════\n\n";

try {
    $db = Database::connection();
    
    // Check if column already exists
    $columns = [];
    $res = $db->query("PRAGMA table_info('banks')");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    if (in_array('official_name_ar', $columns, true)) {
        echo "⚠️  العمود official_name_ar موجود بالفعل. لا حاجة للإضافة.\n";
        exit(0);
    }
    
    echo "📝 بدء Migration...\n\n";
    
    $db->beginTransaction();
    
    // Step 1: Add the column
    echo "1️⃣  إضافة العمود official_name_ar...\n";
    $db->exec("ALTER TABLE banks ADD COLUMN official_name_ar TEXT NULL");
    echo "   ✅ تم إضافة العمود بنجاح\n\n";
    
    // Step 2: Copy existing data
    echo "2️⃣  نسخ البيانات الحالية من official_name إلى official_name_ar...\n";
    $stmt = $db->exec("UPDATE banks SET official_name_ar = official_name WHERE official_name_ar IS NULL");
    echo "   ✅ تم تحديث {$stmt} صف\n\n";
    
    $db->commit();
    
    echo "═══════════════════════════════════════════════════════\n";
    echo "✅ Migration completed successfully!\n";
    echo "═══════════════════════════════════════════════════════\n\n";
    
    // Log the migration
    Logger::info('Migration completed', [
        'migration' => 'add_bank_official_name_ar',
        'status' => 'success'
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "\n❌ خطأ في Migration:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   الملف: " . $e->getFile() . "\n";
    echo "   السطر: " . $e->getLine() . "\n\n";
    
    Logger::error('Migration failed', [
        'migration' => 'add_bank_official_name_ar',
        'error' => $e->getMessage()
    ]);
    
    exit(1);
}
