{

    
    

    public function render()
    {

        return view('livewire.geogrophical', [
            'selectMunicipality' => Municipality::get(['id', 'name']),
            'viewBarangays' => Barangay::with('municipality')->orderBy('name')->paginate(20)
        ]);
    }
}