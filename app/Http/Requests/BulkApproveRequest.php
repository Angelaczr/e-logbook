<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu logbook.',
            'ids.min' => 'Pilih minimal satu logbook.',
            'ids.max' => 'Maksimal :max logbook sekali setujui.',
            'ids.*.integer' => 'Pilihan logbook tidak valid.',
            'ids.*.distinct' => 'Pilihan logbook tidak boleh ganda.',
        ];
    }
}
