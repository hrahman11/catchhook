export type DiffOp = { type: 'equal' | 'insert' | 'delete'; text: string };

export type DiffHunkLine = DiffOp | { type: 'skip'; count: number };

export type HeaderDiffStatus = 'same' | 'changed' | 'added' | 'removed';

export interface HeaderDiffRow {
    name: string;
    left: string | null;
    right: string | null;
    status: HeaderDiffStatus;
}

/**
 * Line diff using Myers' O(ND) algorithm, the same approach as `git diff`.
 *
 * Returns null when the inputs differ by more than `maxEdits` lines, since
 * the work grows with the number of differences.
 */
export function diffLines(left: string[], right: string[], maxEdits = 2000): DiffOp[] | null {
    const n = left.length;
    const m = right.length;
    const max = n + m;
    const offset = max;
    const v = new Int32Array(2 * max + 2);
    const trace: Int32Array[] = [];

    for (let d = 0; d <= Math.min(max, maxEdits); d++) {
        trace.push(v.slice());

        for (let k = -d; k <= d; k += 2) {
            // Step down (insertion) or right (deletion), whichever reaches further.
            let x = k === -d || (k !== d && v[offset + k - 1] < v[offset + k + 1]) ? v[offset + k + 1] : v[offset + k - 1] + 1;
            let y = x - k;

            // Follow the diagonal while lines match.
            while (x < n && y < m && left[x] === right[y]) {
                x++;
                y++;
            }

            v[offset + k] = x;

            if (x >= n && y >= m) {
                return backtrack(trace, left, right, offset);
            }
        }
    }

    return null;
}

function backtrack(trace: Int32Array[], left: string[], right: string[], offset: number): DiffOp[] {
    const ops: DiffOp[] = [];
    let x = left.length;
    let y = right.length;

    for (let d = trace.length - 1; d >= 0; d--) {
        const v = trace[d];
        const k = x - y;
        const prevK = k === -d || (k !== d && v[offset + k - 1] < v[offset + k + 1]) ? k + 1 : k - 1;
        const prevX = v[offset + prevK];
        const prevY = prevX - prevK;

        while (x > prevX && y > prevY) {
            ops.push({ type: 'equal', text: left[--x] });
            y--;
        }

        if (d > 0) {
            ops.push(x === prevX ? { type: 'insert', text: right[prevY] } : { type: 'delete', text: left[prevX] });
        }

        x = prevX;
        y = prevY;
    }

    return ops.reverse();
}

/**
 * Collapse long runs of unchanged lines, keeping `context` lines around each change.
 */
export function collapse(ops: DiffOp[], context = 3): DiffHunkLine[] {
    const keep = ops.map(() => false);

    ops.forEach((op, index) => {
        if (op.type !== 'equal') {
            for (let i = Math.max(0, index - context); i <= Math.min(ops.length - 1, index + context); i++) {
                keep[i] = true;
            }
        }
    });

    const lines: DiffHunkLine[] = [];

    ops.forEach((op, index) => {
        if (keep[index]) {
            lines.push(op);
            return;
        }

        const last = lines.at(-1);

        if (last?.type === 'skip') {
            last.count++;
        } else {
            lines.push({ type: 'skip', count: 1 });
        }
    });

    return lines;
}

/**
 * Compare two header maps by name. Multi-value headers are compared as a whole.
 */
export function diffHeaders(left: Record<string, string[]>, right: Record<string, string[]>): HeaderDiffRow[] {
    const names = [...new Set([...Object.keys(left), ...Object.keys(right)])].sort();

    return names.map((name) => {
        const a = left[name]?.join(', ') ?? null;
        const b = right[name]?.join(', ') ?? null;
        const status: HeaderDiffStatus = a === null ? 'added' : b === null ? 'removed' : a === b ? 'same' : 'changed';

        return { name, left: a, right: b, status };
    });
}
