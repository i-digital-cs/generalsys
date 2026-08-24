<?php

namespace App\Http\Requests\TeamLeader;

use App\Models\Attendance;
use App\Services\Attendance\AttendanceTimeReviewService;
use Illuminate\Foundation\Http\FormRequest;

class ForceClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendance = $this->route('attendance');
        $actor = $this->user();
        if (! $attendance instanceof Attendance || $actor === null) {
            return false;
        }

        return app(AttendanceTimeReviewService::class)->actorMayReview($actor, $attendance);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
