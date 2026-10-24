export type BodyFormat = 'json' | 'form' | 'xml' | 'text' | 'binary' | 'empty';

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
    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return body;
    }
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
