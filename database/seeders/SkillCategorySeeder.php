<?php

namespace Database\Seeders;

use App\Models\SkillCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SkillCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                $categories = [
            ['name' => 'ICT & Digital Technology',   'pqf_level' => 'NC II-NC IV'],
            ['name' => 'Agricultural & Fisheries',   'pqf_level' => 'NC I-NC III'],
            ['name' => 'Construction & Engineering', 'pqf_level' => 'NC II-NC III'],
            ['name' => 'Health & Social Services',   'pqf_level' => 'NC II-NC III'],
            ['name' => 'Tourism & Hospitality',      'pqf_level' => 'NC II-NC III'],
            ['name' => 'Business & Administration',  'pqf_level' => 'NC II-NC IV'],
            ['name' => 'Maritime & Transport',       'pqf_level' => 'NC II-NC III'],
            ['name' => 'Arts, Crafts & Design',      'pqf_level' => 'NC I-NC II'],
            ['name' => 'Teaching & Education',       'pqf_level' => 'Degree Level'],
            ['name' => 'Trade & Technical Services', 'pqf_level' => 'NC I-NC III'],
        ];
        foreach ($categories as $c) {
            SkillCategory::create($c);
        }

    }
}
