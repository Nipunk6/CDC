<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, read-only branding (S8.1): the login pages and every shell show the account logo and institute
 * display name before anyone signs in. Only these two values and the logo bytes are exposed.
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Branding::payload());
    }

    public function logo(Request $request): Response
    {
        $logo = Branding::logo();
        abort_unless($logo !== null, 404, 'No account logo has been uploaded.');

        // Cached for five minutes, then revalidated (cheap 304s), so a new logo reaches everyone within minutes.
        $etag = '"'.sha1($logo['path']).'"';
        $headers = [
            'Content-Type' => $logo['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'ETag' => $etag,
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', 304, $headers);
        }

        return response(Storage::disk('local')->get($logo['path']), 200, $headers + [
            'Content-Disposition' => 'inline; filename="account-logo.'.Branding::LOGO_MIMES[$logo['mime']].'"',
        ]);
    }
}
