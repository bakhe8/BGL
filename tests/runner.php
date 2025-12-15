<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Support/autoload.php';

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(string $directory): void
    {
        echo "🚀 Starting BGL Native Test Runner...\n";
        echo "---------------------------------------------------\n";

        $files = glob($directory . '/*Test.php');
        
        foreach ($files as $file) {
            require_once $file;
            $className = basename($file, '.php');
            
            if (!class_exists($className)) {
                echo "⚠️  Skipping $file: Class $className not found.\n";
                continue;
            }

            $testClass = new $className($this);
            $methods = get_class_methods($testClass);

            echo "📂 Testing $className:\n";

            foreach ($methods as $method) {
                if (str_starts_with($method, 'test')) {
                    try {
                        $testClass->$method();
                        echo "   ✅ $method\n";
                        $this->passed++;
                    } catch (AssertionError $e) {
                        echo "   ❌ $method: " . $e->getMessage() . "\n";
                        $this->failed++;
                        $this->errors[] = "$className::$method - " . $e->getMessage();
                    } catch (Throwable $e) {
                        echo "   🔥 $method: Exception " . $e->getMessage() . "\n";
                        $this->failed++;
                        $this->errors[] = "$className::$method - Exception: " . $e->getMessage();
                    }
                }
            }
            echo "\n";
        }

        $this->summary();
    }

    private function summary(): void
    {
        echo "---------------------------------------------------\n";
        echo "🏁 Summary:\n";
        echo "   Passed: {$this->passed}\n";
        echo "   Failed: {$this->failed}\n";
        
        if ($this->failed > 0) {
            echo "\n❌ Failures:\n";
            foreach ($this->errors as $error) {
                echo "   - $error\n";
            }
            exit(1);
        } else {
            echo "\n✨ All tests passed!\n";
            exit(0);
        }
    }
}

class TestCase
{
    public function __construct(protected TestRunner $runner) {}

    protected function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        if (!$condition) {
            throw new AssertionError($message);
        }
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Failed asserting that " . var_export($actual, true) . " matches expected " . var_export($expected, true);
            throw new AssertionError($msg);
        }
    }
}

// Run if called directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $runner = new TestRunner();
    $dir = isset($argv[1]) ? $argv[1] : __DIR__ . '/unit';
    $runner->run($dir);
}
