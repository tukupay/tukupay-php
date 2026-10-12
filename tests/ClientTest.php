<?php

declare(strict_types=1);

namespace TukuPay\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TukuPay\Exception\ApiException;
use TukuPay\Exception\AuthenticationException;
use TukuPay\Exception\ConnectionException;
use TukuPay\Exception\GatewayException;
use TukuPay\Exception\NotFoundException;
use TukuPay\Exception\PermissionDeniedException;
use TukuPay\Exception\RateLimitException;
use TukuPay\Exception\TukuPayException;
use TukuPay\Exception\ValidationException;
use TukuPay\Exception\WaitTimeoutException;
use TukuPay\TukuPay;

final class ClientTest extends TestCase
{
    private const FRESH = ['access_token' => 'new', 'refresh_token' => 'new-refresh', 'token_type' => 'bearer'];

    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    /** @var float[] */
    private array $slept = [];

    /** @param array<int, mixed> $replies @param array<string, mixed> $options */
    private function client(array $replies, array $options = [], string $apiKey = 'sk_test_abc'): TukuPay
    {
        $this->history = [];
        $this->slept = [];
        $stack = HandlerStack::create(new MockHandler($replies));
        $stack->push(Middleware::history($this->history));
        return new TukuPay($apiKey, $options + [
            'http_client' => new Client(['handler' => $stack]),
            'sleep' => function (float $seconds): void {
                $this->slept[] = $seconds;
            },
        ]);
    }

    private static function json(int $status, mixed $body = null, array $headers = []): Response
    {
        return new Response($status, $headers, $body === null ? '' : json_encode($body));
    }

