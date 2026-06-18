<?php

namespace App\Http\Controllers;

use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyFileUploadController extends Controller
{
    public function __construct(private readonly FileUploadService $fileUploadService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $uploaded = $this->fileUploadService->uploadFormFile($validated['file'], $company->id);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'file' => $uploaded,
        ], 201);
    }
}
