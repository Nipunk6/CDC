<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * An upload's real type must match its extension (L24): the extension must be allowed AND the MIME type read from the
 * file's content (finfo, via UploadedFile::getMimeType) must be one that extension stands for. A PDF renamed x.png,
 * or an HTML page renamed x.pdf, is refused. Used for notice attachments, stage-email attachments and survey files.
 */
final class UploadType
{
    /** Extension => MIME types the content may have. */
    public const MIMES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    /**
     * @param  list<string>  $extensions  allowed extensions (keys of MIMES)
     */
    public static function matches(UploadedFile $file, array $extensions): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, $extensions, true)
            && in_array((string) $file->getMimeType(), self::MIMES[$extension] ?? [], true);
    }

    /**
     * The same check as a validation rule.
     *
     * @param  list<string>  $extensions
     */
    public static function rule(array $extensions, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($extensions, $message): void {
            if ($value instanceof UploadedFile && ! self::matches($value, $extensions)) {
                $fail($message);
            }
        };
    }
}
