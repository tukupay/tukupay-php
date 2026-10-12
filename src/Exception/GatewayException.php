<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** The payment gateway refused the request although the API answered HTTP 200, e.g. a wrong onboarding OTP. */
class GatewayException extends TukuPayException
{
}
