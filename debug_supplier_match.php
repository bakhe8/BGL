<?php
/**
 * Debug Script: Why no supplier candidates for "Gulf Horizon Medical"?
 * Self-contained - no autoloader needed
 * Run: php debug_supplier_match.php
 */

// The raw name from record 8408
$rawName = "Gulf Horizon Medical";

// Simple normalization (mirrors Normalizer::normalizeSupplierName)
function normalizeSupplierName(string $name): string
{
    $name = mb_strtolower(trim($name));
    // Remove common prefixes/suffixes
    $name = preg_replace('/\b(co\.?|ltd\.?|inc\.?|llc\.?|corp\.?|company|corporation)\b/i', '', $name);
    // Remove special chars
    $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
    // Collapse whitespace
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

$normalized = normalizeSupplierName($rawName);

echo "=== Supplier Matching Debug ===\n\n";
echo "Raw Name: '$rawName'\n";
echo "Normalized: '$normalized'\n\n";

// Thresholds (from Settings defaults)
$reviewTh = 0.70;
$weakTh = 0.70;

echo "=== Matching Thresholds ===\n";
echo "MATCH_REVIEW_THRESHOLD: $reviewTh\n";
echo "MATCH_WEAK_THRESHOLD: $weakTh\n\n";

// Database connection
$dbPath = __DIR__ . '/storage/database/app.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found at: $dbPath\n");
}

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all suppliers
$stmt = $pdo->query("SELECT id, official_name, normalized_name FROM suppliers ORDER BY id");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== All Suppliers in Database (" . count($suppliers) . " total) ===\n";
foreach ($suppliers as $s) {
    echo "  [{$s['id']}] {$s['official_name']}\n";
}

echo "\n=== Similarity Scores (Top 10) ===\n";
$scores = [];
foreach ($suppliers as $s) {
    $candNorm = $s['normalized_name'] ?? normalizeSupplierName($s['official_name']);

    // Calculate Levenshtein ratio
    $lenA = strlen($normalized);
    $lenB = strlen($candNorm);
    $maxLen = max($lenA, $lenB);

    if ($maxLen === 0 || $lenA > 255 || $lenB > 255) {
        $levScore = 0;
    } else {
        $dist = levenshtein($normalized, $candNorm);
        $levScore = 1 - ($dist / $maxLen);
    }

    // Token similarity (Jaccard)
    $tokensA = array_filter(explode(' ', $normalized));
    $tokensB = array_filter(explode(' ', $candNorm));
    $intersect = count(array_intersect($tokensA, $tokensB));
    $union = count(array_unique(array_merge($tokensA, $tokensB)));
    $tokenScore = $union > 0 ? $intersect / $union : 0;

    // Contains check
    $contains = (str_contains($candNorm, $normalized) || str_contains($normalized, $candNorm)) ? 0.75 : 0;

    // Max score
    $maxScore = max($levScore, $tokenScore, $contains);

    $scores[] = [
        'id' => $s['id'],
        'name' => $s['official_name'],
        'norm' => $candNorm,
        'lev' => $levScore,
        'token' => $tokenScore,
        'contains' => $contains,
        'max' => $maxScore,
        'passes_threshold' => $maxScore >= $weakTh ? 'YES' : 'NO'
    ];
}

// Sort by max score descending
usort($scores, fn($a, $b) => $b['max'] <=> $a['max']);

// Show top 10
foreach (array_slice($scores, 0, 10) as $s) {
    $passIcon = $s['passes_threshold'] === 'YES' ? '✓' : '✗';
    echo sprintf(
        "  %s [%d] %s\n      Normalized: '%s'\n      Lev: %.4f | Token: %.4f | Contains: %.4f | MAX: %.4f\n\n",
        $passIcon,
        $s['id'],
        $s['name'],
        $s['norm'],
        $s['lev'],
        $s['token'],
        $s['contains'],
        $s['max']
    );
}

echo "=== Conclusion ===\n";
$passingCount = count(array_filter($scores, fn($s) => $s['passes_threshold'] === 'YES'));
if ($passingCount === 0) {
    echo "❌ NO suppliers pass the threshold of $weakTh (70%)\n";
    echo "This is why the candidates array is empty.\n";
    echo "\nPossible solutions:\n";
    echo "1. Add 'Gulf Horizon Medical' as a new supplier\n";
    echo "2. Add it as an alternative name for an existing supplier\n";
    echo "3. Lower the threshold (not recommended)\n";
} else {
    echo "✓ $passingCount supplier(s) pass the threshold\n";
    echo "There might be a bug in CandidateService if they're not returned.\n";
}
