export type ApiErrorPayload = {
  error?: unknown;
  code?: unknown;
  message?: unknown;
  requestId?: unknown;
  errors?: unknown;
};

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  readonly code: string;
  readonly requestId: string | null;
  readonly status: number;
  readonly fieldErrors: FieldErrors;

  constructor(input: {
    code: string;
    message: string;
    requestId: string | null;
    status: number;
    fieldErrors: FieldErrors;
  }) {
    super(input.message);
    this.name = 'ApiError';
    this.code = input.code;
    this.requestId = input.requestId;
    this.status = input.status;
    this.fieldErrors = input.fieldErrors;
  }
}
