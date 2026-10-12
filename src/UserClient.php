<?php

declare(strict_types=1);

namespace TukuPay;

use TukuPay\Resource\UserAccount;
use TukuPay\Resource\UserKyc;
use TukuPay\Resource\UserOnboarding;
use TukuPay\Resource\UserPayments;
use TukuPay\Resource\UserSms;
use TukuPay\Resource\UserTransactions;
use TukuPay\Resource\UserWallets;

/**
 * Everything done as one signed-in user. Each call carries the app's key and
 * the user's token, and behaves exactly as it does from TukuPay's own app.
 */
final class UserClient
{
    public readonly UserAccount $account;
    public readonly UserOnboarding $onboarding;
    public readonly UserKyc $kyc;
    public readonly UserWallets $wallets;
    public readonly UserPayments $payments;
    public readonly UserTransactions $transactions;
    public readonly UserSms $sms;

    /** @internal */
    public function __construct(Transport $transport, private readonly TokenSource $tokenSource)
    {
        $this->account = new UserAccount($transport, $tokenSource);
        $this->onboarding = new UserOnboarding($transport, $tokenSource);
        $this->kyc = new UserKyc($transport, $tokenSource);
        $this->wallets = new UserWallets($transport, $tokenSource);
        $this->payments = new UserPayments($transport, $tokenSource);
        $this->transactions = new UserTransactions($transport, $tokenSource);
        $this->sms = new UserSms($transport, $tokenSource);
    }

    /**
     * The user's current tokens, to store between requests.
     *
     * @return array{access_token: string, refresh_token: string|null}
     */
    public function tokens(): array
    {
        return [
            'access_token' => $this->tokenSource->accessToken,
            'refresh_token' => $this->tokenSource->refreshToken,
        ];
    }
}
