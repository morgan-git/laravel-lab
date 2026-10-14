/**
 * Shared helpers for the admin table components. Imported by
 * feed-sources.js and webhook-requests.js.
 */

export function timeAgo(dateString) {
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
