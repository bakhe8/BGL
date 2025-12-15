<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Services\ImportService;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Repositories\LearningLogRepository;
use App\Support\Database;
use App\Support\XlsxReader;

class ExcelFixturesTest extends TestCase
{
    private string $testDbPath;

    public function __construct(TestRunner $runner)
    {
        parent::__construct($runner);
        $this->setupDatabase();
    }

    private function setupDatabase(): void
    {
        // 1. Create a temporary DB file
        $this->testDbPath = __DIR__ . '/../../tests/test.sqlite';
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        touch($this->testDbPath);

        // 2. Override connection
        Database::setDatabasePath($this->testDbPath);

        // 3. Apply Schema
        $schema = file_get_contents(__DIR__ . '/../../storage/database/schema.sql');
        
        // Remove comments to avoid issues
        $schema = preg_replace('/--.*$/m', '', $schema);
        
        // Split by semicolon
        $statements = explode(';', $schema);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt) {
                Database::connection()->exec($stmt);
            }
        }
    }
    
    public function __destruct()
    {
        // Cleanup
        if (file_exists($this->testDbPath)) {
            // unlink($this->testDbPath); // Keep for debugging if needed, or unlike. 
            // Better to clean up.
            unlink($this->testDbPath);
        }
    }

    public function testImportAllFixtures()
    {
        $fixturesDir = __DIR__ . '/../../tests/fixtures';
        $files = glob($fixturesDir . '/*.xlsx');

        if (empty($files)) {
            echo "   ⚠️ No Excel files found in tests/fixtures\n";
            return;
        }

        $service = new ImportService(
            new ImportSessionRepository(),
            new ImportedRecordRepository(),
            new XlsxReader()
        );

        foreach ($files as $file) {
            $filename = basename($file);
            echo "      📄 Importing $filename... ";

            try {
                $result = $service->importExcel($file);
                
                if ($result['records_count'] > 0) {
                     echo "✅ Success ({$result['records_count']} records)\n";
                } elseif (isset($result['skipped']) && count($result['skipped']) > 0) {
                     echo "⚠️  0 Records. Skipped: " . count($result['skipped']) . " rows. Examples:\n";
                     foreach (array_slice($result['skipped'], 0, 3) as $skip) {
                         echo "        - $skip\n";
                     }
                } else {
                     echo "❌ Failed: 0 Records imported and no skip reasons.\n";
                     // Check debug map
                     if (isset($result['debug_map'])) {
                        echo "        Debug Map: " . json_encode($result['debug_map']) . "\n";
                     }
                }

            } catch (RuntimeException $e) {
                // Check if it's the 100-row limit exception
                if (str_contains($e->getMessage(), 'أكثر من 100 صف')) {
                    echo "✅ Passed (Limit Enforced)\n";
                } elseif (str_contains($e->getMessage(), 'لم يتم العثور على صفوف')) {
                    echo "⚠️ Skipped (Empty)\n";
                } else {
                     $msg = "❌ Failed: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString() . "\n";
                     echo $msg;
                     file_put_contents(__DIR__ . '/../../tests/test_error.log', $msg, FILE_APPEND);
                }
            } catch (Throwable $e) {
                $msg = "❌ Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString() . "\n";
                echo $msg;
                file_put_contents(__DIR__ . '/../../tests/test_error.log', $msg, FILE_APPEND);
            }
        }
    }
}
