<?php

namespace App\Support;

/**
 * The fixed staff permission catalogue and the rule that maps every admin API route to one permission + level.
 *
 * Super admins combine these permissions into roles (staff_roles) and per-person overrides; they cannot invent new
 * ones, because each permission is enforced by the rules below. Levels are cumulative: read < verify < write.
 * Managing users and roles is never a permission: those routes are `super` (super admins only).
 *
 * Adding an admin route? Add a rule here. StaffPermissionsTest fails while any admin route has none, and the
 * middleware refuses an unmatched route for everyone except super admins.
 */
final class StaffPermissions
{
    public const LEVELS = ['none' => 0, 'read' => 1, 'verify' => 2, 'write' => 3];

    /** Pseudo-permissions used only in RULES. */
    public const SUPER = 'super';

    public const ANY = 'any';

    /**
     * group => [key => [label, allowed levels (highest last), description]]
     */
    public const CATALOGUE = [
        'Overview' => [
            'dashboard' => ['Dashboard & Analytics', ['read'], 'Dashboard and Analytics pages.'],
            'calendar' => ['Calendar', ['read'], 'The admin calendar.'],
            'reports' => ['Reports', ['read'], 'Reports page and report downloads.'],
        ],
        'Placement' => [
            'placement_cycles' => ['Placements (cycles)', ['read', 'write'], 'Write: create, edit, close and publish placements; enrol students.'],
            'job_profiles' => ['Job Profiles', ['read', 'write'], 'Write: create, edit, open, close and cancel job profiles; rounds; documents; eligibility.'],
            'progress_grid' => ['Progress grid', ['read', 'write'], 'Stage shortlists and results. Write: record results and attendance, publish stages, waitlists.'],
            'results_offers' => ['Results & offers', ['read', 'write'], 'Write: publish final results, edit or revoke offers, upload CTCs.'],
            'job_profile_exports' => ['Job profile Excel downloads', ['read'], 'Download applicant, eligible and shortlist lists.'],
            'job_profile_communications' => ['Job profile communications', ['read', 'write'], 'Activity and mail log. Write: email a stage, send the applicant list to the company.'],
            'proposals' => ['Proposals', ['read', 'verify'], 'Company shortlist proposals. Verify: approve or reject.'],
            'events' => ['Events', ['read', 'write'], 'Write: create, edit, publish and delete events.'],
        ],
        'Companies & Forms' => [
            'form_reviews' => ['JNF & INF reviews', ['read', 'verify', 'write'], 'Verify: accept or reject, remarks and notes. Write: edit form data, Add New Job.'],
            'companies' => ['Companies', ['read', 'write'], 'Write: edit company details.'],
            'policy_documents' => ['Policy Documents', ['read', 'write'], 'Write: add, edit and delete policy documents.'],
            'alumni_outreach' => ['Alumni Outreach', ['read'], 'Alumni outreach submissions.'],
        ],
        'Students' => [
            'students' => ['Students', ['read', 'write'], 'Directory, student page, notes, reports, export. Write: add, import, edit, suspend.'],
            'student_invitations' => ['Student invitations', ['read', 'write'], 'Write: send, resend and revoke invitations.'],
            'resumes' => ['Resumes', ['read', 'verify'], 'Verify: approve or reject resumes.'],
            'profile_verification' => ['Profile verification', ['verify'], 'Accept or reject student-changed profile fields.'],
            'branch_changes' => ['Branch changes', ['read', 'verify'], 'Verify: approve or reject branch change requests.'],
            'student_categories' => ['Student categories', ['read', 'write'], 'Write: create categories and assign students.'],
            'scpt' => ['SCPT & SPOCs', ['read', 'write'], 'SCPT members per placement and job profile SPOCs. Write: add or remove members, assign or remove SPOCs (mails the SPOC).'],
            'blocks' => ['Placement blocks', ['read', 'write'], 'Write: add or lift blocks.'],
        ],
        'Engagement' => [
            'notices' => ['Notices', ['read', 'write'], 'Write: create, edit, publish and delete notices.'],
            'surveys' => ['Surveys', ['read', 'write'], 'Write: create, edit, publish and delete surveys.'],
        ],
        'Settings' => [
            'settings' => ['Settings & Branch Manager', ['read', 'write'], 'Write: change portal settings, logo, programme branches.'],
            'excel_templates' => ['Excel Templates', ['read', 'write'], 'Write: create, edit and delete Excel templates.'],
            'audit_log' => ['Audit Log', ['read'], 'The audit log.'],
            'login_log' => ['Login Log', ['read'], 'Sign-in records (non-super admins see their own and students\' rows).'],
        ],
    ];

