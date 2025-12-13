<?php
declare(strict_types=1);

namespace App\Support;

class Settings
{
    private string $path;
    private array $defaults = [
        'MATCH_AUTO_THRESHOLD' => Config::MATCH_AUTO_THRESHOLD, // 0.90
        'MATCH_REVIEW_THRESHOLD' => Config::MATCH_REVIEW_THRESHOLD, // 0.70
        'MATCH_WEAK_THRESHOLD' => 0.70, // Synced with Review Threshold to avoid hiding results
        'CONFLICT_DELTA' => Config::CONFLICT_DELTA, // 0.1
        'WEIGHT_OFFICIAL' => 1.0,
        'WEIGHT_ALT_CONFIRMED' => 0.95, // Increased confidence for manual aliases
        'WEIGHT_ALT_LEARNING' => 0.75,
        'WEIGHT_FUZZY' => 0.80, // Increased to not penalize typos too harshly
        'CANDIDATES_LIMIT' => 20,
    ];

    public function __construct(string $path = '')
    {
        $this->path = $path ?: storage_path('settings.json');
    }

    public function all(): array
    {
        if (!file_exists($this->path)) {
            return $this->defaults;
        }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data)) {
            return $this->defaults;
        }
        return array_merge($this->defaults, $data);
    }

    public function save(array $data): array
    {
        $current = $this->all();
        $merged = array_merge($current, $data);
        file_put_contents($this->path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $merged;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }
}
