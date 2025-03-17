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
                // Cache status names for faster lookups
                $leadStatusesCache = \App\Models\Status::where('type', 'lead')->pluck('id', 'name')->toArray();
                $setoutStatusesCache = \App\Models\Status::where('type', 'setout')->pluck('id', 'name')->toArray();
                $writStatusesCache = \App\Models\Status::where('type', 'writ')->pluck('id', 'name')->toArray();

                $successCount = 0;
                $errorCount = 0;
                $createdTeams = 0;
                $createdStatuses = 0;
                $errors = [];
                $rowNumber = 1; // Header row is 0, first data row is 1

                // Use transaction for data integrity
                DB::beginTransaction();
                try {
                    // Process each data row
                    while (($rowData = fgetcsv($handle, 1000, ',')) !== false) {
                        $rowNumber++; // Increment row counter (starting at 2 since row 1 is header)
                        $leadData = [];
                        $teamNamesRaw = null;

                        // Skip rows with insufficient data
                        if (count($rowData) < count($header)) {
                            $errorCount++;
                            $errors[] = [
                                'row' => $rowNumber,
                                'data' => $rowData,
                                'error' => 'Row has fewer columns than header',
                                'details' => 'Expected ' . count($header) . ' columns, got ' . count($rowData)
                            ];
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
                                // Check if this is a status field and create if needed
                                if ($value && in_array($columnName, ['status', 'setout', 'writ'])) {
                                    // Determine status type based on column name
                                    $statusType = $columnName === 'status' ? 'lead' : ($columnName === 'setout' ? 'setout' : 'writ');
                                    $statusCache = $statusType === 'lead' ? $leadStatusesCache :
                                                 ($statusType === 'setout' ? $setoutStatusesCache : $writStatusesCache);

                                    // Check if status exists, if not create it
                                    if (!isset($statusCache[$value])) {
                                        try {
                                            $status = \App\Models\Status::firstOrCreate([
                                                'name' => $value,
                                                'type' => $statusType
                                            ]);

                                            if ($status->wasRecentlyCreated) {
                                                $createdStatuses++;

                                                // Update cache
                                                if ($statusType === 'lead') {
                                                    $leadStatusesCache[$value] = $status->id;
                                                } elseif ($statusType === 'setout') {
                                                    $setoutStatusesCache[$value] = $status->id;
                                                } else {
                                                    $writStatusesCache[$value] = $status->id;
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            // Log status creation error but continue with import
                                            Log::warning("Failed to create status", [
                                                'status' => $value,
                                                'type' => $statusType,
                                                'error' => $e->getMessage()
                                            ]);
                                        }
                                    }
                                }

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
                            $errors[] = [
                                'row' => $rowNumber,
                                'data' => $rowData,
                                'lead_data' => $leadData,
                                'error' => $e->getMessage(),
                                'code' => $e->getCode()
                            ];
                        }
                    }

                    // Commit transaction, clean up and notify user
                    DB::commit();
                    fclose($handle);

                    // Export error details to CSV if there were errors
                    $errorFilePath = null;
                    $errorReportUrl = null;
                    if ($errorCount > 0) {
                        $errorFilePath = $this->exportErrorsToCSV($errors, $header);
                        $errorReportUrl = Storage::url($errorFilePath);

                        // Log each error individually for better visibility in logs
                        foreach ($errors as $error) {
                            Log::error("CSV Import Row Error", [
                                'row' => $error['row'],
                                'error' => $error['error'],
                                'details' => $error['details'] ?? '',
                                'error_report_url' => $errorReportUrl
                            ]);
                        }
                    }

                    Storage::disk('public')->delete($this->form->getState()['csv_file']);

                    // Build success message
                    $successMessage = "Successfully imported {$successCount} leads";
                    if ($createdTeams > 0) {
                        $successMessage .= ", created {$createdTeams} new teams";
                    }
                    if ($createdStatuses > 0) {
                        $successMessage .= ", created {$createdStatuses} new statuses";
                    }
                    if ($errorCount > 0) {
                        $successMessage .= " with {$errorCount} errors. See error log for details.";
                    }

                    // Log import results
                    Log::info("CSV Import Summary", [
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'created_teams' => $createdTeams,
                        'created_statuses' => $createdStatuses,
                        'errors_count' => count($errors),
                        'error_report_url' => $errorReportUrl ?? null
                    ]);

                    $notification = Notification::make()
                        ->title('Import Complete')
                        ->body($successMessage)
                        ->success();

                    // Add download link if there were errors
                    if ($errorCount > 0 && $errorFilePath) {
                        $notification->actions([
                            \Filament\Notifications\Actions\Action::make('download_errors')
                                ->label('Download Error Report')
                                ->url($errorReportUrl)
                                ->openUrlInNewTab(),
                        ]);
                    }

                    $notification->send();

                } catch (\Exception $e) {
                    // Rollback transaction on error
                    DB::rollBack();
                    fclose($handle);

                    Log::error("CSV Import Failed", [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);

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

    /**
     * Export error details to a downloadable CSV file
     *
     * @param array $errors The array of error data
     * @param array $header The original CSV header
     * @return string The path to the generated error CSV file
     */
    private function exportErrorsToCSV(array $errors, array $header): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "import-errors-{$timestamp}.csv";
        $filePath = "error-reports/{$filename}";

        // Create the directory if it doesn't exist
        if (!Storage::disk('public')->exists('error-reports')) {
            Storage::disk('public')->makeDirectory('error-reports');
        }

        // Create CSV file with error details
        $handle = fopen(Storage::disk('public')->path($filePath), 'w');

        // Write header row with reordered columns (Raw Error right after Error Message)
        fputcsv($handle, array_merge(
            ['Row Number', 'Error Message', 'Raw Error'],
            $header
        ));

        // Write error data with reordered columns
        foreach ($errors as $error) {
            $rowData = $error['data'] ?? [];
            $rowData = array_pad($rowData, count($header), ''); // Ensure consistent columns

            fputcsv($handle, array_merge(
                [$error['row'], $error['error'], $error['details'] ?? ''],
                $rowData
            ));
        }

        fclose($handle);

        return $filePath;
    }
}
