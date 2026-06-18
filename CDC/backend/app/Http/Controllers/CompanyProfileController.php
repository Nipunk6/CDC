<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        return response()->json(['company' => $company]);
    }

    public function update(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[^\s]*$/i'],
            'postal_address' => ['nullable', 'string', 'max:1000'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'sector' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],

            'hr_name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\s'.-]+$/u"],
            'hr_designation' => ['required', 'string', 'max:255'],
            'hr_email' => ['required', 'email:rfc,dns', 'max:255', 'unique:companies,hr_email,'.$company->id],
            'hr_phone' => ['required', 'string', 'max:30'],
            'hr_alt_phone' => ['nullable', 'string', 'max:30'],

            'head_name' => ['required', 'string', 'max:255'],
            'head_designation' => ['required', 'string', 'max:255'],
            'head_email' => ['required', 'string', 'email', 'max:255'],
            'head_mobile' => ['required', 'string', 'max:30'],
            'head_landline' => ['nullable', 'string', 'max:30'],

            'poc1_name' => ['required', 'string', 'max:255'],
            'poc1_designation' => ['required', 'string', 'max:255'],
            'poc1_email' => ['required', 'string', 'email', 'max:255'],
            'poc1_mobile' => ['required', 'string', 'max:30'],
            'poc1_landline' => ['nullable', 'string', 'max:30'],

            'poc2_name' => ['nullable', 'string', 'max:255'],
            'poc2_designation' => ['nullable', 'string', 'max:255'],
            'poc2_email' => ['nullable', 'string', 'email', 'max:255'],
            'poc2_mobile' => ['nullable', 'string', 'max:30'],
            'poc2_landline' => ['nullable', 'string', 'max:30'],

            'category_org_type' => ['nullable', 'string', 'max:255'],
            'date_of_establishment' => ['nullable', 'date', 'before_or_equal:today'],
            'annual_turnover' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:1000'],
            'industry_sector_tags' => ['nullable', 'array'],
            'industry_sector_tags.*' => ['string', 'max:100'],
            'mnc_hq_country_city' => ['nullable', 'string', 'max:255'],
            'nature_of_business' => ['nullable', 'string', 'max:1000'],
            'company_description' => ['nullable', 'string', 'max:5000'],
        ]);

        $company->update([
            'name' => $validated['name'],
            'website' => $validated['website'],
            'postal_address' => $validated['postal_address'] ?? null,
            'employee_count' => $validated['employee_count'] ?? null,
            'sector' => $validated['sector'],
            'industry' => $validated['industry'] ?? $validated['sector'],

            'hr_name' => $validated['hr_name'],
            'hr_designation' => $validated['hr_designation'],
            'hr_email' => $validated['hr_email'],
            'hr_phone' => $validated['hr_phone'],
            'hr_alt_phone' => $validated['hr_alt_phone'] ?? null,

            'head_talent_contact' => [
                'name' => $validated['head_name'],
                'designation' => $validated['head_designation'],
                'email' => $validated['head_email'],
                'mobile' => $validated['head_mobile'],
                'landline' => $validated['head_landline'] ?? null,
            ],
            'primary_contact' => [
                'name' => $validated['poc1_name'],
                'designation' => $validated['poc1_designation'],
                'email' => $validated['poc1_email'],
                'mobile' => $validated['poc1_mobile'],
                'landline' => $validated['poc1_landline'] ?? null,
            ],
            'secondary_contact' => [
                'name' => $validated['poc2_name'] ?? null,
                'designation' => $validated['poc2_designation'] ?? null,
                'email' => $validated['poc2_email'] ?? null,
                'mobile' => $validated['poc2_mobile'] ?? null,
                'landline' => $validated['poc2_landline'] ?? null,
            ],

            'category_org_type' => $validated['category_org_type'] ?? null,
            'date_of_establishment' => $validated['date_of_establishment'] ?? null,
            'annual_turnover' => $validated['annual_turnover'] ?? null,
            'linkedin_url' => $validated['linkedin_url'] ?? null,
            'industry_sector_tags' => $validated['industry_sector_tags'] ?? null,
            'mnc_hq_country_city' => $validated['mnc_hq_country_city'] ?? null,
            'nature_of_business' => $validated['nature_of_business'] ?? null,
            'company_description' => $validated['company_description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'company' => $company->fresh(),
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $request->validate([
            'company_logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $logoPath = $request->file('company_logo')?->store('company-logos', 'public');

        if ($logoPath === null) {
            return response()->json([
                'message' => 'Unable to upload company logo.',
            ], 422);
        }

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->update(['logo_path' => $logoPath]);

        return response()->json([
            'message' => 'Company logo updated successfully.',
            'company' => $company->fresh(),
        ]);
    }
}
