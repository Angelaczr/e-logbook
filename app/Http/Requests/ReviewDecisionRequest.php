<?php

namespace App\Http\Requests;

use App\Models\Logbook;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Persetujuan dengan catatan opsional. Aturan hak review dan status ditegakkan oleh LogbookService.
 */
class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $logbook = $this->route('logbook');

        return $logbook instanceof Logbook && ($this->user()?->can('view', $logbook) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Catatan maksimal :max karakter.',
        ];
    }
}
