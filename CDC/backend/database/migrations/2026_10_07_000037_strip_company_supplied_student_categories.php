<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix M2: `allowedStudentCategories` must come only from the CDC. Before the fix, opening a job profile copied the key
 * from the company-written form_data into the eligibility snapshot, and the company's value won over the admin's
 * choice. Rule (D127): when the posting's JNF/INF form_data carries the key and the snapshot holds the same list
 * (normalised ids), the restriction came from the company: drop it (the CDC can set categories again with Edit
 * eligibility). Any other snapshot value can only have come from the CDC: keep it. Audit rows are not used, because
 * before the fix they echoed the snapshot (i.e. the company's value) and prove nothing. Afterwards the key is removed
 * from every JNF/INF form_data (it is never read from there any more). Idempotent; no down migration.
 *
 * Edited after it first ran (exception to "never edit a run migration", D127): data-only, ran only on the local dev
 * database (0 affected postings, 0 forms with the key, verified 2026-10-07), not yet run in production. It has to be
 * this migration because it is the only step that still sees the form keys before stripping them.
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
        $tables = ['App\\Models\\Jnf' => 'jnfs', 'App\\Models\\Inf' => 'infs'];

        DB::table('job_postings')->orderBy('id')->chunkById(200, function ($postings) use ($normalise, $tables): void {
            foreach ($postings as $posting) {
                $snapshot = json_decode((string) $posting->eligibility_snapshot, true);
                if (! is_array($snapshot) || ! array_key_exists('allowedStudentCategories', $snapshot)) {
                    continue;
                }

                $table = $tables[$posting->postable_type] ?? null;
                $formData = $table ? json_decode((string) DB::table($table)->where('id', $posting->postable_id)->value('form_data'), true) : null;
                $current = $normalise($snapshot['allowedStudentCategories']);

                $companySupplied = is_array($formData)
                    && array_key_exists('allowedStudentCategories', $formData)
                    && $normalise($formData['allowedStudentCategories']) === $current;

                if (! $companySupplied && $current !== []) {
                    continue; // set by the CDC: keep
                }

                unset($snapshot['allowedStudentCategories']);
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
