<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Tata Steel',
                'industry' => 'Manufacturing',
                'website' => 'https://www.tatasteel.com',
                'hr_name' => 'Rohit Singh',
                'hr_email' => 'hr@tatasteel.com',
                'hr_phone' => '+91-9999991111',
            ],
            [
                'name' => 'Infosys',
                'industry' => 'IT Services',
                'website' => 'https://www.infosys.com',
                'hr_name' => 'Anita Sharma',
                'hr_email' => 'hr@infosys.com',
                'hr_phone' => '+91-9999992222',
            ],
        ];

        foreach ($companies as $companyData) {
            $company = Company::updateOrCreate(
                ['hr_email' => $companyData['hr_email']],
                $companyData
            );

            User::updateOrCreate(
                ['email' => $companyData['hr_email']],
                [
                    'name' => $companyData['hr_name'],
                    'password' => 'password123',
                    'role' => 'company',
                    'company_id' => $company->id,
                ]
            );
        }
    }
}
