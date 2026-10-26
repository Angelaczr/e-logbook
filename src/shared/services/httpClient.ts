import { ApiError, type FieldErrors } from '@/shared/types/apiError';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function mapFieldErrors(value: unknown): FieldErrors {
  if (!isRecord(value)) return {};

  return Object.fromEntries(
    Object.entries(value).flatMap(([field, messages]) => {
      if (!Array.isArray(messages)) return [];
      const strings = messages.filter((message): message is string => typeof message === 'string');
      return strings.length > 0 ? [[field, strings]] : [];
    }),
  );
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  const response = await fetch(path, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      ...init.headers,
    },
  });

  if (!response.ok) {
    const body: unknown = await response.json().catch(() => null);
    const payload = isRecord(body) ? body : {};
    const nested = isRecord(payload.error) ? payload.error : payload;
    throw new ApiError({
      code: typeof nested.code === 'string' ? nested.code : `HTTP_${response.status}`,
      message: typeof nested.message === 'string' ? nested.message : 'Permintaan tidak dapat diproses.',
      requestId: typeof nested.requestId === 'string' ? nested.requestId : null,
      status: response.status,
      fieldErrors: mapFieldErrors(nested.errors),
    });
  }

  if (response.status === 204) return undefined as T;
  const body: unknown = await response.json();
  // Response schemas are supplied by the backend; validate at this boundary when generated OpenAPI types are added.
  return body as T;
}
