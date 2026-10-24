import { detectFormat, headerRows, hexDump, parseQuery } from '@/lib/http';
import { describe, expect, it } from 'vitest';

describe('detectFormat', () => {
    it('detects formats from the content type', () => {
        expect(detectFormat('application/json; charset=utf-8', '{}', false)).toBe('json');
        expect(detectFormat('application/x-www-form-urlencoded', 'a=1', false)).toBe('form');
        expect(detectFormat('text/xml', '<a/>', false)).toBe('xml');
        expect(detectFormat('text/plain', 'hello', false)).toBe('text');
    });

    it('sniffs JSON sent without a JSON content type', () => {
        expect(detectFormat('text/plain', ' {"a":1} ', false)).toBe('json');
        expect(detectFormat('text/plain', '{not json', false)).toBe('text');
    });

    it('handles binary and empty bodies first', () => {
        expect(detectFormat('application/json', 'AAEC', true)).toBe('binary');
        expect(detectFormat('application/json', null, false)).toBe('empty');
    });
});

describe('parseQuery', () => {
    it('keeps duplicate keys in order', () => {
        expect(parseQuery('a=1&b=2&a=3')).toEqual([
            ['a', '1'],
            ['b', '2'],
            ['a', '3'],
        ]);
        expect(parseQuery(null)).toEqual([]);
    });
});

describe('headerRows', () => {
    it('flattens multi-value headers', () => {
        expect(headerRows({ accept: ['a', 'b'], host: ['x'] })).toEqual([
            ['accept', 'a'],
            ['accept', 'b'],
            ['host', 'x'],
        ]);
    });
});

describe('hexDump', () => {
    it('renders offsets, hex bytes and printable ASCII', () => {
        expect(hexDump(btoa('Hi\0'))).toBe(`00000000  48 69 00${' '.repeat(39)}  Hi.`);
    });

    it('notes truncated output', () => {
        expect(hexDump(btoa('abcdef'), 4)).toMatch(/… 2 more bytes$/);
    });
});
