<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Repositories\BankRepository;

$repo = new BankRepository();
$banks = $repo->allNormalized();

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo " ID | الاسم العربي                           | English Name        | Code  \n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";

foreach ($banks as $b) {
    $id = str_pad($b['id'], 3, ' ', STR_PAD_LEFT);
    $ar = str_pad(mb_substr($b['official_name'], 0, 35), 40);
    $en = str_pad(mb_substr($b['official_name_en'] ?? '-', 0, 20), 20);
    $code = str_pad($b['short_code'] ?? '-', 6);
    
    echo "{$id} | {$ar} | {$en} | {$code}\n";
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "إجمالي: " . count($banks) . " بنك\n";
