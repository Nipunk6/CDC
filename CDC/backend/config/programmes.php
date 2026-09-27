<?php

/*
|--------------------------------------------------------------------------
| Built-in programme / branch catalogue
|--------------------------------------------------------------------------
|
| Transcribed verbatim from CDC/frontend/components/forms/shared/eligibilitygrid.tsx
| (defaultProgrammes). 8 programmes / 52 branches. Keep the two in sync:
| the frontend list drives the JNF/INF eligibility grid, this list drives
| server-side student validation and EligibilityService.
| Admin-added custom branches live in the programme_branches table and are
| merged in by App\Support\ProgrammeCatalogue.
|
*/

return [
    'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)' => [
        'Chemical Engineering',
        'Civil Engineering',
        'Computer Science & Engineering',
        'Electrical Engineering',
        'Electronics & Communication Engineering',
        'Engineering Physics',
        'Environmental Engineering',
        'Mathematics & Computing',
        'Mechanical Engineering',
        'Mechanical Engineering (Mining Machinery Engineering)',
        'Mineral & Metallurgical Engineering',
        'Mining Engineering',
        'Petroleum Engineering',
    ],
    'Integrated M.Tech (5 Year) - JEE Advanced' => [
        'Mathematics & Computing',
        'Applied Geology',
        'Applied Geophysics',
    ],
    'M.Tech (2 Year) - GATE' => [
        'Earthquake Science & Engineering (Applied Geophysics)',
        'Chemical Engineering',
        'Pharmaceutical Science and Engineering',
        'Civil Engineering',
        'Computer Science and Engineering',
        'Power System Engineering (Electrical Engineering)',
        'Power Electronics & Electrical Drives (Electrical Engineering)',
        'Communication & Signal Processing (Electronics and Communication Engineering)',
        'Optical Communication & Integrated Photonics (Electronics and Communication Engineering)',
        'RF & Microwave Engineering (Electronics and Communication Engineering)',
        'VLSI Design (Electronics and Communication Engineering)',
        'Environmental Science & Engineering',
        'Fuel and Energy Engineering',
        'Mineral Engineering',
        'Metallurgical Engineering',
        'Industrial Engineering & Management',
        'Data Analytics',
        'Machine Design (Mechanical Engineering)',
        'Manufacturing Engineering (Mechanical Engineering)',
        'Thermal Engineering (Mechanical Engineering)',
        'Mining Engineering',
        'Geomatics (Mining Engineering)',
        'Tunneling and Underground Space Technology (Mining Engineering)',
        'Petroleum Engineering',
    ],
    'M.Sc. Tech (3 Year) - JAM' => [
        'Applied Geology',
        'Applied Geophysics',
    ],
    'MBA (2 Year) - CAT' => [
        'MBA - Business Analytics',
        'MBA - Finance',
        'MBA - Marketing',
        'MBA - HR',
        'MBA - Operations',
    ],
    'M.Sc (2 Year) - JAM' => [
        'Physics',
        'Chemistry',
        'Mathematics & Computing',
    ],
    'M.A. (2 Year) - Digital Humanities & Social Sciences' => [
        'Digital Humanities & Social Sciences',
    ],
    'Ph.D - GATE/NET' => [
        'All Departments (Specify in Job Description)',
    ],
];
