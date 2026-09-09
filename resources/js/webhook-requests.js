window.webhookRequestsTable = (initialRequests) => ({
    requests: initialRequests,
    sortColumn: 'created_at',
    sortDirection: 'desc',
    statusFilter: '',
    providerFilter: '',
    expandedId: null,

    sortBy(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
    },

    // Pulled from the loaded rows rather than hardcoded, so the filter
    // dropdowns always match whatever status/provider values actually
    // exist without needing to keep an enum list in sync.
    get statusOptions() {
        return [...new Set(this.requests.map((r) => r.status))].sort();
    },

    get providerOptions() {
        return [...new Set(this.requests.map((r) => r.provider))].sort();
    },

    get filteredRequests() {
        return this.requests.filter((r) => {
            if (this.statusFilter && r.status !== this.statusFilter) return false;
            if (this.providerFilter && r.provider !== this.providerFilter) return false;
            return true;
        });
    },

    get sortedRequests() {
        return [...this.filteredRequests].sort((a, b) => {
            let valueA = a[this.sortColumn];
            let valueB = b[this.sortColumn];

            if (this.sortColumn === 'created_at') {
                valueA = new Date(valueA ?? 0).getTime();
                valueB = new Date(valueB ?? 0).getTime();
                return this.sortDirection === 'asc' ? valueA - valueB : valueB - valueA;
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

    toggleExpanded(id) {
        this.expandedId = this.expandedId === id ? null : id;
    },

    formatPayload(payload) {
        if (payload === null || payload === undefined) return 'null';
        return JSON.stringify(payload, null, 2);
    },

    // Same as feed-sources.js's timeAgo. Worth pulling both into a
    // shared helper file at some point instead of keeping two copies.
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