    private static function dropped(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('GET', '/'));
    }

    private function sent(int $index): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    /** @return string[] */
    private function paths(): array
    {
        return array_map(fn (array $entry) => $entry['request']->getUri()->getPath(), $this->history);
    }

    private function caught(callable $call): TukuPayException
    {
        try {
            $call();
        } catch (TukuPayException $error) {
            return $error;
        }
        self::fail('No exception was thrown');
    }

    // construction

    public static function prefixes(): array
    {
        return [['sk_test_x', 'test.api.tuku.money'], ['sk_live_x', 'api.tuku.money']];
    }

    #[DataProvider('prefixes')]
    public function testBaseUrlFollowsTheKeyPrefix(string $apiKey, string $host): void
    {
        $this->client([self::json(200, [])], [], $apiKey)->app->retrieve();
        self::assertSame($host, $this->sent(0)->getUri()->getHost());
        self::assertSame('/api/v1/sdk/app/', $this->sent(0)->getUri()->getPath());
    }

    public function testRefusesAMissingOrUnrecognisedKey(): void
    {
        new TukuPay('legacy-key', ['base_url' => 'http://localhost:8000/']);
        try {
            new TukuPay('');
            self::fail('empty key accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sk_test_ or sk_live_');
        new TukuPay('legacy-key');
    }

    // requests

    public function testAppCallsSendTheKeyAndNoUserToken(): void
    {
        $this->client([self::json(200, [])])->app->wallets->list(['limit' => 5, 'offset' => 10]);
        $request = $this->sent(0);
        self::assertSame('sk_test_abc', $request->getHeaderLine('X-API-Key'));
        self::assertSame('tukupay-php/' . TukuPay::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('limit=5&offset=10', $request->getUri()->getQuery());
    }

    public function testEmptyFiltersAreLeftOutAndValuesSerialised(): void
    {
        $this->client([self::json(200, ['transactions' => [], 'has_more' => false])])->app->transactions->list([
            'status' => 'completed',
            'wallet_id' => null,
            'start_date' => new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        ]);
        parse_str($this->sent(0)->getUri()->getQuery(), $query);
        self::assertSame(['status' => 'completed', 'start_date' => '2026-10-01T00:00:00+00:00'], $query);

        $this->client([self::json(200, [])])->asUser(['access_token' => 'tok'])->payments->methods(true);
        self::assertSame('include_unavailable=true', $this->sent(0)->getUri()->getQuery());
    }

    public function testUserCallsSendKeyAndToken(): void
    {
        $this->client([self::json(200, [])])->asUser(['access_token' => 'tok'])->wallets->list();
        self::assertSame('sk_test_abc', $this->sent(0)->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer tok', $this->sent(0)->getHeaderLine('Authorization'));
        self::assertSame(['/api/v1/sdk/user/wallets/'], $this->paths());
    }

    public function testFilesAreSentAsMultipart(): void
    {
        $this->client([self::json(200, ['id' => 1])])->asUser(['access_token' => 'tok'])->kyc->upload([
            ['name' => 'doc_type', 'contents' => 'national_id'],
            ['name' => 'file', 'contents' => 'abc', 'filename' => 'id.png'],
        ]);
        self::assertStringStartsWith('multipart/form-data', $this->sent(0)->getHeaderLine('Content-Type'));
    }

    public function testJsonBodiesArePassedThrough(): void
    {
        $user = ['phone_number' => '0712345678', 'password' => 'pw', 'accepted_terms' => true];
        $this->client([self::json(200, [])])->auth->register($user);
        self::assertSame($user, json_decode((string) $this->sent(0)->getBody(), true));
    }

    public function testPhoneVerificationCalls(): void
    {
        $tuku = $this->client([self::json(200, ['message' => 'ok']), self::json(200, ['message' => 'sent'])]);
        $tuku->auth->verifyPhone('0712345678', '123456');
        $tuku->auth->sendPhoneVerification('0712345678');
        self::assertSame(['/api/v1/sdk/auth/verify-phone', '/api/v1/sdk/auth/send-phone-verification'], $this->paths());
        self::assertSame(
            ['phone_number' => '0712345678', 'otp_code' => '123456'],
            json_decode((string) $this->sent(0)->getBody(), true),
        );
    }

    // errors

    public static function statuses(): array
    {
        return [
            [401, AuthenticationException::class],
            [403, PermissionDeniedException::class],
            [404, NotFoundException::class],
            [400, ValidationException::class],
            [422, ValidationException::class],
            [500, ApiException::class],
        ];
    }

    #[DataProvider('statuses')]
    public function testStatusMapsToException(int $status, string $class): void
    {
        $tuku = $this->client([self::json($status, ['errors' => 'Nope'])], ['max_retries' => 0]);
        $error = $this->caught(fn () => $tuku->app->retrieve());
        self::assertInstanceOf($class, $error);
        self::assertSame($status, $error->status);
        self::assertSame('Nope', $error->getMessage());
        self::assertSame('Nope', $error->errors);
    }

    public function testFieldErrorsBecomeOneMessageAndAreKept(): void
    {
        $errors = [['field' => 'phone_number', 'message' => 'Invalid phone'], ['message' => 'Bad']];
        $tuku = $this->client([self::json(422, ['errors' => $errors])]);
        $error = $this->caught(fn () => $tuku->auth->login('x', 'y'));
        self::assertSame('phone_number: Invalid phone; Bad', $error->getMessage());
        self::assertSame($errors, $error->errors);
    }

    public function testNetworkFailureIsWrapped(): void
    {
        $tuku = $this->client([self::dropped()], ['max_retries' => 0]);
        $error = $this->caught(fn () => $tuku->app->retrieve());
        self::assertInstanceOf(ConnectionException::class, $error);
        self::assertNull($error->status);
    }

    public function testA200WithStatusFalseThrowsGatewayException(): void
    {
        $tuku = $this->client([self::json(200, ['status' => false, 'message' => 'Invalid OTP'])]);
        $error = $this->caught(fn () => $tuku->asUser(['access_token' => 'tok'])->onboarding->confirm('000000'));
        self::assertInstanceOf(GatewayException::class, $error);
        self::assertSame('Invalid OTP', $error->getMessage());
    }

    // retries

    public function testGetIsRetriedOn503AndDroppedConnection(): void
    {
        $tuku = $this->client([self::json(503), self::dropped(), self::json(200, ['id' => 1])]);
        self::assertSame(['id' => 1], $tuku->app->retrieve());
        self::assertCount(2, $this->slept);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $tuku = $this->client([self::json(503), self::json(503), self::json(503)]);
        self::assertInstanceOf(ApiException::class, $this->caught(fn () => $tuku->app->retrieve()));
        self::assertCount(3, $this->history);
    }

    public function testPostWithoutIdempotencyKeyIsNeverRepeated(): void
    {
        $tuku = $this->client([self::json(503)]);
        self::assertInstanceOf(ApiException::class, $this->caught(fn () => $tuku->app->sms->send(['0700000000'], 'hi')));
        self::assertCount(1, $this->history);
    }

    public function testPaymentIsRepeatedWithTheSameIdempotencyKey(): void
    {
        $tuku = $this->client([self::json(503), self::json(200, ['transaction_id' => 9])]);
        $tuku->asUser(['access_token' => 'tok'])->payments->direct(['pin' => '1234']);
        $first = $this->sent(0)->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        self::assertSame($first, $this->sent(1)->getHeaderLine('Idempotency-Key'));
    }

    public function testCallersIdempotencyKeyIsUsed(): void
    {
        $this->client([self::json(200, [])])->asUser(['access_token' => 'tok'])->payments->direct([], 'order-42');
        self::assertSame('order-42', $this->sent(0)->getHeaderLine('Idempotency-Key'));
    }

    public function testShortRateLimitIsWaitedOutAndLongOneThrown(): void
    {
        $tuku = $this->client([self::json(429, ['errors' => 'Rate limit'], ['Retry-After' => '2']), self::json(200, ['id' => 1])]);
        self::assertSame(['id' => 1], $tuku->app->retrieve());
        self::assertSame([2.0], $this->slept);

        $tuku = $this->client([self::json(429, ['errors' => 'Rate limit'], ['Retry-After' => '45'])]);
        $error = $this->caught(fn () => $tuku->app->retrieve());
        self::assertInstanceOf(RateLimitException::class, $error);
        self::assertSame(45.0, $error->retryAfter);
        self::assertCount(1, $this->history);
    }

    // token refresh

    public function testExpiredTokenIsRenewedOnceAndReported(): void
    {
        $tuku = $this->client([
            self::json(401, ['errors' => 'Token expired']),
            self::json(200, self::FRESH),
            self::json(200, ['id' => 7]),
        ]);
        $seen = [];
        $user = $tuku->asUser(
            ['access_token' => 'old', 'refresh_token' => 'old-refresh'],
            function (array $tokens) use (&$seen): void {
                $seen[] = $tokens;
            },
        );
        self::assertSame(['id' => 7], $user->account->me());
        self::assertSame(['/api/v1/sdk/user/me', '/api/v1/sdk/auth/refresh', '/api/v1/sdk/user/me'], $this->paths());
        self::assertSame('Bearer old-refresh', $this->sent(1)->getHeaderLine('Authorization'));
        self::assertSame('Bearer new', $this->sent(2)->getHeaderLine('Authorization'));
        self::assertSame([self::FRESH], $seen);
        self::assertSame(['access_token' => 'new', 'refresh_token' => 'new-refresh'], $user->tokens());
    }

    public function testNoLoopWhenTheRenewedTokenIsRefusedToo(): void
    {
        $tuku = $this->client([self::json(401), self::json(200, self::FRESH), self::json(401)]);
        $user = $tuku->asUser(['access_token' => 'old', 'refresh_token' => 'r']);
        self::assertInstanceOf(AuthenticationException::class, $this->caught(fn () => $user->account->me()));
        self::assertCount(3, $this->history);
    }

    public function testRefreshTokenIsNotSpentOnARejectedApiKey(): void
    {
        $tuku = $this->client([self::json(401, ['errors' => 'Invalid or revoked API key'])]);
        $user = $tuku->asUser(['access_token' => 'old', 'refresh_token' => 'r']);
        self::assertInstanceOf(AuthenticationException::class, $this->caught(fn () => $user->account->me()));
        self::assertCount(1, $this->history);
    }

    public function testAWrongPinIsNotRepeated(): void
    {
        $tuku = $this->client([self::json(401, ['errors' => 'Invalid PIN. 2 attempts remaining.'])]);
        $user = $tuku->asUser(['access_token' => 'tok', 'refresh_token' => 'r']);
        self::assertInstanceOf(AuthenticationException::class, $this->caught(fn () => $user->account->verifyPin('0000')));
        self::assertCount(1, $this->history);
    }

    public function testThrowsStraightAwayWithoutARefreshToken(): void
    {
        $tuku = $this->client([self::json(401)]);
        $user = $tuku->asUser(['access_token' => 'old']);
        self::assertInstanceOf(AuthenticationException::class, $this->caught(fn () => $user->account->me()));
        self::assertCount(1, $this->history);
    }

    // transactions

    public function testWaitPollsUntilSettled(): void
    {
        $failed = ['id' => 5, 'status' => 'failed', 'transaction_metadata' => ['failure' => ['gateway_code' => '1032']]];
        $tuku = $this->client([
            self::json(200, ['id' => 5, 'status' => 'pending']),
            self::json(200, ['id' => 5, 'status' => 'pending']),
            self::json(200, $failed),
        ]);
        $transaction = $tuku->app->transactions->wait(5, interval: 0.1);
        self::assertCount(3, $this->history);
        self::assertSame('failed', $transaction['status']);
        self::assertSame('1032', TukuPay::failureOf($transaction)['gateway_code']);
    }

    public function testWaitGivesUpAtTheTimeout(): void
    {
        $tuku = $this->client([self::json(200, ['id' => 5, 'status' => 'pending'])]);
        $error = $this->caught(fn () => $tuku->app->transactions->wait(5, timeout: 0));
        self::assertInstanceOf(WaitTimeoutException::class, $error);
        self::assertSame(['id' => 5, 'status' => 'pending'], $error->transaction);
    }

    public function testIterateWalksEveryPage(): void
    {
        $tuku = $this->client([
            self::json(200, ['transactions' => [['id' => 1], ['id' => 2]], 'has_more' => true]),
            self::json(200, ['transactions' => [['id' => 3]], 'has_more' => false]),
        ]);
        $ids = [];
        foreach ($tuku->app->transactions->iterate(['status' => 'completed'], 2) as $transaction) {
            $ids[] = $transaction['id'];
        }
        self::assertSame([1, 2, 3], $ids);
        self::assertSame('status=completed&limit=2&offset=2', $this->sent(1)->getUri()->getQuery());
    }
}
