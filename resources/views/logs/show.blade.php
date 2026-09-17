@extends('layouts.app')

@section('title', 'Log Detail')

@section('content')
    <x-page-header title="Log Detail" :breadcrumb="['Activity Logs' => route('logs.index'), '#'.$log->id => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Activity">
                <x-table>
                    <tbody>
                        <tr>
                            <th width="180">Time</th>
                            <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>User</th>
                            <td>
                                @if ($log->user)
                                    {{ $log->user->name }}
                                    <small class="text-muted">({{ $log->user->email }})</small>
                                @else
                                    <span class="text-muted">System / Guest</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Module</th>
                            <td><span class="badge badge-soft-primary">{{ $log->moduleLabel() }}</span></td>
                        </tr>
                        <tr>
                            <th>Event</th>
                            <td><span class="{{ $log->eventBadgeClass() }}">{{ $log->eventLabel() }}</span></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td>{{ $log->description }}</td>
                        </tr>
                        <tr>
                            <th>Subject</th>
                            <td>
                                @if ($log->subject)
                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    @if ($log->subject->getAttribute('name') || $log->subject->getAttribute('title'))
                                        — {{ $log->subject->getAttribute('name') ?? $log->subject->getAttribute('title') }}
                                    @endif
                                @elseif ($log->subject_type)
                                    <span class="text-muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }} (deleted)</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td>{{ $log->ip_address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Agent</th>
                            <td class="text-break">{{ $log->user_agent ?: '-' }}</td>
                        </tr>
                    </tbody>
                </x-table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('logs.delete')
                        <form action="{{ route('logs.destroy', $log) }}" method="POST"
                            onsubmit="return confirm('Delete this log entry?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger" icon="bx bx-trash">Delete</x-button>
                        </form>
                    @endcan
                    <x-button href="{{ route('logs.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Properties">
                @if ($log->properties)
                    <pre class="bg-light border rounded p-3 mb-0 small text-wrap">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="text-muted mb-0">No additional properties.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
