<?php

namespace App\Filament\Actions;

use App\Models\Lead;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Filament\Forms;

class PrintLeadsBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'print';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print Selected');
        $this->icon('heroicon-o-printer');
        $this->color('success');
        $this->modalHeading('Print Selected Leads');
        $this->modalWidth(MaxWidth::FitContent);

        // Add form fields for column selection
        $this->form([
            Forms\Components\Section::make('Select Columns to Print')
                ->description('Choose which columns to include in the printout')
                ->schema([
                    Forms\Components\CheckboxList::make('columns')
                        ->options([
                            'id' => 'ID',
                            'ref' => 'REF',
                            'plaintiff' => 'Plaintiff',
                            'defendant_first_name' => 'Defendant First Name',
                            'defendant_last_name' => 'Defendant Last Name',
                            'address' => 'Address',
                            'county' => 'County',
                            'city' => 'City',
                            'state' => 'State',
                            'zip' => 'Zip',
                            'case_number' => 'Case Number',
                            'setout_date' => 'Setout Date',
                            'setout_time' => 'Setout Time',
                            'status' => 'Status',
                            'setout_status' => 'Setout Status',
                            'writ_status' => 'Writ Status',
                            'lbx' => 'LBX',
                            'vis_setout' => 'Vis-LO',
                            'vis_to' => 'Vis-TO',
                            'time_on' => 'Time Start',
                            'time_en' => 'Time End',
                            'setout_st' => 'Setout Start',
                            'setout_en' => 'Setout End',
                            'locs' => 'LOCS',
                            'amount_owed' => 'Amount Owed',
                            'amount_cleared' => 'Amount Cleared',
                            'notes' => 'Notes',
                        ])
                        ->columns(3)
                        ->gridDirection('row')
                        ->default([
                            'ref',
                            'plaintiff',
                            'defendant_first_name',
                            'defendant_last_name',
                            'address',
                            'county',
                            'city',
                            'state',
                            'zip',
                            'case_number',
                            'setout_date',
                            'setout_time',
                            'status',
                            'setout_status',
                            'writ_status',
                        ])
                        ->bulkToggleable()
                ])
        ]);

        // Simply redirect to the print page after form submission
        $this->action(function (array $data, Collection $records) {
            // Get the selected columns or use defaults
            $columns = $data['columns'] ?? [];

            // Store columns and record IDs in the session
            $recordIds = $records->pluck('id')->toArray();
            session(['print_columns' => $columns, 'print_record_ids' => $recordIds]);

            // Redirect to the print page
            return redirect()->route('print-leads');
        });
    }
}
