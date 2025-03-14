<?php

namespace App\Filament\Pages;

use App\Models\Lead;
use App\Models\Team;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImportLeads extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.import-leads';
    protected static ?string $navigationGroup = 'Lead Management';
    protected static ?string $navigationLabel = 'Import Leads';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    public $csv_file = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('csv_file')
                    ->label('CSV File')
                    ->helperText('Upload a CSV file containing lead data. Include a "RID" column for team assignments.')
                    ->required()
                    ->disk('public')
                    ->directory('csv-imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
            ]);
    }

    public function importLeads()
    {
        $data = $this->form->getState();

        if (empty($data['csv_file'])) {
            Notification::make()
                ->title('Import Error')
                ->body('No file was uploaded')
                ->danger()
                ->send();
            return;
        }

        try {
            $filePath = Storage::disk('public')->path($data['csv_file']);
            $leadColumns = Schema::getColumnListing('leads');

            if (($handle = fopen($filePath, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ',');
                $header = array_map('strtolower', $header);
                $header = array_map('trim', $header);

                $requiredFields = ['plaintiff'];
                $missingRequiredFields = array_diff($requiredFields, $header);

                if (!empty($missingRequiredFields)) {
                    Notification::make()
                        ->title('Import Error')
                        ->body('The CSV file is missing required fields: ' . implode(', ', $missingRequiredFields))
                        ->danger()
                        ->send();
                    fclose($handle);
                    return;
                }

                $teamFieldIndex = false;
                foreach (['team', 'teams', 'rid', 'team_names'] as $possibleFieldName) {
                    $teamFieldIndex = array_search($possibleFieldName, $header);
                    if ($teamFieldIndex !== false) break;
                }

                if ($teamFieldIndex === false) {
                    Notification::make()
                        ->title('Import Warning')
                        ->body('No team column found in CSV. Leads will be created without team assignments.')
                        ->warning()
                        ->send();
                }

                $teamsCache = Team::pluck('id', 'name')->toArray();
                $successCount = 0;
                $errorCount = 0;
                $createdTeams = 0;
                $errors = [];

                DB::beginTransaction();
                try {
                    while (($rowData = fgetcsv($handle, 1000, ',')) !== false) {
                        $leadData = [];
                        $teamNamesRaw = null;

                        if (count($rowData) < count($header)) {
                            $errorCount++;
                            continue;
                        }

                        if ($teamFieldIndex !== false && isset($rowData[$teamFieldIndex])) {
                            $teamNamesRaw = $rowData[$teamFieldIndex];
                        }

                        foreach ($header as $index => $columnName) {
                            if (!isset($rowData[$index]) || $index === $teamFieldIndex) {
                                continue;
                            }

                            $value = $rowData[$index] !== '' ? $rowData[$index] : null;

                            if (in_array($columnName, $leadColumns)) {
                                $leadData[$columnName] = $value;
                            }
                        }

                        try {
                            $lead = Lead::create($leadData);

                            if (!empty($teamNamesRaw)) {
                                // Support both comma and pipe separators for teams
                                $teamNames = array_map('trim', preg_split('/[,|]+/', $teamNamesRaw));
                                $teamIds = [];

                                foreach ($teamNames as $teamName) {
                                    if (!empty($teamName)) {
                                        if (isset($teamsCache[$teamName])) {
                                            $teamIds[] = $teamsCache[$teamName];
                                        } else {
                                            $team = Team::firstWhere('name', $teamName);

                                            if (!$team) {
                                                $team = Team::create(['name' => $teamName]);
                                                $createdTeams++;
                                                $teamsCache[$teamName] = $team->id;
                                            }

                                            $teamIds[] = $team->id;
                                        }
                                    }
                                }

                                if (!empty($teamIds)) {
                                    $lead->teams()->sync($teamIds);
                                }
                            }

                            $successCount++;
                            unset($lead);
                        } catch (\Exception $e) {
                            $errorCount++;
                            $errors[] = $e->getMessage();
                        }
                    }

                    DB::commit();
                    fclose($handle);
                    Storage::disk('public')->delete($this->form->getState()['csv_file']);

                    $successMessage = "Successfully imported {$successCount} leads";
                    if ($createdTeams > 0) {
                        $successMessage .= ", created {$createdTeams} new teams";
                    }
                    if ($errorCount > 0) {
                        $successMessage .= " with {$errorCount} errors";
                    }

                    Log::info("CSV Import Summary", [
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'created_teams' => $createdTeams,
                        'errors' => $errors
                    ]);

                    Notification::make()
                        ->title('Import Complete')
                        ->body($successMessage)
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    DB::rollBack();
                    fclose($handle);

                    Notification::make()
                        ->title('Import Error')
                        ->body('Error importing data: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Import Error')
                    ->body('Unable to open the CSV file')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Import Error')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }

        $this->csv_file = null;
        $this->form->fill([]);
    }
}
