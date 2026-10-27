const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * fetch() wrapper for same-origin JSON endpoints. Resolves with the parsed body,
 * rejects with { status, message, errors } so callers can map 422s onto fields.
 */
export async function fetchJson(url, { method = 'GET', body = null, headers = {} } = {}) {
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body && !isForm ? JSON.stringify(body) : body,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw {
            status: response.status,
            message: data.message ?? 'Something went wrong. Please try again.',
            errors: data.errors ?? {},
        };
    }

    return data;
}

export const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
