<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTransferEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nonBlankString = ['required', 'string', 'regex:/\\S/u', 'max:191'];

        return [
            'events' => ['required', 'array'],
            'events.*.event_id' => $nonBlankString,
            'events.*.station_id' => $nonBlankString,
            'events.*.amount' => ['required', 'numeric', 'min:0'],
            'events.*.status' => ['required', 'string', 'regex:/\\S/u', 'max:64'],
            'events.*.created_at' => ['required', 'string', 'regex:/\\S/u', 'date_format:Y-m-d\\TH:i:s\\Z'],
        ];
    }

    public function messages(): array
    {
        return [
            'events.required' => 'The events field is required.',
            'events.array' => 'The events field must be an array.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 400));
    }
}