    /**
     * First match wins. [methods ('*' = any, else '|'-separated), URI pattern after "api/admin/" (fnmatch, `*` crosses
     * slashes), permission (or SUPER / ANY / a list meaning "any one of these"), level].
     *
     * @var list<array{0: string, 1: string, 2: string|list<string>, 3: string}>
     */
    public const RULES = [
        // Users and roles: super admins only.
        ['*', 'manage-admins*', self::SUPER, 'write'],
        ['*', 'roles*', self::SUPER, 'write'],

        // Anyone with an admin account: their own access, the catalogue, and lookup lists other pages need.
        ['GET', 'me/access', self::ANY, 'read'],
        ['GET|PUT', 'me/profile', self::ANY, 'read'],
        ['GET', 'ping', self::ANY, 'read'],
        ['GET', 'placement-cycles', self::ANY, 'read'],
        ['GET', 'programme-branches', self::ANY, 'read'],
        ['GET', 'student-categories', self::ANY, 'read'],
        ['GET', 'export-templates', self::ANY, 'read'],
        ['GET', 'export-templates/fields', self::ANY, 'read'],
        ['GET', 'policy-documents', self::ANY, 'read'],

        // Overview
        ['GET', 'dashboard*', 'dashboard', 'read'],
        ['GET', 'calendar', 'calendar', 'read'],
        ['GET', 'reports', 'reports', 'read'],
        ['GET', 'placement-cycles/*/reports/*', 'reports', 'read'],

        // Placement cycles
        ['GET', 'placement-cycles/*/students/export', 'placement_cycles', 'read'],
        ['GET', 'placement-cycles*', 'placement_cycles', 'read'],
        ['*', 'placement-cycles*', 'placement_cycles', 'write'],

        // Job profiles: exports, communications, progress grid, results, then the rest.
        ['GET', 'postings/*/export', 'job_profile_exports', 'read'],
        ['GET', 'postings/*/eligible/export', 'job_profile_exports', 'read'],
        ['GET', 'postings/*/rounds/*/shortlist/export', 'job_profile_exports', 'read'],
        ['GET', 'postings/*/rounds/*/reconcile/export', 'job_profile_exports', 'read'],
        ['GET', 'postings/*/activity', 'job_profile_communications', 'read'],
        ['GET', 'postings/*/communications', 'job_profile_communications', 'read'],
        ['GET', 'postings/*/rounds/*/message-audience', 'job_profile_communications', 'write'],
        ['POST', 'postings/*/rounds/*/email', 'job_profile_communications', 'write'],
        ['POST', 'postings/*/send-applicant-list', 'job_profile_communications', 'write'],
        ['GET', 'postings/*/pipeline', 'progress_grid', 'read'],
        ['GET', 'postings/*/rounds/*/shortlist', 'progress_grid', 'read'],
        ['GET', 'postings/*/rounds/*/reconcile', 'progress_grid', 'read'],
        ['POST', 'postings/*/rounds/*/reconcile', 'progress_grid', 'write'],
        ['*', 'postings/*/rounds/*/results*', 'progress_grid', 'write'],
        ['POST', 'postings/*/rounds/*/attendance', 'progress_grid', 'write'],
        ['POST', 'postings/*/rounds/*/publish', 'progress_grid', 'write'],
        ['POST', 'postings/*/rounds/*/addendum', 'progress_grid', 'write'],
        ['POST', 'postings/*/applications/*/remove-from-process', 'progress_grid', 'write'],
        ['GET', 'postings/*/spocs', 'scpt', 'read'],
        ['*', 'postings/*/spocs*', 'scpt', 'write'],
        ['GET', 'postings/*/results/prepare', 'results_offers', 'read'],
        ['POST', 'postings/*/results/publish', 'results_offers', 'write'],
        ['POST', 'postings/*/offers/ctc-upload', 'results_offers', 'write'],
        ['GET', 'offers/*', 'results_offers', 'read'],
        ['*', 'offers/*', 'results_offers', 'write'],
        ['GET', 'postings*', 'job_profiles', 'read'],
        ['*', 'postings*', 'job_profiles', 'write'],

        // Students
        ['GET', 'students/invitations', 'student_invitations', 'read'],
        ['POST', 'students/invitations/*', 'student_invitations', 'write'],
        ['POST', 'students/*/resend-invitation', 'student_invitations', 'write'],
        ['POST', 'students/*/revoke-invitation', 'student_invitations', 'write'],
        ['POST', 'students/*/resumes/verify-all', 'resumes', 'verify'],
        ['*', 'students/*/verification/*', 'profile_verification', 'verify'],
        ['GET', 'students/*/categories', 'students', 'read'],
        ['GET', 'students*', 'students', 'read'],
        ['*', 'students*', 'students', 'write'],
        ['GET', 'resumes*', 'resumes', 'read'],
        ['*', 'resumes*', 'resumes', 'verify'],
        ['GET', 'branch-changes', 'branch_changes', 'read'],
        ['*', 'branch-changes/*', 'branch_changes', 'verify'],
        ['GET', 'scpt', 'scpt', 'read'],
        ['*', 'scpt*', 'scpt', 'write'],
        ['GET', 'student-categories*', 'student_categories', 'read'],
        ['*', 'student-categories*', 'student_categories', 'write'],
        ['GET', 'blocks', 'blocks', 'read'],
        ['*', 'blocks*', 'blocks', 'write'],

        // Companies & forms
        ['GET', 'jnfs*', 'form_reviews', 'read'],
        ['GET', 'infs*', 'form_reviews', 'read'],
        ['PATCH', 'jnfs/*/form-data', 'form_reviews', 'write'],
        ['PATCH', 'infs/*/form-data', 'form_reviews', 'write'],
        ['*', 'jnfs/*', 'form_reviews', 'verify'],
        ['*', 'infs/*', 'form_reviews', 'verify'],
        ['GET', 'form-builder/*', 'form_reviews', 'write'],
        ['*', 'form-builder*', 'form_reviews', 'write'],
        ['GET', 'companies*', 'companies', 'read'],
        ['*', 'companies*', 'companies', 'write'],
        ['*', 'policy-documents*', 'policy_documents', 'write'],
        ['GET', 'alumni-outreach', 'alumni_outreach', 'read'],

        // Events and engagement
        ['GET', 'events', 'events', 'read'],
        ['*', 'events*', 'events', 'write'],
        ['POST', 'audiences/preview', ['notices', 'surveys'], 'write'],
        ['GET', 'notices*', 'notices', 'read'],
        ['*', 'notices*', 'notices', 'write'],
        ['GET', 'surveys*', 'surveys', 'read'],
        ['*', 'surveys*', 'surveys', 'write'],
        ['GET', 'proposals', 'proposals', 'read'],
        ['*', 'proposals/*', 'proposals', 'verify'],

        // Settings
        ['GET', 'settings', 'settings', 'read'],
        ['*', 'settings*', 'settings', 'write'],
        ['*', 'programme-branches*', 'settings', 'write'],
        ['GET', 'export-templates*', 'excel_templates', 'read'],
        ['*', 'export-templates*', 'excel_templates', 'write'],
        ['GET', 'audit-logs', 'audit_log', 'read'],
        ['GET', 'login-logs*', 'login_log', 'read'],
    ];

