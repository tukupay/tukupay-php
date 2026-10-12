<?php

declare(strict_types=1);

namespace TukuPay\Resource;

final class UserSms extends Resource
{
    /**
     * Queues an SMS on this user's account and credits.
     *
     * @param string[] $phoneNumbers
     * @return array<string, mixed>
     */
    public function send(array $phoneNumbers, string $message, ?string $senderId = null): array
    {
        $body = ['phone_numbers' => array_values($phoneNumbers), 'message' => $message];
        if ($senderId !== null) {
            $body['sender_id'] = $senderId;
        }
        return $this->call('POST', '/sdk/user/sms/send', ['json' => $body]);
    }

    /**
     * @param array<string, mixed> $filters status, phone_number, search, limit, offset
     * @return array<string, mixed>
     */
    public function outbox(array $filters = []): array
    {
        return $this->call('GET', '/sdk/user/sms/outbox', ['query' => $filters]);
    }

    /**
     * The user's SMS credit balance.
     *
     * @return array<string, mixed>
     */
    public function balance(): array
    {
        return $this->call('GET', '/sdk/user/sms/balance');
    }
}
