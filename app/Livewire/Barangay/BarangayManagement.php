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

    
    public bool $showModal = false;

    public string $barangayName = '';

    public ?int $municipalityID = null;

    // public true $currentModel=false;

    public function rules()
    {

        
        $validateBarangay = [
            'barangayName' => 'required|string|max:75|min:5',
            'municipalityID' => 'required|int|exist:municipalities,id',
            
        ];

        return $validateBarangay;
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
DB::transaction(function () use ($detector, $audit) {
            $this->barangayCreate();
            )};

            return redirect()->route('geogrophical')
                ->with('success', 'Successfully Created.');
        } catch (\Throwable $th) {
            Log::error($th); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }



    }


    public function render()
    {

        return view('livewire.geogrophical');
    }
}
