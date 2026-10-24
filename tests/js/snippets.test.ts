import { shellQuote, toCurl, toFetch, toPython, type SnippetInput } from '@/lib/snippets';
import { describe, expect, it } from 'vitest';

const json: SnippetInput = {
    method: 'POST',
    url: 'https://catchhook.test/h/abc/events',
    headers: [
        ['content-type', 'application/json'],
        ['x-signature', 'sha256=abc'],
    ],
    body: '{"id":12345678901234567890,"note":"it\'s here"}',
    isBinary: false,
};

const binary: SnippetInput = { ...json, headers: [['content-type', 'application/octet-stream']], body: btoa('\x89PNG\0'), isBinary: true };

describe('shellQuote', () => {
    it('wraps values in single quotes and escapes embedded quotes', () => {
        expect(shellQuote('plain')).toBe("'plain'");
        expect(shellQuote("it's")).toBe(`'it'\\''s'`);
        expect(shellQuote('$HOME `whoami`')).toBe("'$HOME `whoami`'");
    });
});

describe('toCurl', () => {
    it('includes the method, url, headers and verbatim body', () => {
        expect(toCurl(json)).toBe(
            [
                "curl -X POST 'https://catchhook.test/h/abc/events'",
                "  -H 'content-type: application/json'",
                "  -H 'x-signature: sha256=abc'",
                `  --data-raw '{"id":12345678901234567890,"note":"it'\\''s here"}'`,
            ].join(' \\\n'),
        );
    });

    it('pipes binary bodies through base64 so bytes survive the shell', () => {
        const snippet = toCurl(binary);

        expect(snippet).toContain(`printf %s '${binary.body}' | base64 --decode |`);
        expect(snippet).toContain('--data-binary @-');
    });

    it('uses --head for HEAD requests and omits empty bodies', () => {
        expect(toCurl({ ...json, method: 'HEAD', headers: [], body: null })).toBe("curl --head 'https://catchhook.test/h/abc/events'");
    });
});

describe('toFetch', () => {
    it('keeps the body as an exact string literal', () => {
        const snippet = toFetch(json);

        expect(snippet).toContain('method: "POST"');
        expect(snippet).toContain('"x-signature": "sha256=abc"');
        // Large numeric IDs must not be rounded by a JSON round-trip.
        expect(snippet).toContain(`body: ${JSON.stringify(json.body)}`);
    });

    it('decodes binary bodies into bytes', () => {
        expect(toFetch(binary)).toContain(`body: Uint8Array.from(atob("${binary.body}"), (c) => c.charCodeAt(0))`);
    });

    it('merges repeated headers', () => {
        const snippet = toFetch({
            ...json,
            headers: [
                ['accept', 'text/html'],
                ['accept', 'application/json'],
            ],
        });

        expect(snippet).toContain('"accept": "text/html, application/json"');
    });
});

describe('toPython', () => {
    it('sends the body as encoded bytes', () => {
        const snippet = toPython(json);

        expect(snippet).toMatch(/^import requests/);
        expect(snippet).toContain(`data=${JSON.stringify(json.body)}.encode(),`);
    });

    it('decodes binary bodies with base64', () => {
        const snippet = toPython(binary);

        expect(snippet).toMatch(/^import base64\nimport requests/);
        expect(snippet).toContain(`data=base64.b64decode("${binary.body}"),`);
    });
});
