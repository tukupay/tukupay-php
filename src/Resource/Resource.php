<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\TokenSource;
use TukuPay\Transport;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly Transport $transport, protected readonly ?TokenSource $tokens = null)
    {
    }

    /** @param array<string, mixed> $options */
    protected function call(string $method, string $path, array $options = []): mixed
    {
        return $this->transport->request($method, $path, $options, $this->tokens);
    }
}
