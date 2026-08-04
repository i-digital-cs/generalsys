<?php

namespace App\Http\Requests\TeamLeader;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ($this->user()->isTeamLeader() || $this->user()->isManager());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'performance_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'behavior_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'productivity_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_return_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
