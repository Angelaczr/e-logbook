<?php

namespace App\Http\Requests;

use App\Enums\LogbookStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogbookIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::enum(LogbookStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.date_format' => 'Bulan harus berformat TTTT-BB (contoh 2026-10).',
            'status.enum' => 'Status yang dipilih tidak valid.',
        ];
    }

    public function status(): ?LogbookStatus
    {
        return LogbookStatus::tryFrom((string) $this->validated('status'));
    }

    public function monthStart(): ?CarbonImmutable
    {
        $month = $this->validated('month');

        return $month ? CarbonImmutable::createFromFormat('!Y-m', $month) : null;
    }
}
