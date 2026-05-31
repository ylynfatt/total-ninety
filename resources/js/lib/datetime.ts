/**
 * Human-friendly formatting for the ISO date strings the API serializes
 * (e.g. "2026-05-02T00:00:00.000000Z").
 *
 * These come from Laravel `date` casts pinned to midnight UTC, so we format in
 * UTC to keep the calendar date stable regardless of the viewer's timezone —
 * otherwise a negative offset could shift "May 2" back to "May 1".
 */
const dateFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

function toDate(value?: string | null): Date | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * "May 2, 2026" — or an empty string for null/invalid input.
 */
export function formatDate(value?: string | null): string {
    const date = toDate(value);

    return date ? dateFormatter.format(date) : '';
}

/**
 * "May 2, 2026 – May 29, 2026", collapsing to a single date when only one end
 * is present.
 */
export function formatDateRange(start?: string | null, end?: string | null): string {
    const from = formatDate(start);
    const to = formatDate(end);

    if (from && to) {
        return `${from} – ${to}`;
    }

    return from || to || '';
}
