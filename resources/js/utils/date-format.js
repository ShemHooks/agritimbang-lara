const DATE_OPTIONS = {
    day: "2-digit",
    month: "short",
    year: "numeric",
};

function parseDate(value) {
    if (!value) return null;
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;

    const raw = String(value).trim();
    const dateOnly = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnly) {
        const date = new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
        return Number.isNaN(date.getTime()) ? null : date;
    }

    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDate(value, fallback = "—") {
    const date = parseDate(value);
    if (!date) return fallback;
    const parts = new Intl.DateTimeFormat("en-PH", {
        ...DATE_OPTIONS,
        timeZone: "Asia/Manila",
    }).formatToParts(date);
    const part = (type) => parts.find((item) => item.type === type)?.value ?? "";
    return `${part("day")} ${part("month")} ${part("year")}`;
}

export function formatDateTime(value, fallback = "—") {
    const date = parseDate(value);
    if (!date) return fallback;
    return `${formatDate(date, fallback)}, ${new Intl.DateTimeFormat("en-PH", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
        timeZone: "Asia/Manila",
    }).format(date)}`;
}

export function formatMonth(value, fallback = "—") {
    const match = String(value ?? "").match(/^(\d{4})-(\d{2})$/);
    if (!match) return fallback;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, 1);
    return new Intl.DateTimeFormat("en-PH", {
        month: "long",
        year: "numeric",
        timeZone: "Asia/Manila",
    }).format(date);
}
