<?php
declare(strict_types=1);

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// strict

class MunicipalityManagement extends Component
{


    public bool $showModal = false;

    public string $MunicipalityName = '';



    public function rules()
    {



        return [
            'MunicipalityName' => 'required|string|max:75|min:5|alpha_dash',//alpha is tem, wil fix it later or replace it with regrex

        ];
    }
    public function openCreate()
    {

        $this->showModal = true;
    }


    public function barangayCreate()
    {
        Municipality::create([
            'name' => $this->MunicipalityName,
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
            $this->barangayCreate();
            return redirect()->route('geogrophical')
                ->with('success', 'Successfully Created.');
        } catch (\Throwable $th) {
            Log::error('Barangay creation failed', [
                'user_id' => auth()->id(),
                'error' => $th->getMessage(),
            ]); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }



    }


    public function render()
    {

        return view('livewire.geogrophical');
    }
}
