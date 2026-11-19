<?php

namespace App\Traits;

use App\Models\Company;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            // Prevent recursion when resolving the authenticated user
            static $resolving = false;
            if ($resolving) {
                return;
            }

            $resolving = true;
            try {
                $tenantId = null;

                if (auth()->check()) {
                    $user = auth()->user();
                    if ($user) {
                        if ($user->isSuperAdmin()) {
                            // When a tenant is set explicitly (the public scoreboard resolves
                            // one from the company slug) it wins, so the board shows the same
                            // rows to everyone. Elsewhere a superadmin still sees everything.
                            $explicitTenant = TenantManager::getTenantId();
                            if ($explicitTenant === null) {
                                return;
                            }
                            $tenantId = $explicitTenant;
                        } else {
                            $tenantId = $user->company_id;
                        }
                    }
                } else {
                    // For the User model, do not apply scope when the user is not authenticated (guest).
                    // This is required to allow the login/authentication query to run and find the user.
                    if (static::class === \App\Models\User::class) {
                        return;
                    }
                    
                    $tenantId = TenantManager::getTenantId();
                }

                if ($tenantId !== null) {
                    $builder->where($builder->getModel()->getTable() . '.company_id', $tenantId);
                } else {
                    $builder->whereRaw('1 = 0');
                }
            } finally {
                $resolving = false;
            }
        });

        static::creating(function ($model) {
            if (!empty($model->company_id)) {
                return;
            }

            // An explicitly set tenant wins, then fall back to the company of the
            // user doing the work. Superadmins are included here too, otherwise
            // anything they create is left orphaned with a null company_id.
            $tenantId = TenantManager::getTenantId();

            if ($tenantId === null && auth()->check()) {
                $tenantId = auth()->user()->company_id;
            }

            if ($tenantId !== null) {
                $model->company_id = $tenantId;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
