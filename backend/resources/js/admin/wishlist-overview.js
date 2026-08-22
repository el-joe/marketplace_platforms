/**
 * wishlist-overview.js — Admin Wishlist Overview (read-only)
 *
 * Handles DataTable initialisation for the wishlist groups index page.
 */
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const table = document.getElementById('wishlist-groups-table');
    if (table && window.wishlistGroupsTableUrl) {
        const dt = $(table).DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.wishlistGroupsTableUrl,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data(d) {
                    d.is_public = document.getElementById('filter-is-public')?.value || '';
                    d.date_from = document.getElementById('filter-date-from')?.value || '';
                    d.date_to = document.getElementById('filter-date-to')?.value || '';
                },
            },
            columns: [
                { data: 'customer' },
                { data: 'name' },
                { data: 'is_default' },
                { data: 'is_public' },
                { data: 'items_count' },
                { data: 'created_at' },
                { data: 'actions', orderable: false, searchable: false, className: 'text-right' },
            ],
            order: [[5, 'desc']],
            dom: 'rt<"flex items-center justify-between mt-3"ip>',
            pageLength: 25,
            language: { search: '', searchPlaceholder: t('shared.table_search_placeholder') },
        });

        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => dt.search(searchInput.value).draw(), 400);
            });
        }

        document.getElementById('filter-is-public')?.addEventListener('change', () => dt.draw());
        document.getElementById('filter-date-from')?.addEventListener('change', () => dt.draw());
        document.getElementById('filter-date-to')?.addEventListener('change', () => dt.draw());
        document.getElementById('clear-filters')?.addEventListener('click', () => {
            document.getElementById('filter-form')?.reset();
            if (searchInput) dt.search('');
            dt.draw();
        });
    }
});
