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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportLeads extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.import-leads';

    // Group with Leads management
    protected static ?string $navigationGroup = 'Lead Management';

    protected static ?string $navigationLabel = 'Import Leads';

    protected static ?int $navigationSort = 3;

    // Define the form data property
    public ?array $data = [];

    // Define the file upload property
    public $csv_file;

    // Debug property to store the last error
    public $lastError = '';

    // Add properties for progress tracking
    public $isImporting = false;
    public $progress = 0;
    public $totalRows = 0;
    public $processedRows = 0;
    public $successCount = 0;
    public $errorCount = 0;
    public $createdTeams = 0;

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
                    ->helperText('Upload a CSV file containing lead data. Include a "team" column for team assignments.')
                    ->required()
                    ->disk('public')
                    ->directory('csv-imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
            ]);
    }

    public function importLeads()
    {
        // Validate form
        $data = $this->form->getState();

        // Safety check
        if (empty($data['csv_file'])) {
            Notification::make()
                ->title('Import Error')
                ->body('No file was uploaded')
                ->danger()
                ->send();

            return;
        }

        // Reset progress values
        $this->isImporting = true;
        $this->progress = 0;
        $this->totalRows = 0;
        $this->processedRows = 0;
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->createdTeams = 0;
        $this->lastError = '';

        try {
            $filePath = Storage::disk('public')->path($data['csv_file']);

            // Get actual database columns
            $leadColumns = Schema::getColumnListing('leads');

            // Open the file
            if (($handle = fopen($filePath, 'r')) !== false) {
                // Get headers
                $header = fgetcsv($handle, 1000, ',');
                $header = array_map('strtolower', $header);
                $header = array_map('trim', $header);

                // Get required fields and validate
                $requiredFields = ['plaintiff', 'defendant_first_name', 'defendant_last_name'];
                $missingRequiredFields = array_diff($requiredFields, $header);
                if (!empty($missingRequiredFields)) {
                    $this->isImporting = false;
                    Notification::make()
                        ->title('Import Error')
                        ->body('The CSV file is missing required fields: ' . implode(', ', $missingRequiredFields))
                        ->danger()
                        ->send();
                    fclose($handle);
                    return;
                }

                // Check for team column (looking for various possible field names)
                $teamFieldIndex = false;
                foreach (['team', 'teams', 'rid', 'team_name', 'team_names'] as $possibleFieldName) {
                    $teamFieldIndex = array_search($possibleFieldName, $header);
                    if ($teamFieldIndex !== false) {
                        break;
                    }
                }

                if ($teamFieldIndex === false) {
                    Notification::make()
                        ->title('Import Warning')
                        ->body('No team column found in CSV. Leads will be created without team assignments.')
                        ->warning()
                        ->send();
                }

                // Cache all teams to avoid redundant queries
                $teamsCache = Team::pluck('id', 'name')->toArray();

                // Count total rows for progress calculation (excluding header)
                $this->totalRows = $this->countCsvRows($filePath) - 1;

                // Reset file pointer to start processing data rows
                rewind($handle);
                // Skip the header row
                fgetcsv($handle);

                // Make sure total rows is at least 1 to avoid division by zero
                if ($this->totalRows < 1) {
                    $this->totalRows = 1;
                }

                DB::beginTransaction();
                try {
                    $chunkSize = 100;
                    $rowNumber = 1; // Header row is 0, first data row is 1
                    $errors = [];

                    while (($rowData = fgetcsv($handle, 1000, ',')) !== false) {
                        $rowNumber++;
                        $this->processedRows++;
                        $leadData = [];
                        $teamNamesRaw = null;

                        // Update progress as percentage
                        $this->progress = min(round(($this->processedRows / $this->totalRows) * 100), 100);

                        // Push updates to browser every few rows to avoid too many updates
                        if ($this->processedRows % 5 === 0 || $this->processedRows === $this->totalRows) {
                            $this->dispatch('progressUpdated');
                        }

                        // Check if we have a valid row with enough columns
                        if (count($rowData) < count($header)) {
                            $this->errorCount++;
                            $errors[] = "Row {$rowNumber}: Has fewer columns than expected";
                            continue;
                        }

                        // Extract team information if available
                        if ($teamFieldIndex !== false && isset($rowData[$teamFieldIndex])) {
                            $teamNamesRaw = $rowData[$teamFieldIndex];
                        }

                        // Map CSV columns to database fields
                        foreach ($header as $index => $columnName) {
                            // Skip if the column doesn't exist in this row or it's the team field
                            if (!isset($rowData[$index]) || $index === $teamFieldIndex) {
                                continue;
                            }

                            // Handle empty values
                            $value = $rowData[$index] !== '' ? $rowData[$index] : null;

                            // Only add fields that exist in the database
                            if (in_array($columnName, $leadColumns)) {
                                $leadData[$columnName] = $value;
                            }
                        }

                        try {
                            // Create the lead
                            $lead = Lead::create($leadData);

                            // Process team assignments
                            if (!empty($teamNamesRaw)) {
                                $teamNames = array_map('trim', explode(',', $teamNamesRaw));
                                $teamIds = [];

                                foreach ($teamNames as $teamName) {
                                    if (!empty($teamName)) {
                                        // Check if team exists in our cache
                                        if (isset($teamsCache[$teamName])) {
                                            $teamIds[] = $teamsCache[$teamName];
                                        } else {
                                            // If not in cache, try to find in database
                                            $team = Team::firstWhere('name', $teamName);

                                            // Create the team if it doesn't exist
                                            if (!$team) {
                                                $team = Team::create(['name' => $teamName]);
                                                $this->createdTeams++;
                                                // Add to cache for future lookups
                                                $teamsCache[$teamName] = $team->id;
                                            }

                                            $teamIds[] = $team->id;
                                        }
                                    }
                                }

                                // Sync teams to the lead through the pivot table
                                if (!empty($teamIds)) {
                                    $lead->teams()->sync($teamIds);
                                }
                            }

                            $this->successCount++;

                            // Free up memory
                            unset($lead);

                            if ($this->successCount % $chunkSize === 0) {
                                // Periodically commit changes to database
                                DB::commit();
                                DB::beginTransaction();
                            }
                        } catch (\Exception $e) {
                            // Log individual row errors but continue with import
                            $this->errorCount++;
                            $this->lastError = $e->getMessage();
                            $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                        }
                    }

                    DB::commit();
                    fclose($handle);

                    // Clean up the uploaded file
                    Storage::disk('public')->delete($this->form->getState()['csv_file']);

                    $successMessage = "Successfully imported {$this->successCount} leads";
                    if ($this->createdTeams > 0) {
                        $successMessage .= ", created {$this->createdTeams} new teams";
                    }
                    if ($this->errorCount > 0) {
                        $successMessage .= " with {$this->errorCount} errors";
                    }

                    // Only log the final summary
                    Log::info("CSV Import Summary", [
                        'success_count' => $this->successCount,
                        'error_count' => $this->errorCount,
                        'created_teams' => $this->createdTeams,
                        'errors' => $errors
                    ]);

                    $this->isImporting = false;
                    $this->dispatch('importComplete');

                    Notification::make()
                        ->title('Import Complete')
                        ->body($successMessage)
                        ->success()
                        ->send();

                    // Reset the form
                    $this->form->fill([]);
                    $this->csv_file = null;

                } catch (\Exception $e) {
                    DB::rollBack();
                    fclose($handle);

                    Log::error("CSV Import Error", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $this->isImporting = false;

                    Notification::make()
                        ->title('Import Error')
                        ->body('Error importing data: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            } else {
                Log::error("CSV Import: Unable to open file", ['path' => $filePath]);

                $this->isImporting = false;

                Notification::make()
                    ->title('Import Error')
                    ->body('Unable to open the CSV file')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error("CSV Import: General error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->isImporting = false;

            Notification::make()
                ->title('Import Error')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Count the number of rows in a CSV file
     *
     * @param string $filePath
     * @return int
     */
    private function countCsvRows(string $filePath): int
    {
        $rowCount = 0;
        if (($handle = fopen($filePath, "r")) !== false) {
            while (fgetcsv($handle, 1000, ",") !== false) {
                $rowCount++;
            }
            fclose($handle);
        }
        return $rowCount;
    }

    public function downloadTemplate()
    {
        // Get actual database columns
        $leadColumns = Schema::getColumnListing('leads');

        // Define headers based on available columns
        $headers = [];

        // First, add the team field
        $headers[] = 'team'; // Use 'team' as consistent field name

        // Add required fields
        $headers[] = 'plaintiff';
        $headers[] = 'defendant_first_name';
        $headers[] = 'defendant_last_name';

        // Add other common fields
        $commonFields = [
            'address', 'county', 'city', 'state', 'zip', 'case_number',
            'setout_date', 'setout_time', 'status', 'writ', 'setout',
            'lbx', 'vis_setout', 'vis_to', 'notes', 'time_on', 'setout_st',
            'setout_en', 'time_en', 'locs', 'amount_cleared', 'amount_owed'
        ];

        foreach ($commonFields as $field) {
            if (in_array($field, $leadColumns)) {
                $headers[] = $field;
            }
        }

        $sampleData = [
            [
                'Team A, Team B', // Multiple teams separated by comma
                'ABC Corp',
                'John',
                'Doe',
                '123 Main St',
                'Sample County',
                'Sample City',
                'NY',
                '10001',
                'CaseNo123',
                '2023-05-15',
                '10:00 AM',
                'New',
                'Yes',
                'Yes',
                'No',
                'Yes',
                'No',
                'Sample lead notes',
                '08:00',
                '09:00',
                '10:00',
                '11:00',
                'Location A',
                '1000',
                '500'
            ],
            [
                'Team C', // Single team
                'XYZ Inc',
                'Jane',
                'Smith',
                '456 Oak Ave',
                'Another County',
                'Another City',
                'CA',
                '90210',
                'CaseNo456',
                '2023-05-16',
                '11:30 AM',
                'In Progress',
                'No',
                'Yes',
                'Yes',
                'No',
                'Yes',
                'Another sample note',
                '09:00',
                '10:00',
                '11:00',
                '12:00',
                'Location B',
                '1500',
                '750'
            ]
        ];

        // Adjust sample data to match headers length
        foreach ($sampleData as $index => $row) {
            $sampleData[$index] = array_slice($row, 0, count($headers));

            // If row is shorter than headers, pad with empty values
            if (count($sampleData[$index]) < count($headers)) {
                $sampleData[$index] = array_pad($sampleData[$index], count($headers), '');
            }
        }

        // Generate CSV content
        $filename = 'lead_import_template.csv';
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        $file = fopen($tempFile, 'w');
        fputcsv($file, $headers);

        foreach ($sampleData as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }
}
