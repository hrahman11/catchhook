const relativeTime = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
    ['second', 1],
];

export function timeAgo(iso: string, now: number = Date.now()): string {
    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);

    if (Math.abs(seconds) < 5) {
        return 'just now';
    }

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size || unit === 'second') {
            return relativeTime.format(Math.round(seconds / size), unit);
        }
    }

    return '';
}

export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    return kb < 1024 ? `${kb.toFixed(1)} KB` : `${(kb / 1024).toFixed(1)} MB`;
}
