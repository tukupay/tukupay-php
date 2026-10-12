<?php

declare(strict_types=1);

namespace TukuPay;

use TukuPay\Exception\GatewayException;
use TukuPay\Exception\WaitTimeoutException;

/** @internal */
final class Support
{
    /**
     * Reads a transaction until it leaves `pending`. Returns for `completed`
     * and `failed` alike, so check `status` on the result.
     *
     * @param callable(): array<string, mixed> $read
     * @param callable(float): void $sleep
     * @return array<string, mixed>
     */
    public static function waitForTransaction(callable $read, float $timeout, float $interval, callable $sleep): array
    {
        $deadline = microtime(true) + $timeout;
        while (true) {
            $transaction = $read();
            if (($transaction['status'] ?? null) !== 'pending') {
                return $transaction;
            }
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $id = $transaction['id'] ?? '?';
                throw new WaitTimeoutException(
                    "Transaction {$id} was still pending after {$timeout} s",
                    $transaction,
                );
            }
            $sleep(min($interval, $remaining));
        }
    }

    /**
     * Walks a limit/offset list page by page.
     *
     * @param callable(int, int): array{0: array<int, array<string, mixed>>, 1: bool} $fetchPage
     * @return \Generator<int, array<string, mixed>>
     */
    public static function pages(callable $fetchPage, int $pageSize): \Generator
    {
        for ($offset = 0; ; $offset += $pageSize) {
            [$items, $hasMore] = $fetchPage($pageSize, $offset);
            foreach ($items as $item) {
                yield $item;
            }
            if (!$hasMore || $items === []) {
                return;
            }
        }
    }

    // Some gateway calls answer a refusal with HTTP 200 and {"status": false}.
    public static function raiseIfRefused(mixed $body): mixed
    {
        if (is_array($body) && ($body['status'] ?? null) === false) {
            throw new GatewayException(
                Errors::messageFromBody($body, 'The payment gateway refused the request'),
                200,
                null,
                $body,
            );
        }
        return $body;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
