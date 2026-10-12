<?php

declare(strict_types=1);

namespace TukuPay\Resource;

final class AppSms extends Resource
{
    /**
     * Queues an SMS on the app owner's account, using the owner's SMS credits.
     *
     * The returned `message_id` identifies the queued batch; it cannot be used
     * with status(). Read outbox() to find the delivered messages.
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
        return $this->call('POST', '/sdk/sms/send', ['json' => $body]);
    }

    /**
     * Messages sent on the app owner's account, newest first.
     *
     * @param array{limit?: int, offset?: int} $page
     * @return array<int, array<string, mixed>>
     */
    public function outbox(array $page = []): array
    {
        return $this->call('GET', '/sdk/sms/outbox', ['query' => $page]);
    }

    /**
     * One message from the outbox, by its outbox `id`.
     *
     * @return array<string, mixed>
     */
    public function status(int $smsId): array
    {
        return $this->call('GET', "/sdk/sms/status/{$smsId}");
    }
}
