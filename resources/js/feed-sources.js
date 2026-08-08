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

    timeAgo(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);

        if (isNaN(seconds) || seconds < 0) return 'Just now';

        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60,
            second: 1
        };

        for (const [unit, secondsInUnit] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInUnit);
            if (interval >= 1) {
                return `${interval} ${unit}${interval === 1 ? '' : 's'} ago`;
            }
        }
        return 'Just now';
    }
});
