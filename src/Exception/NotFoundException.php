<?php

declare(strict_types=1);

namespace TukuPay\Exception;

/** 404: not found, or it belongs to another app. The API does not say which. */
class NotFoundException extends TukuPayException
{
}
