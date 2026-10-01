<?php

namespace App\Services;

use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Store uploaded file under public disk and return metadata.
     *
     * @return array{path:string,url:string,name:string,size:int,mime:string}
     */
    public function uploadFormFile(UploadedFile $file, int $companyId): array
    {
        $path = $file->store("company-forms/{$companyId}", 'public');

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize() ?: 0,
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
        ];
    }

    /**
     * Store uploaded policy file under public disk and return metadata.
     *
     * @return array{path:string,url:string,name:string,size:int,mime:string}
     */
    public function uploadPolicyFile(UploadedFile $file): array
    {
        $path = $file->store("policy-documents", 'public');

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize() ?: 0,
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
        ];
    }

    /**
     * Store a resume PDF on the PRIVATE disk (never public): resumes/{roll_no}/{slot}_{uuid}.pdf
     *
     * @return array{path:string,size:int}
     */
    public function uploadResume(UploadedFile $file, StudentProfile $student, int $slot): array
    {
        $path = $file->storeAs(
            "resumes/{$student->roll_no}",
            sprintf('%d_%s.pdf', $slot, Str::uuid()),
            'local'
        );

        return [
            'path' => $path,
            'size' => $file->getSize() ?: 0,
        ];
    }
}
