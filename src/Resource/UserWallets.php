<?php

declare(strict_types=1);

namespace TukuPay\Resource;

final class UserWallets extends Resource
{
    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->call('GET', '/sdk/user/wallets/');
    }

    /** @return array<string, mixed> */
    public function get(int $walletId): array
    {
        return $this->call('GET', "/sdk/user/wallets/{$walletId}");
    }

    /**
     * Adds another wallet for a user who already has one.
     *
     * @param array<string, mixed> $wallet
     * @return array<string, mixed>
     */
    public function create(array $wallet): array
    {
        return $this->call('POST', '/sdk/user/wallets/', ['json' => $wallet]);
    }
}
