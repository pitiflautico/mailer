<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DomainResource\Pages;
use App\Models\Domain;
use App\Services\DkimService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Dominios';

    protected static ?string $modelLabel = 'Dominio';

    protected static ?string $pluralModelLabel = 'Dominios';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Dominio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Dominio')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('ejemplo.com')
                            ->helperText('Ingrese el dominio sin www')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('dkim_selector')
                            ->label('Selector DKIM')
                            ->default('default')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Normalmente se usa "default"'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Claves DKIM')
                    ->schema([
                        Forms\Components\Textarea::make('dkim_private_key')
                            ->label('Clave Privada DKIM')
                            ->rows(5)
                            ->columnSpanFull()
                            ->disabled(),

                        Forms\Components\Textarea::make('dkim_public_key')
                            ->label('Clave Pública DKIM')
                            ->rows(5)
                            ->columnSpanFull()
                            ->disabled()
                            ->helperText('Esta clave debe agregarse a sus registros DNS'),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record?->dkim_public_key),

                Forms\Components\Section::make('Estado de Verificación')
                    ->schema([
                        Forms\Components\Placeholder::make('spf_verified')
                            ->label('SPF Verificado')
                            ->content(fn ($record) => $record?->spf_verified ? '✅ Verificado' : '❌ No verificado'),

                        Forms\Components\Placeholder::make('dkim_verified')
                            ->label('DKIM Verificado')
                            ->content(fn ($record) => $record?->dkim_verified ? '✅ Verificado' : '❌ No verificado'),

                        Forms\Components\Placeholder::make('dmarc_verified')
                            ->label('DMARC Verificado')
                            ->content(fn ($record) => $record?->dmarc_verified ? '✅ Verificado' : '❌ No verificado'),

                        Forms\Components\Placeholder::make('last_verification_at')
                            ->label('Última Verificación')
                            ->content(fn ($record) => $record?->last_verification_at?->diffForHumans() ?? 'Nunca'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Dominio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('spf_verified')
                    ->label('SPF')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('dkim_verified')
                    ->label('DKIM')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('dmarc_verified')
                    ->label('DMARC')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('active_mailboxes_count')
                    ->label('Buzones')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),

                Tables\Filters\Filter::make('verified')
                    ->label('Completamente Verificado')
                    ->query(fn ($query) => $query->where('spf_verified', true)
                        ->where('dkim_verified', true)
                        ->where('dmarc_verified', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('generate_dkim')
                    ->label('Generar DKIM')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Domain $record) {
                        app(DkimService::class)->generateKeys($record);

                        Notification::make()
                            ->success()
                            ->title('Claves DKIM generadas')
                            ->body('Las claves DKIM han sido generadas exitosamente.')
                            ->send();
                    })
                    ->visible(fn (Domain $record) => !$record->dkim_public_key),

                Tables\Actions\Action::make('verify')
                    ->label('Verificar DNS')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->action(function (Domain $record) {
                        app(DkimService::class)->verifyDnsRecords($record);

                        Notification::make()
                            ->success()
                            ->title('Verificación completada')
                            ->body('Los registros DNS han sido verificados.')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
