<?php

declare(strict_types=1);

namespace TukuPay\Resource;

use TukuPay\Transport;

/**
 * App-level endpoints (/sdk/*): the only ones that work with the API key
 * alone. They cover the app as a whole and are read-only, apart from SMS.
 */
final class App extends Resource
{
    public readonly AppWallets $wallets;
    public readonly AppTransactions $transactions;
    public readonly AppUsers $users;
    public readonly AppSms $sms;

    /** @internal */
    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->wallets = new AppWallets($transport);
        $this->transactions = new AppTransactions($transport);
        $this->users = new AppUsers($transport);
        $this->sms = new AppSms($transport);
    }

    /**
     * The app this key belongs to. Also the quickest check that a key works.
     *
     * @return array<string, mixed>
     */
    public function retrieve(): array
    {
        return $this->call('GET', '/sdk/app/');
    }
}
