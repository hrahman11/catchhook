import { collapse, diffHeaders, diffLines, type DiffOp } from '@/lib/diff';
import { describe, expect, it } from 'vitest';

const left = (ops: DiffOp[]) => ops.filter((op) => op.type !== 'insert').map((op) => op.text);
const right = (ops: DiffOp[]) => ops.filter((op) => op.type !== 'delete').map((op) => op.text);
const edits = (ops: DiffOp[]) => ops.filter((op) => op.type !== 'equal').length;

describe('diffLines', () => {
    it('marks identical input as unchanged', () => {
        expect(diffLines(['a', 'b'], ['a', 'b'])).toEqual([
            { type: 'equal', text: 'a' },
            { type: 'equal', text: 'b' },
        ]);
    });

    it('finds a single changed line', () => {
        expect(diffLines(['a', 'b', 'c'], ['a', 'x', 'c'])).toEqual([
            { type: 'equal', text: 'a' },
            { type: 'delete', text: 'b' },
            { type: 'insert', text: 'x' },
            { type: 'equal', text: 'c' },
        ]);
    });

    it('handles empty sides', () => {
        expect(diffLines([], ['a'])).toEqual([{ type: 'insert', text: 'a' }]);
        expect(diffLines(['a'], [])).toEqual([{ type: 'delete', text: 'a' }]);
        expect(diffLines([], [])).toEqual([]);
    });

    it('produces a minimal edit script that reconstructs both sides', () => {
        const a = 'the quick brown fox jumps over the lazy dog'.split(' ');
        const b = 'the quick red fox leaps over the very lazy dog'.split(' ');
        const ops = diffLines(a, b)!;

        expect(left(ops)).toEqual(a);
        expect(right(ops)).toEqual(b);
        // brown→red, jumps→leaps (2 deletes + 2 inserts) and "very" inserted.
        expect(edits(ops)).toBe(5);
    });

    it('reconstructs both sides for random inputs', () => {
        const alphabet = ['a', 'b', 'c', 'd'];
        const random = (length: number) => Array.from({ length }, () => alphabet[Math.floor(Math.random() * alphabet.length)]);

        for (let i = 0; i < 200; i++) {
            const a = random(Math.floor(Math.random() * 20));
            const b = random(Math.floor(Math.random() * 20));
            const ops = diffLines(a, b)!;

            expect(left(ops)).toEqual(a);
            expect(right(ops)).toEqual(b);
        }
    });

    it('gives up when the inputs differ too much', () => {
        expect(diffLines(['a', 'b', 'c'], ['x', 'y', 'z'], 2)).toBeNull();
    });
});

describe('collapse', () => {
    it('keeps context around changes and folds the rest', () => {
        const ops: DiffOp[] = [
            ...Array.from({ length: 10 }, (_, i): DiffOp => ({ type: 'equal', text: `line ${i}` })),
            { type: 'delete', text: 'old' },
            { type: 'insert', text: 'new' },
            ...Array.from({ length: 10 }, (_, i): DiffOp => ({ type: 'equal', text: `tail ${i}` })),
        ];

        const lines = collapse(ops, 2);

        expect(lines[0]).toEqual({ type: 'skip', count: 8 });
        expect(lines.slice(1, 7).map((line) => ('text' in line ? line.text : null))).toEqual(['line 8', 'line 9', 'old', 'new', 'tail 0', 'tail 1']);
        expect(lines.at(-1)).toEqual({ type: 'skip', count: 8 });
    });
});

describe('diffHeaders', () => {
    it('classifies each header', () => {
        expect(
            diffHeaders(
                { 'content-type': ['application/json'], 'x-old': ['1'], accept: ['a', 'b'] },
                { 'content-type': ['application/json'], 'x-new': ['2'], accept: ['a', 'c'] },
            ),
        ).toEqual([
            { name: 'accept', left: 'a, b', right: 'a, c', status: 'changed' },
            { name: 'content-type', left: 'application/json', right: 'application/json', status: 'same' },
            { name: 'x-new', left: null, right: '2', status: 'added' },
            { name: 'x-old', left: '1', right: null, status: 'removed' },
        ]);
    });
});
