@props([
    'id' => 'data-table',
    'ajax' => null,
    'columns' => [],
    'order' => [[1, 'desc']],
    'pageLength' => 10,
])

<div class="skote-datatable-wrapper">
    <table
        id="{{ $id }}"
        {{ $attributes->class(['table', 'table-bordered', 'dt-responsive', 'nowrap', 'w-100', 'skote-datatable']) }}
        data-datatable="true"
        data-ajax="{{ $ajax }}"
        data-columns="{{ json_encode($columns) }}"
        data-order="{{ json_encode($order) }}"
        data-page-length="{{ $pageLength }}"
    >
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class([$column['className'] ?? '']) @if (! empty($column['width'])) style="width: {{ $column['width'] }}" @endif>
                        {{ $column['title'] ?? $column['data'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
