<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Support;

final class UserOnboarding extends Resource
{
    /**
     * Step 1: starts wallet onboarding. The user receives an OTP by SMS.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public function initial(array $details): array
    {
        return Support::raiseIfRefused($this->call('POST', '/sdk/user/onboarding/initial', ['json' => $details]));
    }

    /**
     * Step 2: confirms with the OTP. A wrong OTP throws GatewayException.
     *
     * @return array<string, mixed>
     */
    public function confirm(string $otp, ?string $requestId = null): array
    {
        $body = ['otp' => $otp];
        if ($requestId !== null) {
            $body['request_id'] = $requestId;
        }
        return Support::raiseIfRefused($this->call('POST', '/sdk/user/onboarding/confirm', ['json' => $body]));
    }

    /**
     * Step 3: uploads the identity documents.
     *
     * @param array<int, array<string, mixed>> $multipart Guzzle multipart parts
     * @return array<string, mixed>
     */
    public function kyc(array $multipart): array
    {
        return Support::raiseIfRefused(
            $this->call('POST', '/sdk/user/onboarding/kyc', ['multipart' => $multipart]),
        );
    }

    /**
     * How far the user is through wallet onboarding and identity checks.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->call('GET', '/sdk/user/onboarding/status');
    }
}
