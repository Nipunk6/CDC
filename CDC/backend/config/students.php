<?php

/*
|--------------------------------------------------------------------------
| Student accounts
|--------------------------------------------------------------------------
|
| institute_email_domains: the only domains accepted for a student's institute email on create, import and edit
| (owner decision B2-10). Comma-separated in STUDENT_EMAIL_DOMAINS to add another, e.g. "iitism.ac.in,ism.ac.in".
|
| demo_email_domains: the demo seeder's reserved .test addresses (D90). Accepted only when the app runs in the
| `local` or `testing` environment, so a local tester never has to type a real student's institute address.
|
| import_max_rows: rows read from one bulk-import file (S5.7). Rows beyond it are reported as errors, never dropped.
|
*/

return [
    'institute_email_domains' => array_values(array_filter(array_map(
        fn (string $domain) => strtolower(trim($domain)),
        explode(',', (string) env('STUDENT_EMAIL_DOMAINS', 'iitism.ac.in'))
    ))),

    'demo_email_domains' => ['students.cdc-demo.test'],

    'import_max_rows' => (int) env('STUDENT_IMPORT_MAX_ROWS', 10000),
];
