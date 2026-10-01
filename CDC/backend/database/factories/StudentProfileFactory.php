<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    public const BTECH = 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roll = sprintf('22JE%04d', fake()->unique()->numberBetween(1, 9999));
        $email = strtolower($roll).'@iitism.ac.in';

        return [
            'user_id' => User::factory()->state([
                'role' => 'student',
                'email' => $email,
            ]),
            'roll_no' => $roll,
            'full_name' => fake()->name(),
            'institute_email' => $email,
            'programme' => self::BTECH,
            'branch' => 'Computer Science & Engineering',
            'graduating_batch' => 2027,
            'current_cgpa' => 8.00,
            'ongoing_backlogs' => 0,
            'total_backlogs' => 0,
            'gender' => 'male',
            'tenth_percent' => 90.00,
            'twelfth_percent' => 90.00,
            'pwd' => false,
        ];
    }
}
