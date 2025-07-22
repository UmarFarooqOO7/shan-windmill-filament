<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Lead;
use Illuminate\Support\Facades\Storage;

class LeadDocuments extends Component
{
    public $lead;
    public $documents = [];

    public function mount($lead)
    {
        $leadModel = Lead::find($lead);
        $this->lead = $leadModel;
        $this->documents = $leadModel?->documents ?? [];
    }




    public function removeDocument($index)
        {
            // Get the file path from the documents array
            $file = $this->documents[$index] ?? null;

            if (!$file) return;

            // Delete file from storage (public disk)
            Storage::disk('public')->delete($file);

            // Remove from documents array
            unset($this->documents[$index]);

            // Re-index the array
            $this->documents = array_values($this->documents);

            // Save back to DB
            $this->lead->update([
                'documents' => $this->documents,
            ]);
}


    public function render()
    {
        return view('livewire.lead-documents');
    }
}
