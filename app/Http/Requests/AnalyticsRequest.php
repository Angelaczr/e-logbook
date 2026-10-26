<?php

namespace App\Http\Requests;

use App\Services\AnalyticsFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Throwable;

class AnalyticsRequest extends FormRequest
{
    public const MAX_DAYS = 92;

    public function authorize(): bool
    {
        return Gate::allows('view-analytics');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer'],
        ];

        try {
            $limit = CarbonImmutable::parse((string) $this->input('from'))->addDays(self::MAX_DAYS)->toDateString();
            $rules['to'][] = 'before_or_equal:'.$limit;
        } catch (Throwable) {
            // `from` tidak valid atau kosong: aturan `date` yang melaporkan.
        }

        return $rules;
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
            'to.before_or_equal' => 'Periode maksimal '.self::MAX_DAYS.' hari.',
            'department_id.integer' => 'Departemen yang dipilih tidak valid.',
        ];
    }

    public function filter(): AnalyticsFilter
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        return new AnalyticsFilter(
            viewer: $this->user(),
            from: $from ? CarbonImmutable::parse($from) : CarbonImmutable::today()->startOfMonth(),
            to: $to ? CarbonImmutable::parse($to) : CarbonImmutable::today(),
            departmentId: $this->integer('department_id') ?: null,
        );
    }
}
