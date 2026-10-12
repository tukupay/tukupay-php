<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** transactions->wait() gave up before the transaction left `pending`. */
class WaitTimeoutException extends TukuPayException
{
    /** @param array<string, mixed> $transaction the transaction as last read, still pending */
    public function __construct(string $message, public readonly array $transaction)
    {
        parent::__construct($message);
    }
}
