<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix M2: `allowedStudentCategories` must come only from the CDC. Before the fix, opening a job profile copied the key
 * from the company-written form_data into the eligibility snapshot. Rule: keep a posting's snapshot value only when the
 * audit log shows an admin set exactly that list — the `posting.float` row's `after.allowed_student_categories`, or the
 * latest `posting.eligibility_update` row's `after.criteria.allowedStudentCategories`; otherwise drop the key. The key
 * is also removed from every JNF/INF form_data (it is never read from there any more). Idempotent; no down migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $normalise = function ($ids): array {
            $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0))) : [];
            sort($ids);

            return $ids;
        };

        DB::table('job_postings')->orderBy('id')->chunkById(200, function ($postings) use ($normalise): void {
            foreach ($postings as $posting) {
                $snapshot = json_decode((string) $posting->eligibility_snapshot, true);
                if (! is_array($snapshot) || ! array_key_exists('allowedStudentCategories', $snapshot)) {
                    continue;
                }

                $current = $normalise($snapshot['allowedStudentCategories']);
                $logs = DB::table('audit_logs')
                    ->where('subject_type', 'App\\Models\\JobPosting')
                    ->where('subject_id', $posting->id)
                    ->whereIn('action', ['posting.float', 'posting.eligibility_update'])
                    ->orderByDesc('id')
                    ->get(['action', 'after']);

                $adminValue = null;
                foreach ($logs as $log) {
                    $after = json_decode((string) $log->after, true) ?: [];
                    if ($log->action === 'posting.eligibility_update' && isset($after['criteria']) && array_key_exists('allowedStudentCategories', $after['criteria'])) {
                        $adminValue = $normalise($after['criteria']['allowedStudentCategories']);
                        break;
                    }
                    if ($log->action === 'posting.float' && array_key_exists('allowed_student_categories', $after)) {
                        $adminValue = $normalise($after['allowed_student_categories']);
                        break;
                    }
                }

                if ($adminValue !== null && $adminValue === $current && $current !== []) {
                    continue; // set by the CDC: keep
                }

                unset($snapshot['allowedStudentCategories']);
                if ($adminValue) {
                    $snapshot['allowedStudentCategories'] = $adminValue;
                }
                DB::table('job_postings')->where('id', $posting->id)->update(['eligibility_snapshot' => json_encode($snapshot)]);
            }
        });

        foreach (['jnfs', 'infs'] as $table) {
            DB::table($table)->whereNotNull('form_data')->where('form_data', 'like', '%allowedStudentCategories%')->orderBy('id')
                ->chunkById(200, function ($forms) use ($table): void {
                    foreach ($forms as $form) {
                        $data = json_decode((string) $form->form_data, true);
                        if (is_array($data) && array_key_exists('allowedStudentCategories', $data)) {
                            unset($data['allowedStudentCategories']);
                            DB::table($table)->where('id', $form->id)->update(['form_data' => json_encode($data)]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Data clean-up only; nothing to restore.
    }
};
