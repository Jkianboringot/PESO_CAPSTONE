<?php
declare(strict_types=1);

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// strict

class Geogrophical extends Component
{

    public string $municipalityName = '';
    public string $municipalityProvince = '';

    public bool $showModal = false;

    public string $barangayName = '';

    public ?int $municipalityID = null;

    // public true $currentModel=false;

    public function rules()
    {

        $createMunicipality = false;
        $validateMunicipality = [
            'municipalityName' => 'required|string|max:75|min:5',
            'municipalityProvince' => 'required|string|max:45|min:5|unique'
        ];
        if ($this->municipalityName || $this->municipalityProvince) {
            $createMunicipality = true;
        }
        $validateBarangay = [
            'barangayName' => 'required|string|max:75|min:5',
            'municipalityID' => 'required|int|exist:municipalities,id',
            $createMunicipality ?? $validateMunicipality
        ];

        return $validateBarangay;
    }
    public function openCreate()
    {

        $this->showModal = true;
    }

    public function geoCreate()
    {
        Barangay::create([
            'name' => $this->barangayName,
            'municipality_id' => $this->municipalityID,
        ])->save();
    }

    public function save()
    {
        abort_if( // TODO-LATER - dont use this, use a proper message error , this is just for testing
            !auth()->user()->hasRole(['staff', 'admin']),
            403
        );

        // dd($this->municipalityID);

        // $this->validate($this->rules());
        try {

            $this->geoCreate();

            return redirect()->route('geogrophical')
                ->with('success', 'Successfully Created.');
        } catch (\Throwable $th) {
            Log::error($th); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }



    }


    public function render()
    {

        return view('livewire.geogrophical', [
            'selectMunicipality' => Municipality::get(['id', 'name']),
            'viewBarangays' => Barangay::with('municipality')->orderBy('name')->paginate(20)
        ]);
    }
}
