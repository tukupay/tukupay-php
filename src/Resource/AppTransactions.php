<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class AppTransactions extends Resource
{
    /**
     * Transactions made through this app, newest first.
     *
     * @param array<string, mixed> $filters transaction_type, status, wallet_id, start_date, end_date, limit, offset
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->call('GET', '/sdk/transactions/', ['query' => $filters]);
    }

    /**
     * One of the app's transactions. Never cached, so safe to poll.
     *
     * @return array<string, mixed>
     */
    public function get(int $transactionId): array
    {
        return $this->call('GET', "/sdk/transactions/{$transactionId}");
    }

    /**
     * Polls until the transaction is `completed` or `failed`.
     *
     * @return array<string, mixed>
     */
    public function wait(int $transactionId, float $timeout = 120.0, float $interval = 3.0): array
    {
        return Support::waitForTransaction(
            fn (): array => $this->get($transactionId),
            $timeout,
            $interval,
            fn (float $seconds) => $this->transport->sleep($seconds),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(array $filters = [], int $pageSize = 100): \Generator
    {
        return Support::pages(function (int $limit, int $offset) use ($filters): array {
            $result = $this->list($filters + ['limit' => $limit, 'offset' => $offset]);
            return [$result['transactions'], (bool) $result['has_more']];
        }, $pageSize);
    }
}
