import { computed, ref } from 'vue';
import { durationMinutes, overlappingIndexes } from '@/lib/logbookTime';
import type { FormItem, LogbookItemData } from '@/types/logbook';

let counter = 0;
const nextUid = (): string => `row-${++counter}`;

function blankItem(start = ''): FormItem {
  return {
    uid: nextUid(),
    id: null,
    project_id: null,
    task_description: '',
    start_time: start,
    end_time: '',
    status_progress: 'completed',
    output_link_or_attachment: '',
  };
}

function fromServer(item: LogbookItemData): FormItem {
  return {
    uid: nextUid(),
    id: item.id,
    project_id: item.project_id,
    task_description: item.task_description,
    start_time: item.start_time,
    end_time: item.end_time,
    status_progress: item.status_progress,
    output_link_or_attachment: item.output_link_or_attachment ?? '',
  };
}

/** Bentuk item yang dikirim ke server: tanpa `uid`, `id` hanya jika sudah ada, tautan kosong menjadi null. */
export function toPayload(items: FormItem[]): Record<string, unknown>[] {
  return items.map(({ uid: _uid, id, output_link_or_attachment: link, ...rest }) => ({
    ...(id === null ? {} : { id }),
    ...rest,
    output_link_or_attachment: link === '' ? null : link,
  }));
}

export function useLogbookItems(initial: LogbookItemData[]) {
  const items = ref<FormItem[]>(initial.length > 0 ? initial.map(fromServer) : [blankItem('08:00')]);

  const overlapping = computed(() => overlappingIndexes(items.value.map((item) => ({ start: item.start_time, end: item.end_time }))));
  /** Perkiraan untuk tampilan; total resmi dihitung server. */
  const totalMinutes = computed(() => items.value.reduce(
    (sum, item) => sum + (durationMinutes(item.start_time, item.end_time) ?? 0),
    0,
  ));

  function add(): void {
    items.value.push(blankItem(items.value.at(-1)?.end_time ?? ''));
  }

  function update(index: number, item: FormItem): void {
    items.value.splice(index, 1, item);
  }

  function remove(index: number): void {
    items.value.splice(index, 1);
  }

  function duplicate(index: number): void {
    const source = items.value[index];
    if (source) {
      items.value.splice(index + 1, 0, { ...source, uid: nextUid(), id: null });
    }
  }

  function move(index: number, direction: -1 | 1): void {
    const target = index + direction;
    const [moved] = items.value.splice(index, 1);
    if (moved && target >= 0 && target <= items.value.length) {
      items.value.splice(target, 0, moved);
    } else if (moved) {
      items.value.splice(index, 0, moved);
    }
  }

  return { items, overlapping, totalMinutes, add, update, remove, duplicate, move };
}
