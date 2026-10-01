<?php
/**
 * Security recon (Part 1): inventory of every api/* route with its middleware chain, allowed roles, object
 * parameters and two static heuristics read from the controller method body (file upload, email).
 * Run from CDC/backend:  MAIL_MAILER=log php ../security/route_inventory.php > ../security/evidence/routes.md
 * Read-only: boots the app, never dispatches a request.
 */

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
    ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
    ->sortBy(fn ($r) => $r->uri().' '.implode('|', $r->methods()));

/** Body of the controller method, plus one level of private helpers it calls on $this. */
function methodSource(string $class, string $method, int $depth = 1): string
{
    if (! method_exists($class, $method)) {
        return '';
    }
    $ref = new ReflectionMethod($class, $method);
    $lines = file($ref->getFileName());
    $src = implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    if ($depth > 0 && preg_match_all('/\$this->(\w+)\(/', $src, $m)) {
        foreach (array_unique($m[1]) as $helper) {
            if ($helper !== $method) {
                $src .= methodSource($class, $helper, $depth - 1);
            }
        }
    }

    return $src;
}

$uploadRe = '/hasFile\(|->file\(|\bfile\b\'|\'file\'|UploadedFile|mimes:|UPLOAD_RULES|uploadResume|uploadPhoto|uploadLogo|uploadPolicyFile|companyLogo|company_logo/';
$mailRe = '/Mail::|->send\(|->queue\(|sendBulk|sendLoggedEmail|notifyAdmins|notifyCompany|Password::sendResetLink|sendResetLink|->mail->|Send\w+Mails?::dispatch|SendStudentInvitation|dispatchResultMails|notifyResults|->publish\(|stakeholders|deliverInvitation|sendInvitation|E\d\b/';

$rows = [];
$counts = ['public' => 0, 'auth' => 0, 'admin' => 0, 'company' => 0, 'student' => 0, 'any' => 0];
foreach ($routes as $r) {
    $mw = $r->gatherMiddleware();
    $auth = (bool) preg_grep('/^auth(:|$)/', $mw);
    $roles = [];
    foreach ($mw as $m) {
        if (preg_match('/^role:(.+)$/', $m, $mm)) {
            $roles = array_merge($roles, explode(',', $mm[1]));
        }
    }
    $who = ! $auth ? 'PUBLIC' : ($roles ? implode(',', $roles) : 'any authenticated');
    $counts[! $auth ? 'public' : 'auth']++;
    if ($auth) {
        foreach ($roles ?: ['any'] as $role) {
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }
    }
    $action = $r->getActionName();
    [$class, $method] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];
    $src = ($class !== "Closure" && class_exists($class)) ? methodSource($class, $method) : "";
    $params = $r->parameterNames();
    $rows[] = sprintf(
        '| %s | `/%s` | %s | %s | `%s` | %s | %s | %s |',
        implode('|', array_diff($r->methods(), ['HEAD'])),
        $r->uri(),
        implode(', ', array_map(fn ($m) => str_replace('Illuminate\\Routing\\Middleware\\', '', $m), $mw)),
        $who,
        str_replace('App\\Http\\Controllers\\', '', $action),
        $params ? implode(', ', $params) : '—',
        $src !== '' && preg_match($uploadRe, $src) ? 'yes' : '—',
        $src !== '' && preg_match($mailRe, $src) ? 'yes' : '—',
    );
}

echo "| Method | Path | Middleware | Allowed | Controller@method | Object params | File upload | Sends email/notification |\n";
echo "|---|---|---|---|---|---|---|---|\n";
echo implode("\n", $rows)."\n\n";
printf("Totals: %d api routes · public %d · authenticated %d (admin %d, company %d, student %d, any-role %d)\n",
    count($rows), $counts['public'], $counts['auth'], $counts['admin'] ?? 0, $counts['company'] ?? 0, $counts['student'] ?? 0, $counts['any'] ?? 0);
