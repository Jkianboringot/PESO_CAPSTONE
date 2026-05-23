<?php
declare(strict_types=1);
namespace App\Livewire;

use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SkillAndCategory extends Component
//if am bored maybe i will put interface in this
{
    
    public string $categorySkillName = '';
    public string $categorySkillSet= '';

    public bool $showModal = false;

    public string $skillName = '';

    public ?int $categorySkillID = null;

    // public true $currentModel=false;

    public function rules()
    {

        // $createMunicipality = false;
        // $validateMunicipality = [
        //     'municipalityName' => 'required|string|max:75|min:5',
        //     'municipalityProvince' => 'required|string|max:45|min:5|unique'
        // ];
        // if ($this->municipalityName || $this->municipalityProvince) {
        //     $createMunicipality = true;
        // }
        $validateBarangay = [
            'skillName' => 'required|string|max:75|min:5',
            'categorySkillID' => 'required|int|exist:municipalities,id',
            // $createMunicipality ?? $validateMunicipality
        ];

        return $validateBarangay;
    }
    public function openCreate()
    {

        $this->showModal = true;
    }

    public function skillCreate()
    {

        Skill::create([
            'name' => $this->skillName,
            'municipality_id' => $this->categorySkillID,
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

            $this->skillCreate();

            return redirect()->route('skill')
                ->with('success', 'Successfully Created.');
        } catch (\Throwable $th) {
            Log::error($th); //TODO-LATER make sure all has this, logging
            $this->dispatch('done', error: "Something went wrong. Please try again.");
        }



    }


 
    public function render()
    {
        return view('livewire.skill-and-category',
    [
            'selectMunicipality' => SkillCategory::get(['id', 'name']),
            'viewBarangays' => Skill::with('category')->orderBy('name')->paginate(20)
        ]);
    }
}
