<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A PDF attached to a job profile (Superset parity S6.4 "Attached Documents"). Private disk; served only through
 * authenticated routes (admin, and students who can see the job profile).
 */
class PostingDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['job_posting_id', 'title', 'file_path', 'file_size', 'uploaded_by'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stream(): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($this->file_path), 404, 'Document not found.');

        return Storage::disk('local')->response($this->file_path, Str::slug($this->title).'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
