@php
    $status = $user->status;
@endphp

<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('users.view')
            <li>
                <a class="dropdown-item" href="{{ route('users.show', $user) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('users.edit')
            <li>
                <a class="dropdown-item" href="{{ route('users.edit', $user) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
            @if (auth()->id() !== $user->id)
                <li>
                    <form action="{{ route('users.toggle-status', $user) }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="bx bx-refresh me-1"></i>
                            {{ $status === \App\Enums\UserStatus::Active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                </li>
            @endif
        @endcan
        @can('users.delete')
            @if (auth()->id() !== $user->id)
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('users.destroy', $user) }}" method="POST"
                        onsubmit="return confirm('Delete this user?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bx bx-trash me-1"></i> Delete
                        </button>
                    </form>
                </li>
            @endif
        @endcan
    </ul>
</div>
