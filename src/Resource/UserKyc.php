<?php

declare(strict_types=1);

namespace TukuPay\Resource;

final class UserKyc extends Resource
{
    /**
     * Uploads one KYC document.
     *
     * @param array<int, array<string, mixed>> $multipart Guzzle multipart parts
     * @return array<string, mixed>
     */
    public function upload(array $multipart): array
    {
        return $this->call('POST', '/sdk/user/kyc/', ['multipart' => $multipart]);
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->call('GET', '/sdk/user/kyc/');
    }

    /** @return array<string, mixed> */
    public function get(int $documentId): array
    {
        return $this->call('GET', "/sdk/user/kyc/{$documentId}");
    }
}
