<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Models\Status;
use App\Traits\HasTeamScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{

    use HasTeamScope;

    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    // Group with Leads management
    protected static ?string $navigationGroup = 'Lead Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        // Use the applyTeamScope method from the HasTeamScope trait
        if (!$user || !$user->is_admin) {
            return (new static)->applyTeamScope($query);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Lead Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic & Client Information')
                            ->schema([
                                Forms\Components\Split::make([
                                    Forms\Components\Section::make('Basic Information')
                                        ->schema([
                                            Forms\Components\Select::make('rid')
                                                ->label('RID')
                                                ->searchable()
                                                ->relationship('teams', 'name')
                                                ->preload()
                                                ->multiple(),
                                            Forms\Components\Select::make('status')
                                                ->options(fn() => Status::where('type', 'lead')->pluck('name', 'name'))
                                                ->searchable(),
                                            Forms\Components\TextInput::make('case_number'),
                                        ]),
                                    Forms\Components\Section::make('Client Information')
                                        ->schema([
                                            Forms\Components\TextInput::make('plaintiff')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('defendant_first_name')
                                                ->label('Defendant First Name')
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('defendant_last_name')
                                                ->label('Defendant Last Name')
                                                ->maxLength(255),
                                        ]),
                                ])->from('md'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Address Details')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('county')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('city')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('state')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('zip')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Setout Information')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('setout_date'),
                                        Forms\Components\TimePicker::make('setout_time'),
                                        Forms\Components\Select::make('setout')
                                            ->options(fn() => Status::where('type', 'setout')->pluck('name', 'name'))
                                            ->searchable(),
                                        Forms\Components\TimePicker::make('time_on'),
                                        Forms\Components\TimePicker::make('time_en'),
                                        Forms\Components\TextInput::make('setout_st')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('setout_en')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('vis_setout')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('vis_to')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Financial Details')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('amount_owed')
                                            ->numeric()
                                            ->prefix('$'),
                                        Forms\Components\Placeholder::make('total_cleared')
                                            ->label('Amount Cleared')
                                            ->content(function ($record) {
                                                if (!$record) return '$0.00';
                                                return '$' . number_format($record->leadAmounts()->sum('amount_cleared'), 2);
                                            }),
                                        Forms\Components\Placeholder::make('total_remaining')
                                            ->label('Amount Remaining')
                                            ->content(function ($record) {
                                                if (!$record) return '$0.00';
                                                $remaining = $record->amount_owed - $record->leadAmounts()->sum('amount_cleared');
                                                return '$' . number_format($remaining, 2);
                                            }),
                                        Forms\Components\Select::make('writ')
                                            ->options(fn() => Status::where('type', 'writ')->pluck('name', 'name'))
                                            ->searchable(),
                                        Forms\Components\TextInput::make('lbx')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Section::make('Payment History')
                                    ->schema([
                                        Forms\Components\Repeater::make('leadAmounts')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\TextInput::make('amount_cleared')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->required(),
                                                Forms\Components\DatePicker::make('payment_date')
                                                    ->label('Payment Date')
                                                    ->required()
                                                    ->default(now()),
                                            ])
                                            ->defaultItems(0)
                                            ->reorderable(false)
                                            ->columnSpanFull()
                                            ->addActionLabel('Add Payment')
                                            ->label('Payments'),
                                    ])->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Additional Information')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('locs')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('notes')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->contained() // This removes the scrollbar
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teams.name')
                    ->label('REF')
                    ->listWithLineBreaks()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return $record->teams->pluck('name')->join(', ');
                    }),
                Tables\Columns\TextColumn::make('plaintiff')
                    ->label('Plaintiff')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('defendant_first_name')
                    ->label('Tenant')
                    ->formatStateUsing(fn($record) => $record->defendant_first_name . ' ' . $record->defendant_last_name)
                    ->searchable(['defendant_first_name', 'defendant_last_name'])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('county')
                    ->label('County')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->label('State')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('zip')
                    ->label('Zip')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('case_number')
                    ->label('Case Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('setout_date')
                    ->label('Setout Date')
                    ->date()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('setout_time')
                    ->label('Setout Time')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lbx')
                    ->label('LBX')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vis_setout')
                    ->label('Visual-Setout')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vis_to')
                    ->label('Visual-TO')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('time_on')
                    ->label('Time Start')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('setout_st')
                    ->label('Setout Start')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('setout_en')
                    ->label('Setout End')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('time_en')
                    ->label('Time End')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('locs')
                    ->label('LOCS')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teams')
                    ->relationship('teams', 'name')
                    ->multiple()
                    ->preload()
                    ->label('REF'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn() => Status::where('type', 'lead')->pluck('name', 'name'))
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('county')
                    ->options(fn() => Lead::distinct()->pluck('county', 'county')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn() => Lead::distinct()->pluck('city', 'city')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('state')
                    ->options(fn() => Lead::distinct()->pluck('state', 'state')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('writ')
                    ->options(fn() => Status::where('type', 'writ')->pluck('name', 'name'))
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('setout')
                    ->options(fn() => Status::where('type', 'setout')->pluck('name', 'name'))
                    ->multiple()
                    ->preload(),
                Tables\Filters\Filter::make('has_notes')
                    ->label('Has Notes')
                    ->query(fn($query) => $query->whereNotNull('notes')->where('notes', '!=', ''))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLeads::route('/'),
        ];
    }
}
