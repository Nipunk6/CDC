<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An accepted JNF/INF floated into a placement cycle ("a float").
 */
class JobPosting extends Model
{
    use HasFactory;

    /** form_data keys frozen into eligibility_snapshot at float time (spec M5.3). */
    public const SNAPSHOT_KEYS = [
        'eligibility', 'globalCgpa', 'globalBacklogs', 'genderFilter', 'graduatingBatch', 'minTenthPercent', 'minTwelfthPercent',
        // S8.4: ids of Student Categories, at least one of which a student needs (empty = no restriction).
        'allowedStudentCategories',
    ];

    /** Phase 1 selection-round slugs → display names (mirrors selectionprocessbuilder.tsx). */
    public const ROUND_LABELS = [
        'ppt' => 'Pre-Placement Talk',
        'resume' => 'Resume Shortlisting',
        'written_test' => 'Written Test',
        'online_test' => 'Online Test',
        'take_home_assignment' => 'Take Home Assignment',
        'aptitude_test' => 'Aptitude Test',
        'technical_test' => 'Technical Test',
        'group_discussion' => 'Group Discussion',
        'hr_interview' => 'HR Interview',
        'technical_interview' => 'Technical Interview',
        'psychometric' => 'Psychometric Test',
        'medical' => 'Medical Test',
        'other' => 'Other',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'postable_type',
        'postable_id',
        'placement_cycle_id',
        'application_deadline',
        'status',
        'share_contact_details',
        'offer_type',
        'floated_by',
        'floated_at',
        'eligibility_snapshot',
        'visit_date',
        'scheduled_open_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_deadline' => 'datetime',
            'floated_at' => 'datetime',
            'share_contact_details' => 'boolean',
            'eligibility_snapshot' => 'array',
            'visit_date' => 'date',
            'scheduled_open_at' => 'datetime',
        ];
    }

    /**
     * Job profiles students may see: not scheduled to open later (Superset parity S6.2, "Schedule For Later") and not
     * in a Draft placement (S8.3; a draft takes no job profiles, this keeps students safe if one ever holds some).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<JobPosting>  $query
     */
    public function scopeReleased(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('scheduled_open_at')->orWhere('scheduled_open_at', '<=', now()))
            ->whereIn('placement_cycle_id', PlacementCycle::query()->visibleToStudents()->select('id'));
    }

    /** Waiting to open (S6.2): only an `open` job profile whose opening time is still ahead (fix M4). */
    public function isScheduled(): bool
    {
        return $this->status === 'open' && $this->scheduled_open_at !== null && $this->scheduled_open_at->isFuture();
    }

    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    public function placementCycle(): BelongsTo
    {
        return $this->belongsTo(PlacementCycle::class);
    }

    public function floatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'floated_by');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(PostingRound::class)->orderBy('sort_order')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PostingQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PostingDocument::class)->orderBy('id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ShortlistProposal::class);
    }

    public function formType(): string
    {
        return $this->postable_type === Inf::class ? 'inf' : 'jnf';
    }

    /** fulltime for JNFs, internship for INFs. */
    public function postingType(): string
    {
        return $this->formType() === 'inf' ? 'internship' : 'fulltime';
    }

    /** Offer categories the admin can pick at float time, per form type (owner decision, QA F-004). */
    public const OFFER_CATEGORIES = [
        'jnf' => ['fulltime', 'intern_fulltime'],
        'inf' => ['intern', 'intern_performance_ppo'],
    ];

    /** The posting's offer category (float-time choice; legacy postings fall back to JNF → fulltime, INF → intern). */
    public function offerType(): string
    {
        return $this->offer_type ?: ($this->formType() === 'inf' ? 'intern' : 'fulltime');
    }

    /** @return array<string, mixed> */
    public function formData(): array
    {
        $data = $this->postable?->form_data;

        return is_array($data) ? $data : [];
    }

    /**
     * Eligibility rules frozen at float time; falls back to the live form for rows without a snapshot.
     *
     * @return array<string, mixed>
     */
    public function eligibilityRules(): array
    {
        if (is_array($this->eligibility_snapshot) && $this->eligibility_snapshot !== []) {
            return $this->eligibility_snapshot;
        }

        return self::withoutAdminOnlyKeys(array_intersect_key($this->formData(), array_flip(self::SNAPSHOT_KEYS)));
    }

    /**
     * Eligibility keys only the CDC may set (fix M2): `allowedStudentCategories` comes from the admin's validated input
     * (open dialog, Edit eligibility, Add New Job), never from a JNF/INF's form_data, which the company writes.
     */
    public const ADMIN_ONLY_KEYS = ['allowedStudentCategories'];

    public static function withoutAdminOnlyKeys(?array $data): ?array
    {
        return $data === null ? null : array_diff_key($data, array_flip(self::ADMIN_ONLY_KEYS));
    }

    public function title(): string
    {
        $form = $this->postable;
        $data = $this->formData();

        if ($this->formType() === 'inf') {
            return (string) ($data['internshipTitle'] ?? $form?->internship_title ?? 'Internship');
        }

        return (string) ($data['jobTitle'] ?? $form?->job_title ?? 'Job');
    }

    public function company(): ?Company
    {
        return $this->postable?->company;
    }

    public function deadlinePassed(): bool
    {
        return $this->application_deadline === null || $this->application_deadline->isPast();
    }

    public function acceptsApplications(): bool
    {
        // A closed placement cycle stops taking applications for all of its postings (D67).
        return $this->status === 'open' && ! $this->isScheduled() && ! $this->deadlinePassed() && ($this->placementCycle?->isOpen() ?? true);
    }

    /**
     * Compensation for one programme (or the best across programmes when null / not listed).
     *
     * @return array{currency: string, ctc_annual: int|null, stipend_monthly: int|null, duration_weeks: int|null, ppo: bool}
     */
    public function compensationFor(?string $programme = null): array
    {
        $data = $this->formData();
        $currency = (string) ($data['currency'] ?? 'INR');
        $isInf = $this->formType() === 'inf';
        $rows = array_values(array_filter(
            is_array($data[$isInf ? 'programmeStipends' : 'programmeSalaries'] ?? null) ? $data[$isInf ? 'programmeStipends' : 'programmeSalaries'] : [],
            static fn ($row) => is_array($row) && ($row['enabled'] ?? false)
        ));

        $valueOf = static function (array $row) use ($isInf): ?int {
            $raw = $isInf ? ($row['total'] ?? '') ?: ($row['baseStipend'] ?? '') : ($row['ctcAnnual'] ?? '');
            $digits = preg_replace('/[^0-9.]/', '', (string) $raw) ?? '';

            return is_numeric($digits) ? (int) round((float) $digits) : null;
        };

        $amount = null;
        if ($programme !== null) {
            foreach ($rows as $row) {
                if (\App\Support\ProgrammeCatalogue::sameProgramme((string) ($row['programme'] ?? ''), $programme)) {
                    $amount = $valueOf($row);
                    break;
                }
            }
        }

        if ($amount === null) {
            $values = array_filter(array_map($valueOf, $rows), static fn ($v) => $v !== null);
            $amount = $values === [] ? null : max($values);
        }

        if ($amount === null) {
            $amount = $isInf ? $this->postable?->stipend : ($this->postable?->ctc_max ?: $this->postable?->ctc_min);
            $amount = $amount ? (int) $amount : null;
        }

        return [
            'currency' => $currency,
            'ctc_annual' => $isInf ? null : $amount,
            'stipend_monthly' => $isInf ? $amount : null,
            'duration_weeks' => $isInf ? ((int) ($data['duration'] ?? $this->postable?->internship_duration_weeks ?? 0) ?: null) : null,
            'ppo' => $isInf && (bool) ($data['ppoProvision'] ?? false),
        ];
    }
}
