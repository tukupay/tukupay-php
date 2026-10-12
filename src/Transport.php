<?php

declare(strict_types=1);

namespace TukuPay;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use TukuPay\Exception\ConnectionException;
use TukuPay\Exception\RateLimitException;

/** @internal */
final class Transport
{
    private const API_PREFIX = '/api/v1';
    private const RETRYABLE_STATUSES = [502, 503, 504];

    /** @var callable(float): void */
    private $sleep;

    /** @var (callable(string): void)|null */
    private $debug;

    /**
     * @param callable(float): void $sleep receives seconds
     * @param (callable(string): void)|null $debug
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly float $timeout,
        private readonly int $maxRetries,
        private readonly float $maxRetryDelay,
        private readonly ClientInterface $http,
        callable $sleep,
        ?callable $debug = null,
    ) {
        $this->sleep = $sleep;
        $this->debug = $debug;
    }

    public function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /**
     * @param array{
     *   query?: array<string, mixed>,
     *   json?: mixed,
     *   multipart?: array<int, array<string, mixed>>,
     *   headers?: array<string, string>,
     *   idempotency_key?: string|null,
     *   timeout?: float
     * } $options
     */
    public function request(string $method, string $path, array $options = [], ?TokenSource $tokens = null): mixed
    {
        $verb = strtoupper($method);
        $url = $this->baseUrl . self::API_PREFIX . (str_starts_with($path, '/') ? $path : '/' . $path);
        $idempotencyKey = $options['idempotency_key'] ?? null;
        // A POST is only safe to send twice when the endpoint will recognise the repeat.
        $repeatable = $verb === 'GET' || $verb === 'HEAD' || !empty($idempotencyKey);

        $attempt = 0;
        $refreshed = false;

        while (true) {
            $headers = array_merge(
                ['Accept' => 'application/json', 'User-Agent' => 'tukupay-php/' . TukuPay::VERSION],
                $options['headers'] ?? [],
                ['X-API-Key' => $this->apiKey],
            );
            if ($tokens !== null && !isset($headers['Authorization'])) {
                $headers['Authorization'] = 'Bearer ' . $tokens->accessToken;
            }
            if (!empty($idempotencyKey)) {
                $headers['Idempotency-Key'] = $idempotencyKey;
            }

            $guzzle = [
                'headers' => $headers,
                'query' => self::cleanQuery($options['query'] ?? []),
                'http_errors' => false,
                'timeout' => $options['timeout'] ?? $this->timeout,
            ];
            if (isset($options['multipart'])) {
                $guzzle['multipart'] = $options['multipart'];
            } elseif (array_key_exists('json', $options)) {
                $guzzle['json'] = $options['json'];
            }

            try {
                $response = $this->http->request($verb, $url, $guzzle);
            } catch (GuzzleException $cause) {
                if ($repeatable && $attempt < $this->maxRetries) {
                    $this->sleep(self::backoff($attempt++));
                    continue;
                }
                $timedOut = $cause instanceof ConnectException && stripos($cause->getMessage(), 'timed out') !== false;
                throw new ConnectionException(
                    $timedOut ? "Request to {$verb} {$path} timed out" : "Could not reach TukuPay for {$verb} {$path}",
                    previous: $cause,
                );
            }

            $status = $response->getStatusCode();
            if ($this->debug !== null) {
                ($this->debug)("{$verb} {$path} -> {$status}");
            }
            $body = self::parseBody((string) $response->getBody());
            if ($status >= 200 && $status < 300) {
                return $body;
            }

            // An expired access token is renewed once. Other things also answer 401
            // and must not be repeated: a rejected API key, and above all a wrong PIN,
            // where a silent second try would cost the user another attempt before lockout.
            if (
                $status === 401
                && $tokens !== null
                && $tokens->refreshToken
                && !$refreshed
                && self::isTokenProblem(Errors::messageFromBody($body, ''))
            ) {
                $refreshed = true;
                $tokens->renew();
                continue;
            }

            $error = Errors::fromResponse($status, $body, $response->getHeaderLine('Retry-After') ?: null);
            $retryable = $status === 429 || ($repeatable && in_array($status, self::RETRYABLE_STATUSES, true));
            if ($retryable && $attempt < $this->maxRetries) {
                $wait = $error instanceof RateLimitException && $error->retryAfter !== null
                    ? $error->retryAfter
                    : self::backoff($attempt);
                if ($wait <= $this->maxRetryDelay) {
                    $attempt++;
                    $this->sleep($wait);
                    continue;
                }
            }
            throw $error;
        }
    }

    // True for the API's wording for a missing, expired, revoked or malformed access token.
    private static function isTokenProblem(string $message): bool
    {
        if ($message === '') {
            return true;
        }
        return preg_match('/authentication credentials|token|not authenticated|authentication required/i', $message) === 1
            && preg_match('/api key|refresh token|\\bpin\\b/i', $message) !== 1;
    }

    // Exponential backoff with jitter: 0.5s, 1s, 2s ... each scaled by 0.5-1.
    private static function backoff(int $attempt): float
    {
        return 0.5 * (2 ** $attempt) * (0.5 + mt_rand() / mt_getrandmax() / 2);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    private static function cleanQuery(array $query): array
    {
        $cleaned = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }
            $cleaned[$name] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                default => (string) $value,
            };
        }
        return $cleaned;
    }

    private static function parseBody(string $text): mixed
    {
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $text;
    }
}
