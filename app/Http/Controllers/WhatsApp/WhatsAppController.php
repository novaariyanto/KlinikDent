<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\WhatsApp\WhatsAppProvisioningService;

abstract class WhatsAppController extends Controller
{
    public function __construct(protected WhatsAppProvisioningService $provisioning) {}

    protected function tenantId(): int
    {
        $tenantId = current_tenant_id();
        abort_unless($tenantId, 403);

        return $tenantId;
    }

    protected function tenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->tenantId());
    }

    protected function provision(): int
    {
        $tenantId = $this->tenantId();
        $this->provisioning->ensureTenant($tenantId);

        return $tenantId;
    }
}
