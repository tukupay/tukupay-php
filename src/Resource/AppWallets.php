<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class AppWallets extends Resource
{
    /**
     * Wallets that belong to this app.
     *
     * @param array{limit?: int, offset?: int} $page
     * @return array<int, array<string, mixed>>
     */
    public function list(array $page = []): array
    {
        return $this->call('GET', '/sdk/wallets/', ['query' => $page]);
    }

    /**
     * One of the app's wallets. Another app's wallet throws NotFoundException.
     *
     * @return array<string, mixed>
     */
    public function get(int $walletId): array
    {
        return $this->call('GET', "/sdk/wallets/{$walletId}");
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function iterate(int $pageSize = 100): \Generator
    {
        return Support::pages(function (int $limit, int $offset): array {
            $items = $this->list(['limit' => $limit, 'offset' => $offset]);
            return [$items, count($items) === $limit];
        }, $pageSize);
    }
}
