<?php

namespace App\Http\Requests;

use App\Models\Logbook;

class StoreLogbookRequest extends SaveLogbookRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Logbook::class) ?? false;
    }

    protected function itemIdRules(): array
    {
        return ['prohibited'];
    }
}
