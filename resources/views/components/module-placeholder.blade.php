@props([
    'icon' => 'bx bx-cube',
    'title' => 'Modul',
    'description' => 'Modul ini sedang dalam tahap pengembangan.',
])

<x-card>
    <div class="text-center py-5 px-3">
        <div class="avatar-lg mx-auto mb-4">
            <span class="avatar-title rounded-circle bg-soft-primary text-primary">
                <i class="{{ $icon }} font-size-24"></i>
            </span>
        </div>
        <h4 class="mb-2">{{ $title }}</h4>
        <p class="text-muted mb-2">{{ $description }}</p>
        <p class="text-muted mb-0">Modul ini sedang dalam tahap pengembangan.</p>
        <div class="mt-3">
            <span class="badge badge-soft-warning">Coming Soon</span>
        </div>
    </div>
</x-card>
