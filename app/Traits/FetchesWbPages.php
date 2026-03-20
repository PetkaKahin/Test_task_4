<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use RuntimeException;

trait FetchesWbPages
{
    /**
     * Fetch a single page from the WB API.
     *
     * @return array{data: array, meta: array{last_page: int}}
     */
    protected function fetchPage(string $path, array $params, int $page): array
    {
        $host = rtrim(config('services.wb.host'), '/');
        $key  = config('services.wb.key');

        if (empty($host) || empty($key)) {
            throw new RuntimeException('WB API host or key is not configured (services.wb.host / services.wb.key)');
        }

        $response = Http::timeout(30)->retry(3, 500)->get($host . $path, array_merge($params, [
            'key'  => $key,
            'page' => $page,
        ]));

        $response->throw();

        $body = $response->json();

        if (!is_array($body) || !array_key_exists('data', $body) || !isset($body['meta']['last_page'])) {
            throw new RuntimeException("Unexpected API response structure for {$path} page {$page}");
        }

        return $body;
    }
}