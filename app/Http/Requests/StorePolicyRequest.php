<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:100'],
            'agent_id' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:trip_start'],
            'destination' => ['required', 'string', 'in:DOMESTIC,INTERNATIONAL,domestic,international'],
            'trip_start' => ['required', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', 'in:BASIC,PLUS,PREMIUM,basic,plus,premium'],
            'sum_insured' => ['required', 'integer', 'in:100000,300000,500000'],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip end date must be on or after the trip start date.',
            'date_of_birth.before' => 'The date of birth must be before the trip start date.',
            'plan_code.in' => 'The plan code must be one of: BASIC, PLUS, PREMIUM.',
            'sum_insured.in' => 'The sum insured must be one of: 100000, 300000, 500000.',
            'destination.in' => 'The destination must be either DOMESTIC or INTERNATIONAL.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed for policy intake request.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
