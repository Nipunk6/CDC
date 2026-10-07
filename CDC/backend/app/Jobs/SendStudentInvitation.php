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

        // Revoked after this job was queued, or the student already set a password: no new link (S5).
        if ($student && $student->user && $student->user->activated_at === null && $student->user->invite_revoked_at === null) {
            $accounts->deliverInvitation($student);
        }
    }
}
