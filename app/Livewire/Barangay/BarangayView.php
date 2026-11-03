<?php

namespace App\Livewire\Barangay;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// strict

class BarangayView  extends Component
{

    
    

    public function render()
    {

        return view('livewire.geogrophical', [
            'selectMunicipality' => Municipality::get(['id', 'name']),
            'viewBarangays' => Barangay::with('municipality')->orderBy('name')->paginate(20)
        ]);
    }
}
