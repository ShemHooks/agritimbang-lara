<?php

namespace App\Http\Requests;

use App\Models\FarmerProfile;
use Illuminate\Validation\Rule;

class StoreEncoderTransactionRequest extends StoreTransactionRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->roles()
                ->where('slug', 'lgu_encoder')
                ->exists();
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'farmer_id' => [
                'required',
                'uuid',
                Rule::exists(
                    (new FarmerProfile())->getTable(),
                    'id'
                ),
            ],
            'buyer_phone' => [
                'required',
                'string',
                'max:30',
            ],
        ]);
    }
}
