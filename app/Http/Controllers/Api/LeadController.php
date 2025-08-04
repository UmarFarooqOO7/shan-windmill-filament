<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($search = $request->input('search')) {
            $searchableFields = [
                'plaintiff',
                'defendant_first_name',
                'defendant_last_name',
                'address',
                'county',
                'city',
                'state',
                'zip',
                'case_number',
            ];

            $query->where(function ($q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'like', '%' . $search . '%');
                }
            });
        }

        $leads = $query->latest()->paginate(15);
            return $this->success($leads, 'Leads retrieved successfully.');

    }

    // Show specific lead
    public function show(Lead $lead)
    {
        return $this->success($lead, 'Lead details retrieved.');
    }

    // Create new lead
    public function store(Request $request)
    {
        $validated = $this->validateLead($request);

        if ($validated->fails()) {
            return $this->error($validated->errors()->first(), 422);
        }

        $data = $request->except('documents');

        // handle documents
        $documentsPaths = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $documentsPaths[] = $file->store('lead_documents', 'public');
            }
        }
        $data['documents'] = $documentsPaths;

        $lead = Lead::create($data);

        return $this->success($lead, 'Lead created successfully.');
    }

    // Update lead
    public function update(Request $request, Lead $lead)
    {
        $validated = $this->validateLead($request, $lead->id);

        if ($validated->fails()) {
            return $this->error($validated->errors()->first(), 422);
        }

        $data = $request->except('documents');

        $documentsPaths = $lead->documents ?? [];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $documentsPaths[] = $file->store('lead_documents', 'public');
            }
        }

        $data['documents'] = $documentsPaths;

        $lead->update($data);

        return $this->success($lead, 'Lead updated successfully.');
    }


    // Delete lead
    public function destroy(Lead $lead)
    {
        $lead->delete();
        return $this->success([], 'Lead deleted successfully.');
    }

    protected function validateLead(Request $request, $id = null)
    {
        return Validator::make($request->all(), [
            'plaintiff' => 'required|string',
            'defendant_first_name' => 'nullable|string',
            'defendant_last_name' => 'nullable|string',
            'address' => 'nullable|string',
            'county' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'zip' => 'nullable|string',
            'case_number' => 'nullable|string',
            'setout_date' => 'nullable|date',
            'setout_time' => 'nullable|string',
            'status' => 'nullable|string',
            'setout' => 'nullable|string',
            'writ' => 'nullable|string',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'amount_owed' => 'nullable|numeric',
            'amount_cleared' => 'nullable|numeric',
            'lbx' => 'nullable|string',
            'vis_setout' => 'nullable|string',
            'vis_to' => 'nullable|string',
            'notes' => 'nullable|string',
            'time_on' => 'nullable|string',
            'setout_st' => 'nullable|string',
            'setout_en' => 'nullable|string',
            'time_en' => 'nullable|string',
            'locs' => 'nullable|string',
        ]);
    }

}

