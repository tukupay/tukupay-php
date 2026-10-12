<?php

declare(strict_types=1);

namespace TukuPay;

use TukuPay\Exception\ApiException;
use TukuPay\Exception\AuthenticationException;
use TukuPay\Exception\NotFoundException;
use TukuPay\Exception\PermissionDeniedException;
use TukuPay\Exception\RateLimitException;
use TukuPay\Exception\TukuPayException;
use TukuPay\Exception\ValidationException;

/** @internal */
final class Errors
{
    private static function describe(mixed $errors): ?string
    {
        if (is_string($errors)) {
            return $errors;
        }
        if (!is_array($errors)) {
            return null;
        }
        if (array_is_list($errors)) {
            $parts = [];
            foreach ($errors as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                    continue;
                }
                if (is_array($item)) {
                    $text = $item['message'] ?? $item['msg'] ?? $item['detail'] ?? null;
                    $field = $item['field'] ?? (is_array($item['loc'] ?? null) ? implode('.', $item['loc']) : null);
                    if (is_string($text)) {
                        $parts[] = $field !== null ? "{$field}: {$text}" : $text;
                        continue;
                    }
                }
                $parts[] = (string) json_encode($item);
            }
            return implode('; ', $parts);
        }
        $text = $errors['message'] ?? $errors['detail'] ?? null;
        return is_string($text) ? $text : (string) json_encode($errors);
    }

    /** Pulls the human-readable text out of a response body. */
    public static function messageFromBody(mixed $body, string $fallback): string
    {
        if (is_array($body)) {
            foreach (['errors', 'detail', 'message'] as $key) {
                $text = self::describe($body[$key] ?? null);
                if ($text !== null && $text !== '') {
                    return $text;
                }
            }
            return $fallback;
        }
        if (is_string($body) && trim($body) !== '') {
            return substr(trim($body), 0, 300);
        }
        return $fallback;
    }

    public static function fromResponse(int $status, mixed $body, ?string $retryAfter): TukuPayException
    {
        $errors = is_array($body) ? ($body['errors'] ?? null) : null;
        $message = self::messageFromBody($body, "Request failed with HTTP {$status}");

        if ($status === 429) {
            $seconds = is_numeric($retryAfter) && (float) $retryAfter > 0 ? (float) $retryAfter : null;
            return new RateLimitException($message, $status, $errors, $body, $seconds);
        }
        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status === 400, $status === 422 => ValidationException::class,
            $status >= 500 => ApiException::class,
            default => TukuPayException::class,
        };
        return new $class($message, $status, $errors, $body);
    }
}
