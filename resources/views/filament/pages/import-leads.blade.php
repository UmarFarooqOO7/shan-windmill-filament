<x-filament-panels::page>
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-2">Import Leads from CSV</h2>
        <p>Upload a CSV file containing lead data to import into the system.</p>
    </div>

    <div>
        <form wire:submit="importLeads">
            {{ $this->form }}

            <div class="mt-4 flex gap-4">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Import Leads</span>
                    <span wire:loading>Processing...</span>
                </x-filament::button>

                <x-filament::button
                    href="{{ asset('sample-leads.csv') }}"
                    tag="a"
                    color="gray"
                    target="_blank"
                >
                    Download Sample CSV
                </x-filament::button>
            </div>
        </form>
    </div>

    <div>
        <h3 class="text-lg font-medium mb-2">CSV Format Requirements:</h3>
        <ul class="list-disc pl-5 mb-4">
            <li>The first row must be a header row containing column names.</li>
            <li><strong>Required field:</strong> plaintiff</li>
            <li><strong>Team Assignment:</strong> Include a "RID" column with team name(s). For multiple teams, use either commas or pipes (e.g., "Team A, Team B" or "Team A|Team B").</li>
            <li><strong>Other supported fields:</strong> address, county, city, state, zip, case_number, setout_date, setout_time, status, writ, setout, lbx, vis_setout, vis_to, notes, time_on, setout_st, setout_en, time_en, locs, amount_cleared, amount_owed</li>
        </ul>

        <div class="bg-blue-50 p-4 border border-blue-200 rounded">
            <p class="text-blue-800"><strong>Note:</strong> Teams and statuses will be automatically created if they don't exist yet. Each lead will be associated with the specified teams.<br>Status fields (status, setout, writ) with new values will create corresponding status records.</p>
        </div>
    </div>
</x-filament-panels::page>
