<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\Forms\LeadForm;
use App\Filament\Resources\LeadResource\Tables\LeadTable;
use App\Models\Lead;
use App\Traits\HasTeamScope;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
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
        return LeadForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return LeadTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLeads::route('/'),
        ];
    }
}
