<?php

namespace Database\Seeders;

use App\Models\PolicyDocument;
use Illuminate\Database\Seeder;

class PolicyDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PolicyDocument::updateOrCreate(
            ['title' => 'IIT (ISM) CDC Policy'],
            [
                'type' => 'pdf',
                'url' => '/IIT_ISM_CDC_Policy.pdf',
                'is_visible_jnf' => true,
                'is_visible_inf' => true,
            ]
        );

        PolicyDocument::updateOrCreate(
            ['title' => 'AIPC Guidelines'],
            [
                'type' => 'pdf',
                'url' => '/AIPC_Guidelines_2023.pdf',
                'is_visible_jnf' => true,
                'is_visible_inf' => true,
            ]
        );
    }
}
