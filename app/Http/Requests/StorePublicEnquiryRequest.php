<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Support\InstituteProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'course_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! InstituteProfile::isPublicCourseId((int) $value)) {
                        $fail('Please select a valid course.');
                    }
                },
            ],
            'city' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex' => 'Please enter a valid 10-digit Indian mobile number.',
            'course_id.exists' => 'Please select a valid course.',
        ];
    }
}
