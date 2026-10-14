import { timeAgo } from './helpers';

window.feedSourcesTable = (initialSources) => ({
    sources: initialSources,
    sortColumn: 'provider',
    sortDirection: 'asc',

    sortBy(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
    },

    get sortedSources() {
        return Object.values(this.sources).sort((a, b) => {
            let valueA = a[this.sortColumn];
            let valueB = b[this.sortColumn];

            if (this.sortColumn === 'topic') {
                valueA = a.topic?.name ?? '';
                valueB = b.topic?.name ?? '';
            }

            if (typeof valueA === 'boolean' && typeof valueB === 'boolean') {
                return this.sortDirection === 'asc'
                    ? Number(valueA) - Number(valueB)
                    : Number(valueB) - Number(valueA);
            }

            if (this.sortColumn === 'posts_count') {
                return this.sortDirection === 'asc'
                    ? valueA - valueB
                    : valueB - valueA;
            }

            valueA = String(valueA ?? '').toLowerCase();
            valueB = String(valueB ?? '').toLowerCase();

            const comparison = valueA.localeCompare(valueB);

            return this.sortDirection === 'asc' ? comparison : -comparison;
        });
    },

    sortIcon(column) {
        if (this.sortColumn !== column) {
            return '↕';
        }
        return this.sortDirection === 'asc' ? '↑' : '↓';
    },

    timeAgo,
});
