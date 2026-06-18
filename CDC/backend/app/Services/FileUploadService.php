<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
}
