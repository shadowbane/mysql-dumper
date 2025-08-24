<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'minute' => ['required', 'integer', 'min:0', 'max:59'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'data_source_ids' => ['required', 'array', 'min:1'],
            'data_source_ids.*' => ['exists:data_sources,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Schedule name is required.',
            'hour.required' => 'Hour is required.',
            'hour.min' => 'Hour must be between 0 and 23.',
            'hour.max' => 'Hour must be between 0 and 23.',
            'minute.required' => 'Minute is required.',
            'minute.min' => 'Minute must be between 0 and 59.',
            'minute.max' => 'Minute must be between 0 and 59.',
            'days_of_week.required' => 'At least one day must be selected.',
            'days_of_week.min' => 'At least one day must be selected.',
            'days_of_week.*.min' => 'Invalid day selected.',
            'days_of_week.*.max' => 'Invalid day selected.',
            'data_source_ids.required' => 'At least one data source must be selected.',
            'data_source_ids.min' => 'At least one data source must be selected.',
            'data_source_ids.*.exists' => 'Selected data source does not exist.',
        ];
    }
}
