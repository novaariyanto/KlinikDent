<?php

namespace App\Http\Requests\Finance\SharingFee;

class UpdateSharingFeeRuleRequest extends StoreSharingFeeRuleRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('sharingFeeRule');

        return $rule
            ? ($this->user()?->can('update', $rule) ?? false)
            : false;
    }
}
