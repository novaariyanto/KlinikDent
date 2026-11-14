<?php

namespace App\Http\Requests\Api;

use App\Exceptions\MobileApiException;
use App\Http\Requests\Visit\StoreVisitRequest as RegistrationStoreVisitRequest;
use App\Models\Branch;

class StoreVisitRequest extends RegistrationStoreVisitRequest
{
    protected function prepareForValidation(): void
    {
        $active = $this->attributes->get('active_branch');
        $header = $this->header('X-Branch-Id');
        $headerPresent = is_string($header) && $header !== '';

        if ($headerPresent && $active instanceof Branch && $this->filled('branch_id') && (int) $this->input('branch_id') !== (int) $active->id) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        if (! $this->filled('branch_id') && $active instanceof Branch) {
            $this->merge(['branch_id' => $active->id]);
        }

        parent::prepareForValidation();
    }
}
