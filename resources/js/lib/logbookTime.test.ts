import { describe, expect, it } from 'vitest';
import { durationMinutes, formatDuration, overlappingIndexes } from './logbookTime';

describe('durationMinutes', () => {
  it('menghitung durasi biasa dan yang melewati tengah malam', () => {
    expect(durationMinutes('08:00', '09:30')).toBe(90);
    expect(durationMinutes('22:00', '06:00')).toBe(480);
  });

  it('mengembalikan null untuk jam yang belum lengkap', () => {
    expect(durationMinutes('', '09:00')).toBeNull();
  });
});

describe('formatDuration', () => {
  it('menampilkan jam dan menit', () => {
    expect(formatDuration(45)).toBe('45 menit');
    expect(formatDuration(120)).toBe('2 jam');
    expect(formatDuration(150)).toBe('2 jam 30 menit');
  });
});

describe('overlappingIndexes', () => {
  it('menandai baris yang bertabrakan, bukan yang hanya bersinggungan', () => {
    const result = overlappingIndexes([
      { start: '08:00', end: '10:00' },
      { start: '09:30', end: '11:00' },
      { start: '11:00', end: '12:00' },
    ]);
    expect([...result]).toEqual([0, 1]);
  });

  it('mendeteksi tabrakan pada shift malam', () => {
    const result = overlappingIndexes([
      { start: '22:00', end: '06:00' },
      { start: '01:00', end: '02:00' },
    ]);
    expect(result.size).toBe(2);
  });
});
