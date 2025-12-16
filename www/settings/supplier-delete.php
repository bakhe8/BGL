<?php
/**
 * Supplier Delete Handler
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\SupplierRepository;

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id) {
    $suppliers = new SupplierRepository();
    $suppliers->delete($id);
}

header('Location: /settings.php?tab=suppliers&msg=deleted');
exit;
