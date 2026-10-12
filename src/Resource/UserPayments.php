<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class UserPayments extends Resource
{
    /**
     * Payment methods currently enabled.
     *
     * @return array<string, mixed>
     */
    public function methods(?bool $includeUnavailable = null): array
    {
        return $this->call('GET', '/sdk/user/payments/payment-methods', ['query' => ['include_unavailable' => $includeUnavailable]]);
    }

    /** @return array<string, mixed> */
    public function transactionTypes(): array
    {
        return $this->call('GET', '/sdk/user/payments/transaction-types');
    }

    /**
     * Checks a payment without making it: fees, limits and balance.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public function validate(array $payment): array
    {
        return $this->call('POST', '/sdk/user/payments/validate', ['json' => $payment]);
    }

    /**
     * Sends the user the OTP that some payments need in addition to the PIN.
     *
     * @return array<string, mixed>
     */
    public function requestOtp(float $amount, string $transactionType, ?int $sourceWalletId = null): array
    {
        $body = ['amount' => $amount, 'transaction_type' => $transactionType];
        if ($sourceWalletId !== null) {
            $body['source_wallet_id'] = $sourceWalletId;
        }
        return $this->call('POST', '/sdk/user/payments/request-otp', ['json' => $body]);
    }

    /**
     * Makes a payment.
     *
     * Success here means the payment was *requested*: follow with
     * $user->transactions->wait($result['transaction_id']) for the outcome, and
     * show the payer any `customer_message` in the response word for word.
     *
     * $idempotencyKey identifies this payment so a repeat is recognised and not
     * paid twice. One is generated when left out; pass your own (your order id,
     * say) to make a retry from your side safe too.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public function direct(array $payment, ?string $idempotencyKey = null): array
    {
        return $this->call('POST', '/sdk/user/payments/direct', [
            'json' => $payment,
            'idempotency_key' => $idempotencyKey ?? Support::uuid(),
        ]);
    }

    /**
     * Pays several recipients from one wallet.
     *
     * @param array<string, mixed> $transfer
     * @return array<string, mixed>
     */
    public function bulkTransfer(array $transfer, ?string $idempotencyKey = null): array
    {
        return $this->call('POST', '/sdk/user/payments/bulk-transfer', [
            'json' => $transfer,
            'idempotency_key' => $idempotencyKey ?? Support::uuid(),
        ]);
    }
}
