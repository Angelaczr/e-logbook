const IDR_FORMATTER = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
});

/** Formats integer cents (not rupiah) as Indonesian rupiah. */
export function formatCurrency(cents: number): string {
  if (!Number.isSafeInteger(cents)) {
    throw new RangeError('Nilai mata uang harus berupa integer sen yang aman.');
  }

  return IDR_FORMATTER.format(cents / 100);
}
