<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** 429: the key's per-minute limit was passed. */
class RateLimitException extends TukuPayException
{
    /** @param float|null $retryAfter seconds until the limit resets, from Retry-After */
    public function __construct(
        string $message,
        ?int $status = null,
        mixed $errors = null,
        mixed $body = null,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errors, $body);
    }
}
