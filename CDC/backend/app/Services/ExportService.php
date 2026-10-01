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
     */
    public function studentsWorkbook(PlacementCycle $cycle): StreamedResponse
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

    private function writeHeader(Worksheet $sheet, array $headers): void
    {
        foreach (array_values($headers) as $index => $header) {
            $this->put($sheet, $index + 1, 1, $header); // explicit strings: company-controlled round names cannot become formulas
        }
        $range = 'A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1';
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::MAROON);
        $sheet->freezePane('C2');
    }

    private function finish(Worksheet $sheet, int $columnCount): void
    {
        for ($col = 1; $col <= $columnCount; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
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
