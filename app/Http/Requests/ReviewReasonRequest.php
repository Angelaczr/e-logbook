<?php

namespace App\Http\Requests;

/**
 * Revisi dan penolakan wajib disertai alasan.
 */
class ReviewReasonRequest extends ReviewDecisionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal :min karakter.',
            'reason.max' => 'Alasan maksimal :max karakter.',
        ];
    }
}
