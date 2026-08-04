<?php

namespace App\Http\Requests\TeamLeader;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isTeamLeader() || $user->isManager());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_leader_response' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_leader_response' => __('report.tl.response_label'),
        ];
    }
}
