<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BounceResource\Pages;
use App\Models\Bounce;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BounceResource extends Resource
{
    protected static ?string $model = Bounce::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Rebotes';

    protected static ?string $modelLabel = 'Rebote';

    protected static ?string $pluralModelLabel = 'Rebotes';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Rebote')
                    ->schema([
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Email Destinatario')
                            ->disabled(),

                        Forms\Components\TextInput::make('message_id')
                            ->label('ID de Mensaje')
                            ->disabled(),

                        Forms\Components\Select::make('bounce_type')
                            ->label('Tipo de Rebote')
                            ->options([
                                'hard' => 'Hard',
                                'soft' => 'Soft',
                                'transient' => 'Transitorio',
                                'permanent' => 'Permanente',
                                'unknown' => 'Desconocido',
                            ])
                            ->disabled(),

                        Forms\Components\Select::make('bounce_category')
                            ->label('Categoría')
                            ->options([
                                'invalid_address' => 'Dirección Inválida',
                                'mailbox_full' => 'Buzón Lleno',
                                'spam_related' => 'Relacionado con Spam',
                                'dns_error' => 'Error DNS',
                                'connection_error' => 'Error de Conexión',
                                'policy_related' => 'Relacionado con Políticas',
                                'content_rejected' => 'Contenido Rechazado',
                                'other' => 'Otro',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detalles SMTP')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_code')
                            ->label('Código SMTP')
                            ->disabled(),

                        Forms\Components\Textarea::make('smtp_response')
                            ->label('Respuesta SMTP')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('diagnostic_code')
                            ->label('Código Diagnóstico')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Supresión')
                    ->schema([
                        Forms\Components\Toggle::make('is_suppressed')
                            ->label('Suprimido'),

                        Forms\Components\DateTimePicker::make('suppressed_until')
                            ->label('Suprimido Hasta')
                            ->visible(fn ($record) => $record->is_suppressed),
                    ]),

                Forms\Components\Section::make('Mensaje Original')
                    ->schema([
                        Forms\Components\Textarea::make('raw_message')
                            ->label('Mensaje Completo')
                            ->disabled()
                            ->rows(10)
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record->raw_message),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Destinatario')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('bounce_type')
                    ->label('Tipo')
                    ->colors([
                        'danger' => fn ($state) => in_array($state, ['hard', 'permanent']),
                        'warning' => fn ($state) => in_array($state, ['soft', 'transient']),
                        'gray' => 'unknown',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'hard' => 'Hard',
                        'soft' => 'Soft',
                        'transient' => 'Transitorio',
                        'permanent' => 'Permanente',
                        default => 'Desconocido',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('bounce_category')
                    ->label('Categoría')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'invalid_address' => 'Dirección Inválida',
                        'mailbox_full' => 'Buzón Lleno',
                        'spam_related' => 'Spam',
                        'dns_error' => 'Error DNS',
                        'connection_error' => 'Error Conexión',
                        'policy_related' => 'Política',
                        'content_rejected' => 'Contenido Rechazado',
                        default => 'Otro',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('smtp_code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_suppressed')
                    ->label('Suprimido')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bounce_type')
                    ->label('Tipo')
                    ->options([
                        'hard' => 'Hard',
                        'soft' => 'Soft',
                        'transient' => 'Transitorio',
                        'permanent' => 'Permanente',
                        'unknown' => 'Desconocido',
                    ]),

                Tables\Filters\SelectFilter::make('bounce_category')
                    ->label('Categoría')
                    ->options([
                        'invalid_address' => 'Dirección Inválida',
                        'mailbox_full' => 'Buzón Lleno',
                        'spam_related' => 'Relacionado con Spam',
                        'dns_error' => 'Error DNS',
                        'connection_error' => 'Error de Conexión',
                        'policy_related' => 'Relacionado con Políticas',
                        'content_rejected' => 'Contenido Rechazado',
                        'other' => 'Otro',
                    ]),

                Tables\Filters\TernaryFilter::make('is_suppressed')
                    ->label('Suprimido'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('suppress')
                    ->label('Suprimir')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Bounce $record) => $record->update(['is_suppressed' => true]))
                    ->visible(fn (Bounce $record) => !$record->is_suppressed),

                Tables\Actions\Action::make('unsuppress')
                    ->label('Quitar Supresión')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Bounce $record) => $record->update([
                        'is_suppressed' => false,
                        'suppressed_until' => null
                    ]))
                    ->visible(fn (Bounce $record) => $record->is_suppressed),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBounces::route('/'),
            'view' => Pages\ViewBounce::route('/{record}'),
        ];
    }
}
