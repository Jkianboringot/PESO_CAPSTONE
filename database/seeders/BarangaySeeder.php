<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Barangay;
use App\Models\Municipality;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $data = [

            'Virac' => [
                'Alitao', 'Balite', 'Bato', 'Buenavista', 'Calatagan Proper',
                'Calatagan Tibang', 'Calatagan Center', 'Calatagan Poblacion',
                'Danicop', 'Hicming', 'Igang', 'Juan M. Alberto',
                'Libjo', 'Magnesia del Sur', 'Magnesia del Norte',
                'Palnab del Sur', 'Palnab del Norte', 'San Isidro Village',
                'Santa Elena', 'Santa Cruz', 'Santo Domingo', 'Santo Niño',
                'Talisoy', 'Tawog', 'Yawa'
            ],

            'San Andres' => [
                'Alibuag', 'Balatohan', 'Belmonte', 'Codon', 'Comagaycay',
                'Datag East', 'Datag West', 'Igang', 'Lubas', 'Manambrag',
                'Mayngaway', 'Poblacion', 'Rizal', 'San Jose',
                'San Juan', 'San Pedro', 'Sugod', 'Tibgao'
            ],

            'Bato' => [
                'Bagumbayan', 'Batalay', 'Binanwahan', 'Carorian',
                'Carriedo', 'Libjo', 'Mintay', 'Oguis', 'Pananaogan',
                'Panganiban', 'San Isidro', 'San Miguel', 'Sipi',
                'Tambongon', 'Tinambac', 'Cabugao'
            ],

            'Baras' => [
                'Abihao', 'Bacong', 'Bagong Sirang', 'Benticayan',
                'Buenavista', 'Caragumihan', 'Danao', 'Guintinuaan',
                'Moning', 'Nagbalayong', 'Osmeña', 'Poblacion',
                'Salvacion', 'San Lorenzo', 'San Miguel', 'Tilod'
            ],

            'Bagamanoc' => [
                'Antipolo', 'Bugao', 'Hinipaan', 'Magsaysay',
                'Poblacion', 'Quirino', 'San Isidro Sur',
                'San Isidro Norte', 'Santa Mesa', 'Suchan'
            ],

            'Caramoran' => [
                'Balagon', 'Bococ', 'Buenavista', 'Datag',
                'Gigmoto', 'Lahong', 'Mabini', 'Marilima',
                'Panganiban', 'Poblacion', 'San Vicente',
                'Toytoy', 'Tubli'
            ],

            'Gigmoto' => [
                'Biong', 'Dororian', 'Poblacion District I',
                'Poblacion District II', 'San Pedro',
                'Sioron', 'Tawog'
            ],

            'Pandan' => [
                'Bagumbayan', 'Balog', 'Barihay', 'Cagbunga',
                'Hiyop', 'Lictin', 'Pandan Proper',
                'San Andres', 'San Isidro', 'San Roque'
            ],

            'Panganiban' => [
                'Alfonso', 'Bagong Bayan', 'Bato', 'Buenavista',
                'Burabod', 'Cabuyoan', 'San Vicente', 'Taopon'
            ],

            'San Miguel' => [
                'Boton', 'Kilikilihan', 'Mabato', 'Poblacion',
                'San Marcos', 'San Roque'
            ],

            'Viga' => [
                'Ananong', 'Balite', 'Bato', 'Buenavista',
                'Calatagan', 'Mabini', 'Poblacion', 'San Vicente'
            ],

        ];

        foreach ($data as $municipalityName => $barangays) {
            $municipality = Municipality::where('name', $municipalityName)->first();

            if (!$municipality) continue;

            foreach ($barangays as $barangay) {
                Barangay::create([
                    'name' => $barangay,
                    'municipality_id' => $municipality->id,
                ]);
            }
        }
    }
}