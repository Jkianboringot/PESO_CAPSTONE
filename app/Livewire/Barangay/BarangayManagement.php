<?php
declare(strict_types=1);

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// strict

class BarangayManagement extends Component
{


    public bool $showModal = false;

    public string $barangayName = '';

    public ?int $municipalityID = null;

    // public true $currentModel=false;

    public function rules()
    {



        return [
            'barangayName' => 'required|string|max:75|min:5|alpha_dash',//alpha is tem, wil fix it later or replace it with regrex
            'municipalityID' => 'required|int|exist:municipalities,id',

        ];
    }
    public function openCreate()
    {

        $this->showModal = true;
    }


    public function barangayCreate()
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

        //REVIEW - do i really need 5o try catch thisl i know transaction already has it
        try {
            DB::transaction(function () {
                $this->barangayCreate();
            });
            return redirect()->route('geogrophical')
                ->with('success', 'Successfully Created.');
        } catch (\Throwable $th) {
            Log::error('Barangay creation failed', [
                'user_id' => auth()->id(),
                'municipality_id' => $this->municipalityID,
                'error' => $th->getMessage(),
            ]); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }



    }


    public function render()
    {


        return view('livewire.geogrophical', ['selectMunicipality' => Municipality::get(['id', 'name'])]);
    }
}
