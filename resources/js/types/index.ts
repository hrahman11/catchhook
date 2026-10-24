export interface Bin {
    id: string;
    name: string | null;
    url: string;
    expired: boolean;
    expires_at: string;
    created_at: string | null;
}

export interface CapturedRequestSummary {
    id: string;
    method: string;
    path: string;
    query_string: string | null;
    content_type: string | null;
    size: number;
    ip: string | null;
    created_at: string;
}

export interface RequestCapturedEvent {
    request: CapturedRequestSummary;
}

export interface ReplayResult {
    ok: boolean;
    status: number | null;
    reason: string | null;
    headers: Record<string, string[]>;
    /** Response body; base64-encoded when `is_binary` is true. */
    body: string | null;
    is_binary: boolean;
    size: number;
    truncated: boolean;
    duration_ms: number;
    error: string | null;
}

export interface CapturedRequest extends CapturedRequestSummary {
    headers: Record<string, string[]>;
    is_binary: boolean;
    /** Raw body; base64-encoded when `is_binary` is true. */
    body: string | null;
}
