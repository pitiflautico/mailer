<?php

namespace App\Filament\Widgets;

use App\Models\SendLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSendsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SendLog::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('from_email')
                    ->label('De')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_email')
                    ->label('Para')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'delivered',
                        'info' => 'sent',
                        'danger' => fn ($state) => in_array($state, ['bounced', 'failed']),
                        'warning' => 'rejected',
                        'gray' => fn ($state) => in_array($state, ['queued', 'deferred']),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
