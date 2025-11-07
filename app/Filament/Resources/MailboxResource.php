<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailboxResource\Pages;
use App\Models\Mailbox;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Buzones';

    protected static ?string $modelLabel = 'Buzón';

    protected static ?string $pluralModelLabel = 'Buzones';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Buzón')
                    ->schema([
                        Forms\Components\Select::make('domain_id')
                            ->label('Dominio')
                            ->options(Domain::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, $get) =>
                                $set('email', $get('local_part') ? $get('local_part') . '@' . Domain::find($state)?->name : '')
                            ),

                        Forms\Components\TextInput::make('local_part')
                            ->label('Usuario')
                            ->required()
                            ->placeholder('noreply, info, contacto, etc.')
                            ->maxLength(255)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $domain = Domain::find($get('domain_id'));
                                if ($domain) {
                                    $set('email', $state . '@' . $domain->name);
                                }
                            })
                            ->helperText('Parte local del email (antes del @)'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Completo')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña SMTP')
                            ->password()
                            ->required(fn ($record) => $record === null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Deje vacío para mantener la contraseña actual')
                            ->revealable()
                            ->minLength(8),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\TextInput::make('quota_mb')
                            ->label('Cuota (MB)')
                            ->numeric()
                            ->default(1024)
                            ->required()
                            ->minValue(0)
                            ->maxValue(config('mailcore.max_quota_mb', 10240)),

                        Forms\Components\TextInput::make('daily_send_limit')
                            ->label('Límite Diario de Envíos')
                            ->numeric()
                            ->default(1000)
                            ->required()
                            ->minValue(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\Toggle::make('can_send')
                            ->label('Puede Enviar')
                            ->default(true),

                        Forms\Components\Toggle::make('can_receive')
                            ->label('Puede Recibir')
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('domain.name')
                    ->label('Dominio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quota_mb')
                    ->label('Cuota')
                    ->formatStateUsing(fn ($record) =>
                        $record->used_mb . ' / ' . $record->quota_mb . ' MB'
                    )
                    ->badge()
                    ->color(fn ($record) =>
                        $record->getQuotaUsagePercentage() > 90 ? 'danger' :
                        ($record->getQuotaUsagePercentage() > 75 ? 'warning' : 'success')
                    ),

                Tables\Columns\TextColumn::make('daily_send_count')
                    ->label('Envíos Hoy')
                    ->formatStateUsing(fn ($record) =>
                        $record->daily_send_count . ' / ' . $record->daily_send_limit
                    )
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('can_send')
                    ->label('Enviar')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('can_receive')
                    ->label('Recibir')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('domain')
                    ->relationship('domain', 'name')
                    ->label('Dominio'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),

                Tables\Filters\TernaryFilter::make('can_send')
                    ->label('Puede Enviar'),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_password')
                    ->label('Cambiar Contraseña')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('Nueva Contraseña')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable(),
                    ])
                    ->action(function (Mailbox $record, array $data) {
                        $record->update([
                            'password' => $data['new_password']
                        ]);
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
            'index' => Pages\ListMailboxes::route('/'),
            'create' => Pages\CreateMailbox::route('/create'),
            'edit' => Pages\EditMailbox::route('/{record}/edit'),
        ];
    }
}
