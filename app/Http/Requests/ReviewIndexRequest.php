<?php

namespace App\Http\Requests;

use App\Enums\LogbookStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIndexRequest extends FormRequest
{
    public const SORT_DATE = 'date';

    public const SORT_VIEWED = 'viewed';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status yang boleh difilter: draft milik bawahan tidak ditampilkan ke peninjau.
     *
     * @return list<LogbookStatus>
     */
    public static function filterableStatuses(): array
    {
        return array_values(array_filter(
            LogbookStatus::cases(),
            fn (LogbookStatus $status) => $status !== LogbookStatus::Draft,
        ));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_map(fn (LogbookStatus $s) => $s->value, self::filterableStatuses()))],
            'review' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in([self::SORT_DATE, self::SORT_VIEWED])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date' => 'Tanggal awal tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'status.in' => 'Status yang dipilih tidak valid.',
            'sort.in' => 'Urutan yang dipilih tidak valid.',
            'user_id.integer' => 'Karyawan yang dipilih tidak valid.',
            'department_id.integer' => 'Departemen yang dipilih tidak valid.',
        ];
    }

    public function sort(): string
    {
        return $this->validated('sort') === self::SORT_VIEWED ? self::SORT_VIEWED : self::SORT_DATE;
    }

    public function status(): LogbookStatus
    {
        return LogbookStatus::tryFrom((string) $this->validated('status')) ?? LogbookStatus::Submitted;
    }
}
