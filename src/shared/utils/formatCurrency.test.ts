import { describe, expect, it } from 'vitest';
import { formatCurrency } from './formatCurrency';

describe('formatCurrency', () => {
  it('formats integer cents as Indonesian rupiah', () => {
    expect(formatCurrency(123456)).toBe('Rp 1.234,56');
  });

  it('rejects values that are not safe integer cents', () => {
    expect(() => formatCurrency(10.5)).toThrow(RangeError);
    expect(() => formatCurrency(Number.MAX_SAFE_INTEGER + 1)).toThrow(RangeError);
  });
});
