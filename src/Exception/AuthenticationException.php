<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** 401: the API key or the user's token was missing, wrong, revoked or expired. */
class AuthenticationException extends TukuPayException
{
}
