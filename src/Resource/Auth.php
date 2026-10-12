<?php

declare(strict_types=1);

namespace TukuPay\Resource;

/** Registering and signing in. These need the API key but no user yet. */
final class Auth extends Resource
{
    /**
     * Registers a user under this key's app.
     *
     * Leave `app_id` out: the app comes from the key. The user receives an OTP
     * by SMS; pass it to verifyPhone().
     *
     * @param array{phone_number: string, password: string, accepted_terms: bool, type?: string} $user
     * @return array<string, mixed>
     */
    public function register(array $user): array
    {
        return $this->call('POST', '/sdk/auth/register', ['json' => $user]);
    }

    /**
     * Confirms a new user's phone with the OTP they received after registering.
     * A user cannot sign in until this is done.
     *
     * @return array<string, mixed>
     */
    public function verifyPhone(string $phoneNumber, string $otpCode): array
    {
        return $this->call('POST', '/sdk/auth/verify-phone', [
            'json' => ['phone_number' => $phoneNumber, 'otp_code' => $otpCode],
        ]);
    }

    /**
     * Sends the phone verification OTP again, for a user who did not get it.
     *
     * @return array<string, mixed>
     */
    public function sendPhoneVerification(string $phoneNumber): array
    {
        return $this->call('POST', '/sdk/auth/send-phone-verification', ['json' => ['phone_number' => $phoneNumber]]);
    }

    /**
     * Signs a user in. Their phone must be verified. Pass the result to $tuku->asUser().
     *
     * @return array{access_token: string, refresh_token: string, token_type?: string}
     */
    public function login(string $phoneNumber, string $password): array
    {
        return $this->call('POST', '/sdk/auth/login', [
            'json' => ['phone_number' => $phoneNumber, 'password' => $password],
        ]);
    }

    /**
     * Exchanges a refresh token for a new pair. The old refresh token stops working.
     *
     * @return array{access_token: string, refresh_token: string, token_type?: string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->call('POST', '/sdk/auth/refresh', [
            'headers' => ['Authorization' => "Bearer {$refreshToken}"],
        ]);
    }
}
