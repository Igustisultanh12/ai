/**
 * API HTTP client for XZY with HttpOnly session cookies, CSRF protection, and error handling.
 */

export function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    if (meta && meta.content) {
        return meta.content;
    }

    // Fallback: extract from XSRF-TOKEN cookie if present
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : '';
}

const PUBLIC_PATHS = ['/', '/login', '/register'];

/**
 * Session expired: send the user back to the login page (once).
 */
export function redirectToLoginIfNeeded(): void {
    if (typeof window === 'undefined' || PUBLIC_PATHS.includes(window.location.pathname)) return;
    const next = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `/login?redirect=${next}`;
}

export interface ApiResponse<T = any> {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
    [key: string]: any;
}

export async function apiRequest<T = any>(
    endpoint: string,
    options: RequestInit = {}
): Promise<T> {
    const headers = new Headers(options.headers || {});
    
    // Set standard JSON headers if body is present and not FormData
    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const csrfToken = getCsrfToken();
    if (csrfToken) {
        headers.set('X-CSRF-TOKEN', csrfToken);
    }

    const response = await fetch(endpoint, {
        ...options,
        headers,
        credentials: 'same-origin', // HttpOnly cookies sent automatically
    });

    if (response.status === 204) {
        return {} as T;
    }

    const isJson = response.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await response.json() : await response.text();

    if (response.status === 401) {
        redirectToLoginIfNeeded();
    }

    if (!response.ok) {
        const errorMsg = data?.message || `Permintaan gagal dengan kode HTTP ${response.status}`;
        const error = new Error(errorMsg) as Error & { status: number; data: any };
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data as T;
}

export const api = {
    get: <T = any>(url: string, headers?: HeadersInit) =>
        apiRequest<T>(url, { method: 'GET', headers }),
    post: <T = any>(url: string, body?: any, headers?: HeadersInit) =>
        apiRequest<T>(url, {
            method: 'POST',
            body: body ? JSON.stringify(body) : undefined,
            headers,
        }),
    put: <T = any>(url: string, body?: any, headers?: HeadersInit) =>
        apiRequest<T>(url, {
            method: 'PUT',
            body: body ? JSON.stringify(body) : undefined,
            headers,
        }),
    delete: <T = any>(url: string, headers?: HeadersInit) =>
        apiRequest<T>(url, { method: 'DELETE', headers }),
};
