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

/**
 * ImportLeads Page
 *
 * This page handles CSV imports of Lead data into the system.
 * It provides a form interface for file uploads and processes
 * the CSV data to create Lead records with team associations.
 */
class ImportLeads extends Page
{
    use InteractsWithForms;

    // Navigation settings for Filament
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.import-leads';
    protected static ?string $navigationGroup = 'Lead Management';
    protected static ?string $navigationLabel = 'Import Leads';
    protected static ?int $navigationSort = 2;

    // Form data properties
    public ?array $data = [];
    public $csv_file = null;

    /**
     * Initialize the form when the page is loaded
     */
    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * Define the file upload form
     */
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

    /**
     * Process the uploaded CSV file and import leads
     *
     * This method:
     * 1. Validates the uploaded file
     * 2. Parses the CSV header and data
     * 3. Maps CSV columns to Lead model attributes
     * 4. Creates Lead records in the database
     * 5. Associates leads with teams based on the CSV data
     * 6. Creates teams if they don't already exist
     */
    public function importLeads()
    {
        $data = $this->form->getState();

        // Validate file upload
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
                // Process header row
                $header = fgetcsv($handle, 1000, ',');
                $header = array_map('strtolower', $header);
                $header = array_map('trim', $header);

                // Check for required fields
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

                // Find team column in headers (supports multiple naming conventions)
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

                // Cache team names to IDs for faster lookups
                $teamsCache = Team::pluck('id', 'name')->toArray();
                $successCount = 0;
                $errorCount = 0;
                $createdTeams = 0;
                $errors = [];

                // Use transaction for data integrity
                DB::beginTransaction();
                try {
                    // Process each data row
                    while (($rowData = fgetcsv($handle, 1000, ',')) !== false) {
                        $leadData = [];
                        $teamNamesRaw = null;

                        // Skip rows with insufficient data
                        if (count($rowData) < count($header)) {
                            $errorCount++;
                            continue;
                        }

                        // Extract team names if team column exists
                        if ($teamFieldIndex !== false && isset($rowData[$teamFieldIndex])) {
                            $teamNamesRaw = $rowData[$teamFieldIndex];
                        }

                        // Map CSV data to Lead model attributes
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
                            // Create the lead record
                            $lead = Lead::create($leadData);

                            // Process teams if team data exists
                            if (!empty($teamNamesRaw)) {
                                // Support both comma and pipe separators for teams
                                $teamNames = array_map('trim', preg_split('/[,|]+/', $teamNamesRaw));
                                $teamIds = [];

                                // Process each team name
                                foreach ($teamNames as $teamName) {
                                    if (!empty($teamName)) {
                                        if (isset($teamsCache[$teamName])) {
                                            // Use cached team ID if available
                                            $teamIds[] = $teamsCache[$teamName];
                                        } else {
                                            // Find or create team with a single eloquent method
                                            $team = Team::firstOrCreate(['name' => $teamName]);
                                            if ($team->wasRecentlyCreated) {
                                                $createdTeams++;
                                            }
                                            $teamsCache[$teamName] = $team->id;

                                            $teamIds[] = $team->id;
                                        }
                                    }
                                }

                                // Associate lead with teams
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

                    // Commit transaction, clean up and notify user
                    DB::commit();
                    fclose($handle);
                    Storage::disk('public')->delete($this->form->getState()['csv_file']);

                    // Build success message
                    $successMessage = "Successfully imported {$successCount} leads";
                    if ($createdTeams > 0) {
                        $successMessage .= ", created {$createdTeams} new teams";
                    }
                    if ($errorCount > 0) {
                        $successMessage .= " with {$errorCount} errors";
                    }

                    // Log import results
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
                    // Rollback transaction on error
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

        // Reset form after import
        $this->csv_file = null;
        $this->form->fill([]);
    }
}
