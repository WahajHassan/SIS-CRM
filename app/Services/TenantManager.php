<?php

namespace App\Services;

use App\Models\Company;

class TenantManager
{
    protected static ?Company $currentTenant = null;

    public static function setTenant(Company $tenant)
    {
        self::$currentTenant = $tenant;
    }

    public static function getTenant(): ?Company
    {
        return self::$currentTenant;
    }

    public static function getTenantId(): ?int
    {
        return self::$currentTenant?->id;
    }
}
