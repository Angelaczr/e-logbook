<?php

namespace App\Http\Requests;

use App\Enums\ProgressStatus;
use App\Models\Logbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SaveLogbookRequest extends FormRequest
{
    private const MAX_ITEMS = 30;

    /**
     * Aturan khusus `items.*.id`: dilarang saat membuat, harus milik logbook ini saat mengubah.
     *
     * @return array<int, mixed>
     */
    abstract protected function itemIdRules(): array;

    protected function ignoredLogbook(): ?Logbook
    {
        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'work_date' => [
                'required',
                'date',
                'before_or_equal:today',
                Rule::unique('logbooks', 'work_date')
                    ->where('user_id', $this->user()->getAuthIdentifier())
                    ->where('shift', $this->input('shift') ?: 'regular')
                    ->ignore($this->ignoredLogbook()?->getKey()),
            ],
            'shift' => ['nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.id' => $this->itemIdRules(),
            'items.*.project_id' => ['nullable', Rule::exists('projects', 'id')->where('is_active', true)],
            'items.*.task_description' => ['required', 'string', 'min:10', 'max:10000'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.end_time' => ['required', 'date_format:H:i', 'different:items.*.start_time'],
            'items.*.status_progress' => ['required', Rule::enum(ProgressStatus::class)],
            'items.*.output_link_or_attachment' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_date' => 'tanggal kerja',
            'shift' => 'shift',
            'items' => 'item aktivitas',
            'items.*.id' => 'item',
            'items.*.project_id' => 'proyek',
            'items.*.task_description' => 'uraian pekerjaan',
            'items.*.start_time' => 'jam mulai',
            'items.*.end_time' => 'jam selesai',
            'items.*.status_progress' => 'status progres',
            'items.*.output_link_or_attachment' => 'tautan hasil kerja',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'date' => ':Attribute bukan tanggal yang valid.',
            'before_or_equal' => 'Tanggal kerja tidak boleh di masa depan.',
            'unique' => 'Logbook untuk tanggal dan shift ini sudah ada.',
            'string' => ':Attribute harus berupa teks.',
            'integer' => ':Attribute tidak valid.',
            'array' => ':Attribute tidak valid.',
            'exists' => ':Attribute yang dipilih tidak valid atau sudah tidak aktif.',
            'enum' => ':Attribute yang dipilih tidak valid.',
            'url' => ':Attribute harus berupa URL yang valid.',
            'date_format' => ':Attribute harus berformat jam:menit (contoh 08:30).',
            'different' => 'Jam selesai harus berbeda dari jam mulai.',
            'prohibited' => ':Attribute tidak boleh diisi saat membuat logbook baru.',
            'shift.max' => 'Shift maksimal :max karakter.',
            'items.min' => 'Minimal harus ada :min item aktivitas.',
            'items.max' => 'Maksimal :max item aktivitas dalam satu logbook.',
            'items.*.task_description.min' => 'Uraian pekerjaan minimal :min karakter.',
            'items.*.task_description.max' => 'Uraian pekerjaan maksimal :max karakter.',
            'items.*.output_link_or_attachment.max' => 'Tautan hasil kerja maksimal :max karakter.',
        ];
    }
}
