<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;

/**
 * Hand-shaped, whitelisted arrays for student-facing posting payloads. Never serialise a Company
 * model here: it carries recruiter contacts (PROJECT_CONTEXT §13).
 */
final class PostingPresenter
{
    /** form_data keys a student may see. `signatory`, `declarations` and unknown keys are dropped. */
    private const FORM_KEYS = [
        'jobTitle', 'jobDesignation', 'jobLocation', 'jobDescription',
        'internshipTitle', 'internshipDesignation', 'internshipLocation', 'internshipDescription',
        'workMode', 'expectedHires', 'minimumHires', 'duration', 'joiningMonth', 'skills', 'additionalInfo', 'registrationLink',
        'eligibility', 'globalCgpa', 'globalBacklogs', 'genderFilter', 'slpRequirement', 'graduatingBatch',
        'minTenthPercent', 'minTwelfthPercent',
        'currency', 'salarySameForAll', 'stipendSameForAll', 'programmeSalaries', 'salaryComponents', 'programmeStipends',
        'ppoProvision', 'ppoCtc', 'selectionRounds',
    ];

    /** companyProfile keys a student may see — postal address and anything unknown are dropped. */
    private const COMPANY_PROFILE_KEYS = [
        'name', 'website', 'sector', 'employeeCount', 'categoryOrgType', 'dateOfEstablishment', 'annualTurnover',
        'linkedinUrl', 'industrySectorTags', 'mncHqCountryCity', 'natureOfBusiness', 'companyDescription', 'logoUrl',
    ];

    /** @return array<string, mixed> */
    public static function card(JobPosting $posting, ?string $programme = null): array
    {
        $company = $posting->company();
        $data = $posting->formData();
        $isInf = $posting->formType() === 'inf';

        return [
            'id' => $posting->id,
            'type' => $posting->postingType(),
            // Float-time offer category (QA F-004): Full-Time, Intern + Full-Time, Internship, Intern + performance PPO.
            'offer_type' => $posting->offerType(),
            'offer_label' => Offer::LABELS[$posting->offerType()] ?? $posting->offerType(),
            'title' => $posting->title(),
            'designation' => (string) ($data[$isInf ? 'internshipDesignation' : 'jobDesignation'] ?? ''),
            'location' => (string) ($data[$isInf ? 'internshipLocation' : 'jobLocation'] ?? ''),
            'work_mode' => (string) ($data['workMode'] ?? ''),
            'company' => [
                'name' => $company?->name ?? (string) ($data['companyProfile']['name'] ?? ''),
                'logo_url' => $company?->logo_url,
                'sector' => $company?->sector,
            ],
            'placement_cycle' => $posting->placementCycle ? $posting->placementCycle->only(['id', 'name', 'type']) : null,
            'status' => $posting->status,
            'application_deadline' => $posting->application_deadline,
            'deadline_passed' => $posting->deadlinePassed(),
            // For the shared status label (fix L17); students never see counts, only this yes/no.
            'any_stage_published' => $posting->relationLoaded('rounds')
                ? $posting->rounds->contains(fn ($r) => $r->status === 'completed')
                : $posting->rounds()->where('status', 'completed')->exists(),
            'accepts_applications' => $posting->acceptsApplications(),
            'floated_at' => $posting->floated_at,
            'visit_date' => $posting->visit_date?->toDateString(),
            'compensation' => $posting->compensationFor($programme),
        ];
    }

    /** @return array<string, mixed> the form payload with contact / signatory data removed */
    public static function formData(JobPosting $posting): array
    {
        $data = $posting->formData();
        $safe = array_intersect_key($data, array_flip(self::FORM_KEYS));

        // Show the eligibility that is actually enforced: the rules frozen at float time (D66).
        $safe = array_replace($safe, array_intersect_key($posting->eligibilityRules(), array_flip(self::FORM_KEYS)));

        $profile = is_array($data['companyProfile'] ?? null) ? $data['companyProfile'] : [];
        $safe['companyProfile'] = array_intersect_key($profile, array_flip(self::COMPANY_PROFILE_KEYS));

        return $safe;
    }

    /** @return array<string, mixed> */
    public static function application(?Application $application): ?array
    {
        if (! $application) {
            return null;
        }

        return [
            'id' => $application->id,
            'status' => $application->status,
            'resume_id' => $application->resume_id,
            'answers' => $application->answers ?? [],
            'used_unverified_resume' => $application->used_unverified_resume,
            'applied_at' => $application->applied_at,
            'withdrawn_at' => $application->withdrawn_at,
        ];
    }

    /**
     * The student's own trail: every round, with only PUBLISHED results filled in (spec Q4.3/Q4.7).
     *
     * @return list<array<string, mixed>>
     */
    public static function trail(JobPosting $posting, ?Application $application): array
    {
        $results = [];

        if ($application) {
            $results = ApplicationRoundResult::query()
                ->where('application_id', $application->id)
                ->whereNotNull('published_at')
                ->get()
                ->keyBy('posting_round_id')
                ->all();
        }

        return $posting->rounds->map(function ($round) use ($results) {
            $result = $results[$round->id] ?? null;

            return [
                'round_id' => $round->id,
                'name' => $round->name,
                'round_type' => $round->round_type,
                'sort_order' => $round->sort_order,
                'scheduled_at' => $round->scheduled_at,
                'venue' => $round->venue,
                'is_final' => $round->is_final,
                'published' => $result !== null,
                'attendance' => $result?->attendance,
                'result' => $result?->result,
                'is_addendum' => $result ? (bool) $result->is_addendum : false,
            ];
        })->values()->all();
    }
}
