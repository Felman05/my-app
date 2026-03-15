export function getTopLocationCounts(items = [], field, limit = 4, fallback = 'Unknown') {
    const countsByName = items.reduce((accumulator, item) => {
        const key = item?.[field] || fallback;
        accumulator[key] = (accumulator[key] || 0) + 1;
        return accumulator;
    }, {});

    return Object.entries(countsByName)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit);
}

export function calculatePercent(part, total) {
    return Math.min(100, (part / Math.max(1, total)) * 100);
}
