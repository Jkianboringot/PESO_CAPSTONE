<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                $municipalities = [
            'Bagamanoc', 'Baras', 'Bato', 'Caramoran', 'Gigmoto',
            'Pandan', 'Panganiban', 'San Andres', 'San Miguel',
            'Viga', 'Virac',
        ];
        foreach ($municipalities as $m) {
            Municipality::create(['name' => $m, 'province' => 'Catanduanes']);
        }

    }
}
