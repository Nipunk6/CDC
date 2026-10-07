<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel exports (spec B7 / M9). Resume cells are clickable 30-day signed links that open without logging in.
 */
class ExportService
{
    private const MAROON = '7B1113';

    /**
     * @param  'admin'|'company'  $audience  company = restricted Q10.2 field set, published outcomes only
     */
    public function applicantsWorkbook(JobPosting $posting, string $audience): StreamedResponse
    {
        $admin = $audience === 'admin';
        $posting->loadMissing(['rounds', 'questions', 'postable.company']);

        $applications = $posting->applications()
            ->when(! $admin, fn ($q) => $q->where('status', 'applied'))
            ->with(['studentProfile', 'resume', 'roundResults', 'offer'])
            ->orderBy('applied_at')
            ->get();

        $share = $admin || $posting->share_contact_details;

        $columns = [
            'Roll No' => fn (Application $a) => $a->studentProfile->roll_no,
            'Name' => fn (Application $a) => $a->studentProfile->full_name,
            'Programme' => fn (Application $a) => $a->studentProfile->programme,
            'Branch' => fn (Application $a) => $a->studentProfile->branch,
            'Batch' => fn (Application $a) => $a->studentProfile->graduating_batch,
            'CGPA' => fn (Application $a) => $this->num($a->studentProfile->current_cgpa),
            'Ongoing Backlogs' => fn (Application $a) => $a->studentProfile->ongoing_backlogs,
            'Total Backlogs' => fn (Application $a) => $a->studentProfile->total_backlogs,
            '10th %' => fn (Application $a) => $this->num($a->studentProfile->tenth_percent),
            '12th %' => fn (Application $a) => $this->num($a->studentProfile->twelfth_percent),
        ];

        if ($admin) {
            $columns += [
                'Gender' => fn (Application $a) => ucfirst((string) $a->studentProfile->gender),
                'Date of Birth' => fn (Application $a) => $a->studentProfile->date_of_birth?->format('Y-m-d'),
                'Category' => fn (Application $a) => $a->studentProfile->category,
                'PwD' => fn (Application $a) => $a->studentProfile->pwd ? 'Yes' : 'No',
                'Home State' => fn (Application $a) => $a->studentProfile->home_state,
                'LinkedIn' => fn (Application $a) => $a->studentProfile->linkedin_url,
                'GitHub' => fn (Application $a) => $a->studentProfile->github_url,
            ];
        }

        if ($share) {
            $columns += [
                'Institute Email' => fn (Application $a) => $a->studentProfile->institute_email,
                'Personal Email' => fn (Application $a) => $a->studentProfile->personal_email,
                'Phone' => fn (Application $a) => $a->studentProfile->phone,
            ];
        }

        if ($admin) {
            $columns += [
                'Application Status' => fn (Application $a) => ucfirst($a->status),
                'Applied At (IST)' => fn (Application $a) => $a->applied_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
                'Resume Label' => fn (Application $a) => $a->resume?->label,
                'Resume Verified' => fn (Application $a) => $a->resume ? ucfirst($a->resume->status) : null,
                'Unverified Resume Flag' => fn (Application $a) => $a->used_unverified_resume ? 'YES' : '',
                'Placed Elsewhere Flag' => fn (Application $a) => $a->placed_elsewhere_flag ? 'YES' : '',
            ];
        }

        // Columns keyed by position so equal round names / question openings never merge (D88).
        foreach ($posting->questions->values() as $qi => $question) {
            $columns['Q'.($qi + 1).': '.Str::limit($question->question, 60)] = function (Application $a) use ($question) {
                $answer = collect($a->answers ?? [])->firstWhere('question_id', $question->id)['answer'] ?? null;

                return is_array($answer) ? implode(', ', $answer) : $answer;
            };
        }

        foreach ($posting->rounds->values() as $ri => $round) {
            $columns['R'.($ri + 1).': '.$round->name] = function (Application $a) use ($round, $admin) {
                /** @var ApplicationRoundResult|null $row */
                $row = $a->roundResults->firstWhere('posting_round_id', $round->id);
                if (! $row || (! $admin && ! $row->isPublished())) {
                    return null;
                }
                $text = ucfirst($row->result);
                if ($row->attendance) {
                    $text .= $row->attendance === 'yes' ? ' (appeared)' : ' (absent)';
                }
                if ($row->is_addendum) {
                    $text .= ' [addendum]';
                }

                return $admin && ! $row->isPublished() ? $text.' (draft)' : $text;
            };
        }

        if ($admin) {
            $columns += [
                'Offer Type' => fn (Application $a) => $a->offer ? (Offer::LABELS[$a->offer->offer_type] ?? $a->offer->offer_type) : null,
                'Offer CTC (annual)' => fn (Application $a) => $a->offer?->ctc_annual,
                'Offer Stipend (monthly)' => fn (Application $a) => $a->offer?->stipend_monthly,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Applicants');

        $headers = array_merge(array_keys($columns), ['Resume Link']);
        $this->writeHeader($sheet, $headers);
        $linkColumn = count($headers);

        $rowNumber = 2;
        foreach ($applications as $application) {
            $col = 1;
            foreach ($columns as $value) {
                $this->put($sheet, $col++, $rowNumber, $value($application));
            }
            if ($application->resume) {
                $url = $application->resume->signedUrl(30);
                $cell = Coordinate::stringFromColumnIndex($linkColumn).$rowNumber;
                $sheet->setCellValue($cell, 'Open resume');
                $sheet->getCell($cell)->getHyperlink()->setUrl($url);
                $sheet->getStyle($cell)->getFont()->setUnderline(true)->getColor()->setRGB('1D4ED8');
            }
            $rowNumber++;
        }

        $this->finish($sheet, count($headers));

        $company = $posting->company()?->name ?? 'company';

        return $this->stream($spreadsheet, Str::slug($company.' '.$posting->title()).'-applicants.xlsx');
    }

    /**
     * Every student enrolled in a cycle with their academics, applications, offers and blocks (admin only).
     * `$narrow` limits the enrolments, e.g. to the enrolled list's filters (M5); without it every enrolment is listed.
     */
    public function studentsWorkbook(PlacementCycle $cycle, ?callable $narrow = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');

        $headers = [
            'Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'Gender', 'CGPA', 'Ongoing Backlogs', 'Total Backlogs', '10th %', '12th %',
            'Category', 'PwD', 'Home State', 'Institute Email', 'Personal Email', 'Phone', 'Account', 'Enrolment',
            'Applications (live)', 'Offers', 'Best CTC (annual, INR)', 'Best Stipend (monthly, INR)', 'Active Blocks',
        ];
        $this->writeHeader($sheet, $headers);

        $rowNumber = 2;
        $cycle->enrollments()
            ->when($narrow !== null, fn ($query) => $narrow($query))
            ->with(['studentProfile.user:id,is_active'])
            ->orderBy('id')
            ->chunk(200, function ($enrollments) use ($sheet, $cycle, &$rowNumber): void {
                $studentIds = $enrollments->pluck('student_profile_id');

                $applications = \App\Models\Application::query()
                    ->whereIn('student_profile_id', $studentIds)
                    ->where('status', 'applied')
                    ->whereHas('jobPosting', fn ($q) => $q->where('placement_cycle_id', $cycle->id))
                    ->selectRaw('student_profile_id, count(*) as total')
                    ->groupBy('student_profile_id')
                    ->pluck('total', 'student_profile_id');

                $offers = Offer::query()->with('company:id,name')
                    ->whereIn('student_profile_id', $studentIds)
                    ->where('placement_cycle_id', $cycle->id)
                    ->get()
                    ->groupBy('student_profile_id');

                $blocks = \App\Models\PlacementBlock::query()->with('offer:id,offer_type')
                    ->whereIn('student_profile_id', $studentIds)
                    ->where('placement_cycle_id', $cycle->id)
                    ->where('active', true)
                    ->get()
                    ->groupBy('student_profile_id');

                foreach ($enrollments as $enrollment) {
                    /** @var StudentProfile $s */
                    $s = $enrollment->studentProfile;
                    $studentOffers = $offers->get($s->id, collect());

                    $values = [
                        $s->roll_no, $s->full_name, $s->programme, $s->branch, $s->graduating_batch, ucfirst((string) $s->gender),
                        $this->num($s->current_cgpa), $s->ongoing_backlogs, $s->total_backlogs, $this->num($s->tenth_percent), $this->num($s->twelfth_percent),
                        $s->category, $s->pwd ? 'Yes' : 'No', $s->home_state, $s->institute_email, $s->personal_email, $s->phone,
                        ($s->user?->is_active ?? true) ? 'Active' : 'Suspended',
                        ucfirst($enrollment->status),
                        (int) ($applications[$s->id] ?? 0),
                        $studentOffers->map(fn (Offer $o) => ($o->company?->name ?? '').' ('.(Offer::LABELS[$o->offer_type] ?? $o->offer_type).($o->currency !== 'INR' ? ', '.$o->currency : '').')')->implode('; '),
                        $studentOffers->where('currency', 'INR')->max('ctc_annual'),
                        $studentOffers->where('currency', 'INR')->max('stipend_monthly'),
                        $blocks->get($s->id, collect())->map(fn ($b) => $b->message())->implode('; '),
                    ];
                    foreach ($values as $index => $value) {
                        $this->put($sheet, $index + 1, $rowNumber, $value);
                    }
                    $rowNumber++;
                }
            });

        $this->finish($sheet, count($headers));

        return $this->stream($spreadsheet, Str::slug($cycle->name).'-students.xlsx');
    }

    /**
     * "Download Current Shortlist" for one stage (Superset parity S1.4, admin only): a title row, the candidates with
     * their current decision and whether it is published or still a draft, and a footer with the download time (IST).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows  AdminShortlistController::candidates()
     */
    public function shortlistWorkbook(JobPosting $posting, \App\Models\PostingRound $round, string $nextLabel, \Illuminate\Support\Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Shortlist');

        $company = $posting->company()?->name ?? 'Company';
        $headers = ['S.No.', 'Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'CGPA', 'Institute Email', 'Phone', 'Decision', 'Published', 'Attendance', 'Addendum', 'Remark'];

        $this->put($sheet, 1, 1, sprintf('%s · %s: students shortlisted during \'%s\' to be proceeded to %s', $company, $posting->title(), $round->name, $nextLabel));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->writeHeader($sheet, $headers, 3);

        $labels = ['selected' => $round->is_final ? 'Selected' : 'Shortlisted', 'waitlisted' => 'On Hold', 'rejected' => 'Not selected', 'pending' => 'Undecided'];
        $rowNumber = 4;
        foreach ($rows->values() as $index => $row) {
            $s = $row['student'];
            $values = [
                $index + 1, $s['roll_no'], $s['full_name'], $s['programme'], $s['branch'], $s['graduating_batch'], $this->num($s['current_cgpa']),
                $s['institute_email'], $s['phone'],
                $row['result'] ? ($labels[$row['result']] ?? ucfirst($row['result'])) : 'Undecided',
                $row['result'] && $row['result'] !== 'pending' ? ($row['published'] ? 'Published' : 'Draft') : '',
                $row['attendance'] === 'yes' ? 'Appeared' : ($row['attendance'] === 'no' ? 'Absent' : ''),
                $row['is_addendum'] ? 'Yes' : '',
                $row['remark'],
            ];
            foreach ($values as $col => $value) {
                $this->put($sheet, $col + 1, $rowNumber, $value);
            }
            $rowNumber++;
        }

        $lastData = max(3, $rowNumber - 1);
        $this->put($sheet, 1, $rowNumber + 1, 'Downloaded on '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST');
        $sheet->getStyle('A'.($rowNumber + 1))->getFont()->setItalic(true);

        $this->finish($sheet, count($headers), 'A3:'.Coordinate::stringFromColumnIndex(count($headers)).$lastData);
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(8);

        return $this->stream($spreadsheet, Str::slug($company.' '.$posting->title().' '.$round->name).'-shortlist.xlsx');
    }

    /**
     * A workbook laid out by an Excel Template (Superset parity S3): the template's columns, in its order, with its
     * display names. `$audience = 'company'` drops every admin-only field (and contact fields unless the job profile
     * shares contact details), whatever the template lists. Expanding fields (answers, stages) need `$posting`.
     *
     * @param  iterable<int, array<string, mixed>>  $contexts  rows: ['student' => StudentProfile, 'application' => ?Application, ...]
     * @param  list<string>  $titleRows  optional lines written above the header
     * @param  bool  $footer  add the "Downloaded on … IST" line under the rows, as the default download does
     */
    public function templateWorkbook(\App\Models\ExportTemplate $template, iterable $contexts, ?JobPosting $posting, string $audience, string $fileName, array $titleRows = [], bool $footer = false): StreamedResponse
    {
        $fields = \App\Support\ExportFieldCatalogue::fields();
        $company = $audience === 'company';
        $shareContact = ! $company || (bool) $posting?->share_contact_details;
        $posting?->loadMissing(['rounds', 'questions']);
        $contexts = collect($contexts)->values();

        // Resolve the template's columns into concrete [header, resolver] pairs.
        $columns = [];
        $cycleIds = [];
        foreach ($template->columns ?? [] as $column) {
            $key = $column['key'] ?? null;
            $field = $key ? ($fields[$key] ?? null) : null;
            if (! $field) {
                continue; // a field that no longer exists is skipped
            }
            if ($company && ($field['audience'] === 'admin' || ($field['audience'] === 'contact' && ! $shareContact))) {
                continue;
            }
            $label = trim((string) ($column['label'] ?? '')) ?: $field['label'];

            if (($field['expand'] ?? null) === 'questions') {
                foreach (($posting?->questions ?? collect())->values() as $qi => $question) {
                    $columns[] = [\App\Support\ExportFieldCatalogue::questionHeader($qi + 1, $question->question), function (array $c) use ($question) {
                        $answer = collect($c['application']?->answers ?? [])->firstWhere('question_id', $question->id)['answer'] ?? null;

                        return is_array($answer) ? implode(', ', $answer) : $answer;
                    }];
                }

                continue;
            }
            if (($field['expand'] ?? null) === 'rounds') {
                foreach (($posting?->rounds ?? collect())->values() as $ri => $round) {
                    $columns[] = ['R'.($ri + 1).': '.$round->name, fn (array $c) => \App\Support\ExportFieldCatalogue::stageText(
                        $c['application']?->roundResults->firstWhere('posting_round_id', $round->id),
                        $company,
                        (bool) $round->is_final
                    )];
                }

                continue;
            }
            if ($field['cycle'] ?? false) {
                $cycleId = (int) ($column['cycle_id'] ?? 0);
                if ($cycleId <= 0) {
                    continue;
                }
                $cycleIds[$cycleId] = true;
                $resolver = $field['value'];
                $columns[] = [$label, fn (array $c) => $resolver(['cycle' => $c['cycles'][$cycleId] ?? []] + $c)];

                continue;
            }
            $columns[] = [$label, $field['value']];
        }

        // Cycle-specific data for every student, loaded once per chosen cycle.
        $cycleData = [];
        $studentIds = $contexts->map(fn ($c) => $c['student']->id)->unique()->values();
        foreach (array_keys($cycleIds) as $cycleId) {
            $enrollments = \App\Models\CycleEnrollment::query()->where('placement_cycle_id', $cycleId)->whereIn('student_profile_id', $studentIds)->get()->keyBy('student_profile_id');
            $offers = Offer::query()->with('company:id,name')->where('placement_cycle_id', $cycleId)->whereIn('student_profile_id', $studentIds)->get()->groupBy('student_profile_id');
            $blocks = \App\Models\PlacementBlock::query()->with('offer:id,offer_type')->where('placement_cycle_id', $cycleId)->where('active', true)->whereIn('student_profile_id', $studentIds)->get()->groupBy('student_profile_id');
            $cycleData[$cycleId] = compact('enrollments', 'offers', 'blocks');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $headerRow = 1;
        foreach ($titleRows as $i => $line) {
            $this->put($sheet, 1, $i + 1, $line);
            $sheet->getStyle('A'.($i + 1))->getFont()->setBold(true);
            $headerRow = $i + 3;
        }
        $headers = array_column($columns, 0);
        if ($headers === []) {
            $headers = ['(This template has no columns this download may include.)'];
        }
        $this->writeHeader($sheet, $headers, $headerRow);

        $rowNumber = $headerRow + 1;
        foreach ($contexts as $index => $context) {
            $sid = $context['student']->id;
            $context += ['application' => null, 'posting' => $posting, 'applied' => null, 'decision' => null, 'enrollment' => null];
            $context['index'] = $index + 1;
            $context['company_view'] = $company;
            $context['cycles'] = [];
            foreach ($cycleData as $cycleId => $data) {
                $context['cycles'][$cycleId] = [
                    'enrollment' => $data['enrollments']->get($sid),
                    'offers' => $data['offers']->get($sid, collect())->all(),
                    'blocks' => $data['blocks']->get($sid, collect())->all(),
                ];
            }

            foreach ($columns as $ci => [, $resolver]) {
                $value = $resolver($context);
                if (is_array($value) && isset($value['link'])) {
                    $cell = Coordinate::stringFromColumnIndex($ci + 1).$rowNumber;
                    $sheet->setCellValueExplicit($cell, 'Link', DataType::TYPE_STRING);
                    $sheet->getCell($cell)->getHyperlink()->setUrl($value['link']);
                    $sheet->getStyle($cell)->getFont()->setUnderline(true)->getColor()->setRGB('1D4ED8');

                    continue;
                }
                $this->put($sheet, $ci + 1, $rowNumber, $value);
            }
            $rowNumber++;
        }

        if ($footer) {
            $this->put($sheet, 1, $rowNumber + 1, 'Downloaded on '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST');
            $sheet->getStyle('A'.($rowNumber + 1))->getFont()->setItalic(true);
        }

        $this->finish($sheet, count($headers), 'A'.$headerRow.':'.Coordinate::stringFromColumnIndex(count($headers)).max($headerRow, $rowNumber - 1));

        return $this->stream($spreadsheet, $fileName);
    }

    /**
     * "Download Placement Report" for one student (Superset parity S4.5, admin only): every application with its job
     * profile, company, cycle, status, each stage's result and attendance (drafts marked), and the offer.
     *
     * @param  \Illuminate\Support\Collection<int, Application>  $applications  StudentRecordService::applications()
     */
    public function studentPlacementReport(StudentProfile $student, \Illuminate\Support\Collection $applications): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Placement Report');

        $maxStages = (int) $applications->map(fn (Application $a) => $a->jobPosting?->rounds->count() ?? 0)->max();
        $headers = ['S.No.', 'Job Profile', 'Company', 'Placement', 'Application Status', 'Applied At (IST)'];
        for ($i = 1; $i <= $maxStages; $i++) {
            array_push($headers, 'Stage '.$i, 'Stage '.$i.' Result', 'Stage '.$i.' Attendance');
        }
        array_push($headers, 'Offer', 'Offer CTC (annual)', 'Offer Stipend (monthly)');

        $this->put($sheet, 1, 1, sprintf('Placement Report: %s (%s)', $student->full_name, $student->roll_no));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->writeHeader($sheet, $headers, 3);

        $rowNumber = 4;
        foreach ($applications->values() as $index => $application) {
            $posting = $application->jobPosting;
            $values = [
                $index + 1,
                $posting?->title(),
                $posting?->company()?->name,
                $posting?->placementCycle?->name,
                ucfirst((string) $application->status),
                $application->applied_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ];
            $rounds = $posting?->rounds->values() ?? collect();
            for ($i = 0; $i < $maxStages; $i++) {
                $round = $rounds->get($i);
                /** @var ApplicationRoundResult|null $row */
                $row = $round ? $application->roundResults->firstWhere('posting_round_id', $round->id) : null;
                $result = \App\Services\StudentRecordService::resultLabel($row?->result, (bool) $round?->is_final);
                array_push(
                    $values,
                    $round?->name,
                    $result !== null && ! $row->isPublished() ? $result.' (draft)' : $result,
                    $round ? \App\Services\StudentRecordService::attendanceLabel($row?->attendance) : null
                );
            }
            $offer = $application->offer;
            array_push(
                $values,
                $offer ? (Offer::LABELS[$offer->offer_type] ?? $offer->offer_type) : null,
                $offer?->ctc_annual,
                $offer?->stipend_monthly
            );
            foreach ($values as $col => $value) {
                $this->put($sheet, $col + 1, $rowNumber, $value);
            }
            $rowNumber++;
        }

        $lastData = max(3, $rowNumber - 1);
        $this->put($sheet, 1, $rowNumber + 1, 'Downloaded on '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST');
        $sheet->getStyle('A'.($rowNumber + 1))->getFont()->setItalic(true);
        $this->finish($sheet, count($headers), 'A3:'.Coordinate::stringFromColumnIndex(count($headers)).$lastData);

        return $this->stream($spreadsheet, Str::slug($student->roll_no.' placement report').'.xlsx');
    }

    /**
     * "Download Eligibility Report" for one student (Superset parity S4.5, admin only): every non-cancelled job
     * profile in the student's cycles, Eligible Yes/No and the EligibilityService::check() reasons.
     *
     * @param  list<array{posting: JobPosting, eligible: bool, reasons: list<string>}>  $rows  StudentRecordService::eligibility()
     */
    public function studentEligibilityReport(StudentProfile $student, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Eligibility Report');

        $headers = ['S.No.', 'Placement', 'Job Profile', 'Company', 'Job Profile Status', 'Application Deadline (IST)', 'Eligible', 'Reasons'];
        $this->put($sheet, 1, 1, sprintf('Eligibility Report: %s (%s)', $student->full_name, $student->roll_no));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->writeHeader($sheet, $headers, 3);

        $rowNumber = 4;
        foreach (array_values($rows) as $index => $row) {
            $posting = $row['posting'];
            $values = [
                $index + 1,
                $posting->placementCycle?->name,
                $posting->title(),
                $posting->company()?->name,
                ucfirst(str_replace('_', ' ', (string) $posting->status)),
                $posting->application_deadline?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
                $row['eligible'] ? 'Yes' : 'No',
                implode('; ', $row['reasons']),
            ];
            foreach ($values as $col => $value) {
                $this->put($sheet, $col + 1, $rowNumber, $value);
            }
            $rowNumber++;
        }

        $lastData = max(3, $rowNumber - 1);
        $this->put($sheet, 1, $rowNumber + 1, 'Downloaded on '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST');
        $sheet->getStyle('A'.($rowNumber + 1))->getFont()->setItalic(true);
        $this->finish($sheet, count($headers), 'A3:'.Coordinate::stringFromColumnIndex(count($headers)).$lastData);

        return $this->stream($spreadsheet, Str::slug($student->roll_no.' eligibility report').'.xlsx');
    }

    /**
     * Survey report (Superset parity S7.3, admin only): one row per response, the student, the submission time (IST)
     * and one `Q{n}:` column per answerable question. Answers are student-written, so every cell is an explicit string.
     *
     * A second "Non-responders" sheet lists the audience members without a response (Roll Number, Name, Branch; L22).
     *
     * @param  list<array{question: \App\Models\SurveyQuestion, number: int}>  $questions
     * @param  iterable<\App\Models\SurveyResponse>  $responses
     * @param  callable(\App\Models\SurveyQuestion, mixed): string  $display
     * @param  iterable<\App\Models\StudentProfile>  $nonResponders
     */
    public function surveyWorkbook(\App\Models\Survey $survey, array $questions, iterable $responses, callable $display, iterable $nonResponders = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Responses');

        $headers = ['S.No.', 'Roll No', 'Name', 'Programme', 'Branch', 'Passout Batch', 'Institute Email', 'Submitted At (IST)'];
        foreach ($questions as $q) {
            $headers[] = \App\Support\ExportFieldCatalogue::questionHeader($q['number'], $q['question']->question);
        }
        $this->writeHeader($sheet, $headers);

        $rowNumber = 2;
        foreach ($responses as $index => $response) {
            $s = $response->studentProfile;
            $values = [
                $rowNumber - 1, $s?->roll_no, $s?->full_name, $s?->programme, $s?->branch, $s?->graduating_batch, $s?->institute_email,
                $response->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            ];
            foreach ($questions as $q) {
                $values[] = $display($q['question'], $response->answers[(string) $q['question']->id] ?? null);
            }
            foreach ($values as $col => $value) {
                $this->put($sheet, $col + 1, $rowNumber, $value === '' ? null : $value);
            }
            $rowNumber++;
        }

        $this->finish($sheet, count($headers));

        $others = $spreadsheet->createSheet();
        $others->setTitle('Non-responders');
        $this->writeHeader($others, ['Roll Number', 'Name', 'Branch']);
        $rowNumber = 2;
        foreach ($nonResponders as $student) {
            foreach ([$student->roll_no, $student->full_name, $student->branch] as $col => $value) {
                $this->put($others, $col + 1, $rowNumber, $value === '' ? null : $value);
            }
            $rowNumber++;
        }
        $this->finish($others, 3);
        $spreadsheet->setActiveSheetIndex(0);

        return $this->stream($spreadsheet, (Str::slug($survey->title) ?: 'survey').'-responses.xlsx');
    }

    /**
     * A plain table with a title row (Superset parity S8.5 reports and other admin lists).
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public function tableWorkbook(string $title, array $headers, iterable $rows, string $fileName, string $sheetTitle = 'Report'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetTitle), 28, ''));

        $this->put($sheet, 1, 1, $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->writeHeader($sheet, $headers, 3);

        $rowNumber = 4;
        foreach ($rows as $row) {
            foreach (array_values($row) as $col => $value) {
                $this->put($sheet, $col + 1, $rowNumber, $value);
            }
            $rowNumber++;
        }
        $this->put($sheet, 1, $rowNumber + 1, 'Downloaded on '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST');
        $sheet->getStyle('A'.($rowNumber + 1))->getFont()->setItalic(true);

        $this->finish($sheet, count($headers), 'A3:'.Coordinate::stringFromColumnIndex(max(1, count($headers))).max(3, $rowNumber - 1));

        return $this->stream($spreadsheet, $fileName);
    }

    /**
     * Text is always written as a literal string so user-controlled values (names, answers, links) such as
     * "=HYPERLINK(...)" can never become live formulas in Excel (D87). Numbers stay numeric.
     */
    private function put(Worksheet $sheet, int $column, int $row, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue([$column, $row], $value);

            return;
        }

        $sheet->setCellValueExplicit([$column, $row], (string) $value, DataType::TYPE_STRING);
    }

    private function num(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function writeHeader(Worksheet $sheet, array $headers, int $row = 1): void
    {
        foreach (array_values($headers) as $index => $header) {
            $this->put($sheet, $index + 1, $row, $header); // explicit strings: company-controlled round names cannot become formulas
        }
        $range = 'A'.$row.':'.Coordinate::stringFromColumnIndex(count($headers)).$row;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MAROON);
        $sheet->freezePane('C'.($row + 1));
    }

    private function finish(Worksheet $sheet, int $columnCount, ?string $filterRange = null): void
    {
        for ($col = 1; $col <= $columnCount; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $sheet->setAutoFilter($filterRange ?? $sheet->calculateWorksheetDimension());
    }

    private function stream(Spreadsheet $spreadsheet, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}
