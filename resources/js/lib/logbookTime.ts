/**
 * Hanya untuk tampilan dan peringatan di klien. Server tetap sumber kebenaran
 * untuk durasi, tumpang tindih, dan total.
 */

const MINUTES_PER_DAY = 24 * 60;

function toMinutes(time: string): number | null {
  const match = /^(\d{2}):(\d{2})/.exec(time);
  if (!match) {
    return null;
  }
  return Number(match[1]) * 60 + Number(match[2]);
}

export function durationMinutes(start: string, end: string): number | null {
  const from = toMinutes(start);
  const to = toMinutes(end);
  if (from === null || to === null) {
    return null;
  }
  return to < from ? to + MINUTES_PER_DAY - from : to - from;
}

export function formatDuration(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  if (hours === 0) {
    return `${rest} menit`;
  }
  return rest === 0 ? `${hours} jam` : `${hours} jam ${rest} menit`;
}

interface TimeRange {
  start: string;
  end: string;
}

/** Indeks baris yang jamnya bertabrakan dengan baris lain (termasuk lewat tengah malam). */
export function overlappingIndexes(ranges: TimeRange[]): Set<number> {
  const spans = ranges.map((range) => {
    const from = toMinutes(range.start);
    const length = durationMinutes(range.start, range.end);
    return from === null || length === null || length === 0 ? null : { from, to: from + length };
  });

  const result = new Set<number>();
  spans.forEach((a, i) => {
    spans.forEach((b, j) => {
      if (a === null || b === null || j <= i) {
        return;
      }
      const clash = [-MINUTES_PER_DAY, 0, MINUTES_PER_DAY].some((shift) => a.from < b.to + shift && b.from + shift < a.to);
      if (clash) {
        result.add(i);
        result.add(j);
      }
    });
  });
  return result;
}

export function formatDate(isoDate: string): string {
  return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    .format(new Date(`${isoDate.slice(0, 10)}T00:00:00`));
}

export function formatDateTime(iso: string): string {
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}
