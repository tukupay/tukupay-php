<?php

declare(strict_types=1);

namespace TukuPay;

/**
 * A user's tokens, and how to renew them. Shared by every call made as that user.
 *
 * @internal
 */
final class TokenSource
{
    /** @var (callable(array<string, mixed>): void)|null */
    private $onTokenRefresh;

    /**
     * @param \Closure(string): array<string, mixed> $refresh
     * @param (callable(array<string, mixed>): void)|null $onTokenRefresh
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        private readonly \Closure $refresh,
        ?callable $onTokenRefresh = null,
    ) {
        $this->onTokenRefresh = $onTokenRefresh;
    }

    public function renew(): void
    {
        $tokens = ($this->refresh)((string) $this->refreshToken);
        $this->accessToken = $tokens['access_token'];
        $this->refreshToken = $tokens['refresh_token'] ?? null;
        if ($this->onTokenRefresh !== null) {
            ($this->onTokenRefresh)($tokens);
        }
    }
}
