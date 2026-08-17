import { describe, expect, it } from 'vitest';
import { humanSize } from '../../src/forum/format';

describe('file library formatting', () => {
  it('formats file sizes without changing layout data', () => {
    expect(humanSize(1536)).toBe('1.5 KB');
  });
});
