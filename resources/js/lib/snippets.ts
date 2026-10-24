import { REPLAY_DROPPED_HEADERS, headerRows } from '@/lib/http';
import type { CapturedRequest } from '@/types';

export type SnippetLanguage = 'curl' | 'fetch' | 'python';

export interface SnippetInput {
    method: string;
    url: string;
    headers: [string, string][];
    /** Raw body; base64-encoded when `isBinary` is true. */
    body: string | null;
    isBinary: boolean;
}

export const SNIPPET_LANGUAGES: { id: SnippetLanguage; label: string }[] = [
    { id: 'curl', label: 'cURL' },
    { id: 'fetch', label: 'JavaScript' },
    { id: 'python', label: 'Python' },
];

export function snippetInput(request: CapturedRequest, url: string): SnippetInput {
    return {
        method: request.method,
        url,
        headers: headerRows(request.headers).filter(([name]) => !REPLAY_DROPPED_HEADERS.includes(name.toLowerCase())),
        body: request.body,
        isBinary: request.is_binary,
    };
}

export function buildSnippet(language: SnippetLanguage, input: SnippetInput): string {
    switch (language) {
        case 'curl':
            return toCurl(input);
        case 'fetch':
            return toFetch(input);
        case 'python':
            return toPython(input);
    }
}

/**
 * Bodies are embedded verbatim (never re-serialized) so that signed payloads
 * still verify when the snippet is run.
 */
export function toCurl({ method, url, headers, body, isBinary }: SnippetInput): string {
    const parts = [method === 'HEAD' ? `curl --head ${shellQuote(url)}` : `curl -X ${method} ${shellQuote(url)}`];

    for (const [name, value] of headers) {
        parts.push(`-H ${shellQuote(`${name}: ${value}`)}`);
    }

    if (body && isBinary) {
        parts.push('--data-binary @-');

        return `printf %s ${shellQuote(body)} | base64 --decode | \\\n  ${parts.join(' \\\n  ')}`;
    }

    if (body) {
        parts.push(`--data-raw ${shellQuote(body)}`);
    }

    return parts.join(' \\\n  ');
}

export function toFetch({ method, url, headers, body, isBinary }: SnippetInput): string {
    const lines = [`const response = await fetch(${str(url)}, {`, `  method: ${str(method)},`];

    if (headers.length) {
        lines.push('  headers: {');
        for (const [name, value] of mergeHeaders(headers)) {
            lines.push(`    ${str(name)}: ${str(value)},`);
        }
        lines.push('  },');
    }

    if (body) {
        lines.push(isBinary ? `  body: Uint8Array.from(atob(${str(body)}), (c) => c.charCodeAt(0)),` : `  body: ${str(body)},`);
    }

    lines.push('});', '', 'console.log(response.status, await response.text());');

    return lines.join('\n');
}

export function toPython({ method, url, headers, body, isBinary }: SnippetInput): string {
    const lines = [...(body && isBinary ? ['import base64'] : []), 'import requests', '', 'response = requests.request(', `    ${str(method)},`, `    ${str(url)},`];

    if (headers.length) {
        lines.push('    headers={');
        for (const [name, value] of mergeHeaders(headers)) {
            lines.push(`        ${str(name)}: ${str(value)},`);
        }
        lines.push('    },');
    }

    if (body) {
        lines.push(isBinary ? `    data=base64.b64decode(${str(body)}),` : `    data=${str(body)}.encode(),`);
    }

    lines.push(')', '', 'print(response.status_code, response.text)');

    return lines.join('\n');
}

/**
 * POSIX single-quote escaping: close the quote, add an escaped quote, reopen.
 */
export function shellQuote(value: string): string {
    return `'${value.replaceAll("'", `'\\''`)}'`;
}

/**
 * A double-quoted literal that is valid in both JavaScript and Python.
 */
function str(value: string): string {
    return JSON.stringify(value);
}

/**
 * Object literals can't hold duplicate keys, so repeated headers are joined
 * with ", " as permitted by RFC 9110 §5.3.
 */
function mergeHeaders(headers: [string, string][]): [string, string][] {
    const merged = new Map<string, string>();

    for (const [name, value] of headers) {
        merged.set(name, merged.has(name) ? `${merged.get(name)}, ${value}` : value);
    }

    return [...merged.entries()];
}
