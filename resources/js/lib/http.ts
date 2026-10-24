export type BodyFormat = 'json' | 'form' | 'xml' | 'text' | 'binary' | 'empty';

export const HTTP_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as const;

/** Connection-level headers the server strips before replaying (mirrors Replayer::DROPPED_HEADERS). */
export const REPLAY_DROPPED_HEADERS = [
    'host',
    'content-length',
    'connection',
    'keep-alive',
    'transfer-encoding',
    'te',
    'trailer',
    'upgrade',
    'proxy-authorization',
    'proxy-connection',
];

export function parseQuery(queryString: string | null): [string, string][] {
    if (!queryString) {
        return [];
    }

    return [...new URLSearchParams(queryString).entries()];
}

export function headerRows(headers: Record<string, string[]>): [string, string][] {
    return Object.entries(headers).flatMap(([name, values]) => values.map((value): [string, string] => [name, value]));
}

export function detectFormat(contentType: string | null, body: string | null, isBinary: boolean): BodyFormat {
    if (!body) {
        return 'empty';
    }

    if (isBinary) {
        return 'binary';
    }

    const type = contentType?.toLowerCase() ?? '';

    if (type.includes('json') || looksLikeJson(body)) {
        return 'json';
    }

    if (type.includes('x-www-form-urlencoded')) {
        return 'form';
    }

    if (type.includes('xml')) {
        return 'xml';
    }

    return 'text';
}

export function prettyJson(body: string): string {
    return formatJson(body) ?? body;
}

/**
 * Re-indent valid JSON without parsing its values, so every literal is kept
 * verbatim. A JSON.parse/stringify round-trip would round integers beyond
 * 2^53, which is common for IDs, and could hide real differences in a diff.
 */
export function formatJson(source: string, indentWith = '  '): string | null {
    try {
        JSON.parse(source);
    } catch {
        return null;
    }

    let out = '';
    let depth = 0;
    let inString = false;
    let escaped = false;
    const newline = () => '\n' + indentWith.repeat(depth);

    for (let i = 0; i < source.length; i++) {
        const char = source[i];

        if (inString) {
            out += char;
            if (escaped) escaped = false;
            else if (char === '\\') escaped = true;
            else if (char === '"') inString = false;
            continue;
        }

        switch (char) {
            case '"':
                inString = true;
                out += char;
                break;
            case '{':
            case '[': {
                const close = char === '{' ? '}' : ']';
                let next = i + 1;
                while (/\s/.test(source[next] ?? '')) next++;

                if (source[next] === close) {
                    out += char + close;
                    i = next;
                } else {
                    depth++;
                    out += char + newline();
                }
                break;
            }
            case '}':
            case ']':
                depth--;
                out += newline() + char;
                break;
            case ',':
                out += ',' + newline();
                break;
            case ':':
                out += ': ';
                break;
            default:
                if (!/\s/.test(char)) out += char;
        }
    }

    return out;
}

/**
 * Render the first bytes of a base64 payload as a classic hex dump.
 */
export function hexDump(base64: string, limit = 512): string {
    const binary = atob(base64);
    const bytes = Uint8Array.from(binary.slice(0, limit), (char) => char.charCodeAt(0));
    const lines: string[] = [];

    for (let offset = 0; offset < bytes.length; offset += 16) {
        const chunk = bytes.slice(offset, offset + 16);
        const hex = [...chunk].map((byte) => byte.toString(16).padStart(2, '0')).join(' ');
        const ascii = [...chunk].map((byte) => (byte >= 32 && byte < 127 ? String.fromCharCode(byte) : '.')).join('');

        lines.push(`${offset.toString(16).padStart(8, '0')}  ${hex.padEnd(47)}  ${ascii}`);
    }

    if (binary.length > limit) {
        lines.push(`… ${binary.length - limit} more bytes`);
    }

    return lines.join('\n');
}

function looksLikeJson(body: string): boolean {
    const trimmed = body.trim();

    if (!/^[[{]/.test(trimmed)) {
        return false;
    }

    try {
        JSON.parse(trimmed);
        return true;
    } catch {
        return false;
    }
}
