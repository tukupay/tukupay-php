<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class AppUsers extends Resource
{
    /**
     * Users registered under this app.
     *
     * @param array{limit?: int, offset?: int} $page
     * @return array<string, mixed>
     */
    public function list(array $page = []): array
    {
        return $this->call('GET', '/sdk/users/', ['query' => $page]);
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function iterate(int $pageSize = 100): \Generator
    {
        return Support::pages(function (int $limit, int $offset): array {
            $result = $this->list(['limit' => $limit, 'offset' => $offset]);
            return [$result['users'], (bool) $result['has_more']];
        }, $pageSize);
    }
}
