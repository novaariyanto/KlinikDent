@props([
    'hover' => true,
])

<div class="table-responsive">
    <table {{ $attributes->class(['table', 'table-bordered', 'table-hover' => $hover, 'align-middle', 'mb-0']) }}>
        {{ $slot }}
    </table>
</div>
