<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                $data = [
            'ICT & Digital Technology' => [
                'Computer Hardware Servicing','Web Development',
                'Network Administration','Data Encoding','Graphic Design',
                'Social Media Management','Software Development',
            ],
            'Agricultural & Fisheries' => [
                'Rice Farming','Coconut Processing','Fishery Operations',
                'Vegetable Production','Animal Husbandry',
            ],
            'Construction & Engineering' => [
                'Masonry','Carpentry','Plumbing','Electrical Wiring',
                'Welding & Fabrication',
            ],
            'Health & Social Services' => [
                'Caregiving','Health Care Support','Community Health Work',
                'Medical Transcription',
            ],
            'Tourism & Hospitality' => [
                'Food & Beverage Service','Housekeeping','Front Office Services',
                'Tour Guiding','Bartending',
            ],
            'Business & Administration' => [
                'Bookkeeping','Customer Service','Office Administration',
                'Business Process Outsourcing',
            ],
            'Maritime & Transport' => [
                'Vessel Operations','Port Operations','Driving (Professional)',
            ],
            'Arts, Crafts & Design' => [
                'Pottery & Ceramics','Weaving','Jewelry Making','Painting',
            ],
            'Teaching & Education' => [
                'Elementary Teaching','Secondary Teaching','Vocational Training',
            ],
            'Trade & Technical Services' => [
                'Automotive Servicing','Refrigeration & AC Servicing',
                'Tailoring & Dressmaking','Beauty Care & Nail Care',
            ],
        ];
 
        foreach ($data as $catName => $skills) {
            $cat = SkillCategory::where('name', $catName)->first();
            if (!$cat) continue;
            foreach ($skills as $s) {
                Skill::create(['name' => $s, 'skill_category_id' => $cat->id]);
            }
        }

    }
}