    /** @return array<string, array{label: string, group: string, levels: list<string>, description: string}> */
    public static function all(): array
    {
        $flat = [];
        foreach (self::CATALOGUE as $group => $permissions) {
            foreach ($permissions as $key => [$label, $levels, $description]) {
                $flat[$key] = ['label' => $label, 'group' => $group, 'levels' => $levels, 'description' => $description];
            }
        }

        return $flat;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** The highest level a permission allows (what "Full Access" grants). */
    public static function maxLevel(string $permission): string
    {
        $levels = self::all()[$permission]['levels'] ?? ['none'];

        return end($levels);
    }

    public static function rank(?string $level): int
    {
        return self::LEVELS[$level ?? 'none'] ?? 0;
    }

    /** Whether `$level` is allowed for `$permission` ("none" always is). */
    public static function allows(string $permission, string $level): bool
    {
        return $level === 'none' || in_array($level, self::all()[$permission]['levels'] ?? [], true);
    }

    /**
     * The rule for an admin route, or null when none matches (= super admins only).
     *
     * @return array{permission: string|list<string>, level: string}|null
     */
    public static function ruleFor(string $method, string $uri): ?array
    {
        $path = preg_replace('#^api/admin/?#', '', $uri);

        foreach (self::RULES as [$methods, $pattern, $permission, $level]) {
            if ($methods !== '*' && ! in_array(strtoupper($method), explode('|', $methods), true)) {
                continue;
            }
            if (fnmatch($pattern, $path)) {
                return ['permission' => $permission, 'level' => $level];
            }
        }

        return null;
    }
}
