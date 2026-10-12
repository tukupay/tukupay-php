<?php

declare(strict_types=1);

namespace TukuPay;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use TukuPay\Resource\App;
use TukuPay\Resource\Auth;

/**
 * The TukuPay client.
 *
 *     $tuku = new TukuPay(getenv('TUKUPAY_API_KEY'));
 *     $app  = $tuku->app->retrieve();
 */
final class TukuPay
{
    public const VERSION = '0.1.0-beta.1';

    private const BASE_URLS = [
        'sk_test_' => 'https://test.api.tuku.money',
        'sk_live_' => 'https://api.tuku.money',
    ];

    /** App-level endpoints: the API key alone. */
    public readonly App $app;

    /** Register and sign in users. */
    public readonly Auth $auth;

    private readonly Transport $transport;

    /**
     * @param string $apiKey The app's key, sk_test_... or sk_live_.... Keep it on the server.
     * @param array{
     *   base_url?: string,
     *   timeout?: float,
     *   max_retries?: int,
     *   max_retry_delay?: float,
     *   http_client?: ClientInterface,
     *   debug?: callable(string): void,
     *   sleep?: callable(float): void
     * } $options
     *   - base_url: defaults to the environment the key belongs to
     *   - timeout: per-request timeout in seconds (30)
     *   - max_retries: retries after the first attempt (2)
     *   - max_retry_delay: a rate-limit wait longer than this many seconds is thrown, not waited out (20)
     *   - http_client: your own Guzzle client, for proxies or tests
     *   - debug: receives "METHOD path -> status" per call; never keys, tokens, PINs or bodies
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('TukuPay: apiKey is required');
        }
        $baseUrl = $options['base_url'] ?? null;
        if ($baseUrl === null) {
            foreach (self::BASE_URLS as $prefix => $url) {
                if (str_starts_with($apiKey, $prefix)) {
                    $baseUrl = $url;
                    break;
                }
            }
        }
        if (!$baseUrl) {
            throw new \InvalidArgumentException('TukuPay: apiKey must start with sk_test_ or sk_live_, or pass base_url');
        }

        $this->transport = new Transport(
            $apiKey,
            rtrim($baseUrl, '/'),
            (float) ($options['timeout'] ?? 30.0),
            (int) ($options['max_retries'] ?? 2),
            (float) ($options['max_retry_delay'] ?? 20.0),
            $options['http_client'] ?? new Client(),
            $options['sleep'] ?? static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            },
            $options['debug'] ?? null,
        );
        $this->app = new App($this->transport);
        $this->auth = new Auth($this->transport);
    }

    /**
     * A client that acts as one signed-in user.
     *
     * Accepts the result of $tuku->auth->login(), or tokens stored earlier.
     * With a refresh token present, an expired access token is renewed and the
     * call repeated, once; $onTokenRefresh then receives the new pair to store.
     *
     * @param array{access_token: string, refresh_token?: string|null} $session
     * @param (callable(array<string, mixed>): void)|null $onTokenRefresh
     */
    public function asUser(array $session, ?callable $onTokenRefresh = null): UserClient
    {
        if (empty($session['access_token'])) {
            throw new \InvalidArgumentException('TukuPay::asUser: access_token is required');
        }
        $tokens = new TokenSource(
            $session['access_token'],
            $session['refresh_token'] ?? null,
            fn (string $refreshToken): array => $this->auth->refresh($refreshToken),
            $onTokenRefresh,
        );
        return new UserClient($this->transport, $tokens);
    }
    /**
     * The decoded failure block of a failed transaction, if it has one.
     * `gateway_code` is the useful field; `code` is nearly always the generic SP01002.
     *
     * @param array<string, mixed> $transaction
     * @return array<string, mixed>|null
     */
    public static function failureOf(array $transaction): ?array
    {
        $failure = $transaction['transaction_metadata']['failure'] ?? null;
        return is_array($failure) ? $failure : null;
    }
}
