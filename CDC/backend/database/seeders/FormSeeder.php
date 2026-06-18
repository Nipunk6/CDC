<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $companyUsers = User::where('role', 'company')->with('company')->get();

        foreach ($companyUsers as $companyUser) {
            $company = $companyUser->company;

            if (! $company instanceof Company) {
                continue;
            }

            $jnf = Jnf::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'job_title' => 'Graduate Engineer Trainee',
                ],
                [
                    'job_description' => 'Core engineering role for final year students.',
                    'job_location' => 'Jamshedpur',
                    'ctc_min' => 900000,
                    'ctc_max' => 1200000,
                    'vacancies' => 15,
                    'application_deadline' => now()->addWeeks(3)->toDateString(),
                    'status' => 'submitted',
                    'admin_remarks' => null,
                ]
            );

            $inf = Inf::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'internship_title' => 'Summer Internship Program',
                ],
                [
                    'internship_description' => '8-week internship program for pre-final year students.',
                    'internship_location' => 'Bengaluru',
                    'stipend' => 50000,
                    'internship_duration_weeks' => 8,
                    'vacancies' => 20,
                    'application_deadline' => now()->addWeeks(2)->toDateString(),
                    'status' => 'submitted',
                    'admin_remarks' => null,
                ]
            );

            FormStatusHistory::firstOrCreate([
                'form_type' => Jnf::class,
                'form_id' => $jnf->id,
                'old_status' => 'draft',
                'new_status' => 'submitted',
                'changed_by' => $companyUser->id,
                'remarks' => 'Submitted by company HR.',
            ]);

            FormStatusHistory::firstOrCreate([
                'form_type' => Inf::class,
                'form_id' => $inf->id,
                'old_status' => 'draft',
                'new_status' => 'submitted',
                'changed_by' => $companyUser->id,
                'remarks' => 'Submitted by company HR.',
            ]);

            if ($admin !== null) {
                PortalNotification::firstOrCreate([
                    'user_id' => $admin->id,
                    'title' => 'New forms submitted',
                    'message' => sprintf('%s submitted JNF and INF forms.', $company->name),
                    'type' => 'info',
                ]);
            }

            PortalNotification::firstOrCreate([
                'user_id' => $companyUser->id,
                'title' => 'Forms submitted',
                'message' => 'Your JNF and INF forms were submitted to CDC for review.',
                'type' => 'success',
            ]);

            EmailLog::firstOrCreate([
                'user_id' => $companyUser->id,
                'recipient_email' => $companyUser->email,
                'subject' => 'Form Submission Confirmation',
                'template' => 'company_form_submission',
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);
        }
    }
}
