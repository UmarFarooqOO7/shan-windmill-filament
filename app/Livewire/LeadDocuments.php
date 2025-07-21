<?php

namespace App\Livewire;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Lead;
use App\Models\LeadImage;
use Illuminate\Support\Facades\Log;

class LeadDocuments extends Component
{
    use WithFileUploads;

    public $lead;
    public $leadId;
    public $documents = [];




public function mount($leadId)
{
    Log::info('Mounting LeadDocuments with Lead ID:', ['leadId' => $leadId]);

    $this->leadId = $leadId;
    $this->lead = \App\Models\Lead::findOrFail($leadId);
}


    public function upload()
    {
        $this->validate([
            'documents.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        foreach ($this->documents as $file) {
            $path = $file->store('lead_documents');

            LeadImage::create([
                'lead_id' => $this->lead->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
            ]);
        }

        $this->documents = [];
        session()->flash('message', 'Files uploaded successfully.');
    }

    public function deleteDocument($id)
    {
        $document = LeadImage::findOrFail($id);
        Storage::delete($document->file_path);
        $document->delete();
    }

    public function render()
    {
        return view('livewire.lead-documents');
    }
}
