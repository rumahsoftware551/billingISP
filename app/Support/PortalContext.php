<?php

namespace App\Support;

use App\Models\CustomerPortalAccount;

final class PortalContext
{
    public static function account(): CustomerPortalAccount
    {
        return app(CustomerPortalAccount::class);
    }

    public static function customerId(): int
    {
        return (int) self::account()->customer_id;
    }

    public static function tenantId(): string
    {
        return (string) self::account()->tenant_id;
    }

    public static function accountId(): int
    {
        return (int) self::account()->id;
    }
}
