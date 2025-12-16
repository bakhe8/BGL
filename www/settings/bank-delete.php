<?php
/**
 * Bank Delete Handler
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\BankRepository;

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id) {
    $banks = new BankRepository();
    $banks->delete($id);
}

header('Location: /settings.php?tab=banks&msg=deleted');
exit;
