<?php
declare(strict_types=1);

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// strict

class MunicipalictyView extends Component
{

    
    

    public function render()
    {

        return view('livewire.geogrophical', [
            'viewBarangays' => Municipality::orderBy('name')->paginate(20)
        ]);
    }
}
