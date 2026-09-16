import './bootstrap';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (window.axios && csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const defaultLanguage = {
    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
    emptyTable: 'No data available',
    zeroRecords: 'No matching records found',
    search: '',
    searchPlaceholder: 'Search...',
    lengthMenu: '_MENU_ entries',
    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
    infoEmpty: 'Showing 0 to 0 of 0 entries',
    infoFiltered: '(filtered from _MAX_ total entries)',
    paginate: {
        first: 'First',
        last: 'Last',
        next: '<i class="mdi mdi-chevron-right"></i>',
        previous: '<i class="mdi mdi-chevron-left"></i>',
    },
};

window.initServerDataTable = function initServerDataTable(selector, options = {}) {
    const table = typeof selector === 'string' ? document.querySelector(selector) : selector;

    if (! table || DataTable.isDataTable(table)) {
        return null;
    }

    const { ajax, columns: optionColumns, ...rest } = options;
    const sourceColumns = optionColumns ?? JSON.parse(table.dataset.columns || '[]');
    const columns = sourceColumns.map((column) => ({
        data: column.data,
        name: column.name ?? column.data,
        orderable: column.orderable !== false,
        searchable: column.searchable !== false,
        className: column.className ?? '',
        defaultContent: column.defaultContent ?? '',
    }));

    const ajaxUrl = typeof ajax === 'string' ? ajax : (ajax?.url ?? table.dataset.ajax);

    return new DataTable(table, {
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        columns,
        order: rest.order ?? JSON.parse(table.dataset.order || '[[1, "desc"]]'),
        pageLength: Number(rest.pageLength ?? table.dataset.pageLength ?? 10),
        lengthMenu: [10, 25, 50, 100],
        language: defaultLanguage,
        ...rest,
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
            ...(typeof ajax === 'object' ? ajax : {}),
        },
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('table[data-datatable]').forEach((table) => {
        window.initServerDataTable(table);
    });
});
