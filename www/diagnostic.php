\u003c?php
// Quick diagnostic for decision.php autocomplete issue
require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;

$suppliers = new SupplierRepository();
$banks = new BankRepository();

$allSuppliers = $suppliers->allNormalized();
$allBanks = $banks->allNormalized();

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>Diagnostic</title></head><body>\n";
echo "<h1>Autocomplete Data Diagnostic</h1>\n";

echo "<h2>Suppliers (count: " . count($allSuppliers) . ")</h2>\n";
echo "<pre>";
print_r(array_slice($allSuppliers, 0, 3));
echo "</pre>\n";

echo "<h2>Banks (count: " . count($allBanks) . ")</h2>\n";
echo "<pre>";
print_r(array_slice($allBanks, 0, 3));
echo "</pre>\n";

echo "<h2>JSON Encoded (first supplier)</h2>\n";
echo "<pre>";
echo htmlspecialchars(json_encode(array_slice($allSuppliers, 0, 1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "</pre>\n";

echo "</body></html>";
?\u003e
