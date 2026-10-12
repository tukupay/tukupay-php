<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** 403: the credentials are valid but not allowed to do this. */
class PermissionDeniedException extends TukuPayException
{
}
