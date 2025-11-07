<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SendLogResource\Pages;
use App\Models\SendLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SendLogResource extends Resource
{
    protected static ?string $model = SendLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Envíos';

    protected static ?string $modelLabel = 'Envío';

    protected static ?string $pluralModelLabel = 'Envíos';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Envío')
                    ->schema([
                        Forms\Components\TextInput::make('message_id')
                            ->label('ID de Mensaje')
                            ->disabled(),

                        Forms\Components\TextInput::make('from_email')
                            ->label('De')
                            ->disabled(),

                        Forms\Components\TextInput::make('to_email')
                            ->label('Para')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body_preview')
                            ->label('Vista Previa del Cuerpo')
                            ->disabled()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('Estado')
                            ->content(fn ($record) => ucfirst($record->status ?? 'N/A')),

                        Forms\Components\Placeholder::make('smtp_code')
                            ->label('Código SMTP')
                            ->content(fn ($record) => $record->smtp_code ?? 'N/A'),

                        Forms\Components\Textarea::make('smtp_response')
                            ->label('Respuesta SMTP')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Mensaje de Error')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->error_message),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Fechas')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Creado')
                            ->content(fn ($record) => $record->created_at?->format('d/m/Y H:i:s') ?? 'N/A'),

                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Enviado')
                            ->content(fn ($record) => $record->sent_at?->format('d/m/Y H:i:s') ?? 'N/A'),

                        Forms\Components\Placeholder::make('delivered_at')
                            ->label('Entregado')
                            ->content(fn ($record) => $record->delivered_at?->format('d/m/Y H:i:s') ?? 'N/A'),

                        Forms\Components\Placeholder::make('bounced_at')
                            ->label('Rebotado')
                            ->content(fn ($record) => $record->bounced_at?->format('d/m/Y H:i:s') ?? 'N/A'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_email')
                    ->label('De')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('to_email')
                    ->label('Para')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'delivered',
                        'info' => 'sent',
                        'danger' => fn ($state) => in_array($state, ['bounced', 'failed']),
                        'warning' => 'rejected',
                        'gray' => fn ($state) => in_array($state, ['queued', 'deferred']),
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('domain.name')
                    ->label('Dominio')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Intentos')
                    ->badge()
                    ->color(fn ($state) => $state > 1 ? 'warning' : 'gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'queued' => 'En Cola',
                        'sent' => 'Enviado',
                        'delivered' => 'Entregado',
                        'bounced' => 'Rebotado',
                        'failed' => 'Fallido',
                        'rejected' => 'Rechazado',
                        'deferred' => 'Diferido',
                    ]),

                Tables\Filters\SelectFilter::make('domain')
                    ->relationship('domain', 'name')
                    ->label('Dominio'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_bounce')
                    ->label('Ver Rebote')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->url(fn (SendLog $record) => $record->bounce
                        ? BounceResource::getUrl('view', ['record' => $record->bounce])
                        : null)
                    ->visible(fn (SendLog $record) => $record->bounce !== null),
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
            'index' => Pages\ListSendLogs::route('/'),
            'view' => Pages\ViewSendLog::route('/{record}'),
        ];
    }
}
