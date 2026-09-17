<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('tenant.view')
            <li>
                <a class="dropdown-item" href="{{ route('saas.tenants.show', $tenant) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('tenant.update')
            <li>
                <a class="dropdown-item" href="{{ route('saas.tenants.edit', $tenant) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
        @endcan
        @can('toggleStatus', $tenant)
            <li>
                <form action="{{ route('saas.tenants.status', $tenant) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="bx bx-refresh me-1"></i>
                        {{ $tenant->status === \App\Enums\TenantStatus::Active ? 'Suspend' : 'Aktifkan' }}
                    </button>
                </form>
            </li>
        @endcan
        @can('tenant.manage')
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('saas.tenants.destroy', $tenant) }}" method="POST"
                    onsubmit="return confirm('Soft delete klinik ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bx bx-trash me-1"></i> Delete
                    </button>
                </form>
            </li>
        @endcan
    </ul>
</div>
