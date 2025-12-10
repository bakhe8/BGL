<?php
declare(strict_types=1);

namespace App\Support;

class Settings
{
    private string $path;
    private array $defaults = [
        'MATCH_AUTO_THRESHOLD' => Config::MATCH_AUTO_THRESHOLD,
        'MATCH_REVIEW_THRESHOLD' => Config::MATCH_REVIEW_THRESHOLD,
        'CONFLICT_DELTA' => Config::CONFLICT_DELTA,
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
        $data = json_decode((string)file_get_contents($this->path), true);
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
}
