<?php

namespace App\Http\Requests;

use App\Models\Logbook;
use Illuminate\Validation\Rule;

class UpdateLogbookRequest extends SaveLogbookRequest
{
    public function authorize(): bool
    {
        $logbook = $this->route('logbook');

        return $logbook instanceof Logbook && ($this->user()?->can('update', $logbook) ?? false);
    }

    protected function ignoredLogbook(): ?Logbook
    {
        $logbook = $this->route('logbook');

        return $logbook instanceof Logbook ? $logbook : null;
    }

    protected function itemIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('logbook_items', 'id')->where('logbook_id', $this->ignoredLogbook()?->getKey()),
        ];
    }
}
