export interface ApiResponse<T> {
    status: number;
    data: T;
}

export interface ValidationErrors {
    message: string;
    errors: Record<string, string[]>;
}

/**
 * POST JSON to a Laravel endpoint, passing the CSRF token from the XSRF-TOKEN cookie.
 */
export async function postJson<T>(url: string, body: unknown): Promise<ApiResponse<T>> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    return { status: response.status, data: (await response.json().catch(() => null)) as T };
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
