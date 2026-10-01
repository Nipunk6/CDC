<?php

namespace App\Jobs;

use App\Models\StudentProfile;
use App\Services\StudentAccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E1. Queued so that a 3000-row import does not create 3000 bcrypt reset tokens inside the HTTP request.
 */
class SendStudentInvitation implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $studentProfileId)
    {
    }

    public function handle(StudentAccountService $accounts): void
    {
        $student = StudentProfile::query()->with('user')->find($this->studentProfileId);

        if ($student && $student->user) {
            $accounts->deliverInvitation($student);
        }
    }
}
