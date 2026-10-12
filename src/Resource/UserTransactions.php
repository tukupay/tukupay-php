<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class UserTransactions extends Resource
{
    /**
     * The user's transactions.
     *
     * @param array<string, mixed> $filters transaction_id, transaction_type, status, start_date, end_date,
     *                                      min_amount, max_amount, wallet_id, search, limit, offset
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->call('GET', '/sdk/user/transactions/', ['query' => $filters]);
    }

    /**
     * One transaction. Never cached, so safe to poll.
     *
     * @return array<string, mixed>
     */
    public function get(int $transactionId): array
    {
        return $this->call('GET', "/sdk/user/transactions/{$transactionId}");
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
