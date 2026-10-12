<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/**
 * Base class for every failure. The API reports failures as {"errors": ...};
 * that value is kept untouched on $errors.
 */
class TukuPayException extends \RuntimeException
{
    /**
     * @param int|null $status HTTP status, or null when no response was received
     * @param mixed    $errors the API's `errors` value: a string, a list of field errors, or an array
     * @param mixed    $body   the whole response body, as parsed
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly mixed $errors = null,
        public readonly mixed $body = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
