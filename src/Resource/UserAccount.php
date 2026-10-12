<?php

declare(strict_types=1);

namespace TukuPay\Resource;

final class UserAccount extends Resource
{
    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->call('GET', '/sdk/user/me');
    }

    /**
     * Sets the user's payment PIN. Required before any wallet debit.
     *
     * @return array<string, mixed>
     */
    public function setupPin(string $pin, string $confirmPin): array
    {
        return $this->call('POST', '/sdk/user/setup-pin', ['json' => ['pin' => $pin, 'confirm_pin' => $confirmPin]]);
    }

    /** @return array<string, mixed> */
    public function verifyPin(string $pin): array
    {
        return $this->call('POST', '/sdk/user/verify-pin', ['json' => ['pin' => $pin]]);
    }

    /**
     * Ends the session. The tokens stop working.
     *
     * @return array<string, mixed>
     */
    public function logout(): array
    {
        return $this->call('POST', '/sdk/auth/logout');
    }
}
