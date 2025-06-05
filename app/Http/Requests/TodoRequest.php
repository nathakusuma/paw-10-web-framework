<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TodoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_completed' => ['sometimes', 'boolean'],
            'filter' => ['sometimes', 'string', 'in:all,active,completed'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle various boolean representations
        if ($this->has('is_completed')) {
            $value = $this->input('is_completed');

            // Convert string representations to boolean
            if (is_string($value)) {
                $boolValue = match(strtolower($value)) {
                    'true', '1', 'on', 'yes' => true,
                    'false', '0', 'off', 'no', '' => false,
                    default => (bool) $value
                };

                $this->merge(['is_completed' => $boolValue]);
            }
        }

        // Ensure filter has a default value
        if (!$this->has('filter') || !in_array($this->input('filter'), ['all', 'active', 'completed'])) {
            $this->merge(['filter' => 'all']);
        }
    }
}
