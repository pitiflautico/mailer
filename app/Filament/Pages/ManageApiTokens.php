<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManageApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.manage-api-tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'Mis Tokens de API';

    protected static ?int $navigationSort = 99;

    public ?string $newTokenValue = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Auth::user()->tokens()->getQuery()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('token')
                    ->label('Token')
                    ->formatStateUsing(fn () => '••••••••••••••••')
                    ->description('El token completo solo se muestra al crearlo'),
                TextColumn::make('last_used_at')
                    ->label('Último uso')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Nunca usado'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Crear nuevo token')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('name')
                            ->label('Nombre del token')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Mi proyecto Laravel')
                            ->helperText('Un nombre descriptivo para identificar este token'),
                    ])
                    ->action(function (array $data): void {
                        $token = Auth::user()->createToken($data['name']);

                        $this->newTokenValue = $token->plainTextToken;

                        Notification::make()
                            ->success()
                            ->title('Token creado exitosamente')
                            ->body('Copia el token ahora, no podrás verlo de nuevo.')
                            ->persistent()
                            ->send();
                    })
                    ->successNotification(null),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar token')
                    ->modalDescription('¿Estás seguro? Las aplicaciones que usen este token dejarán de funcionar.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Token eliminado')
                            ->body('El token ha sido revocado exitosamente.')
                    ),
            ])
            ->emptyStateHeading('Sin tokens')
            ->emptyStateDescription('Crea tu primer token de API para comenzar a enviar correos desde tus aplicaciones.')
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Crear token')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('name')
                            ->label('Nombre del token')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Mi proyecto Laravel'),
                    ])
                    ->action(function (array $data): void {
                        $token = Auth::user()->createToken($data['name']);

                        $this->newTokenValue = $token->plainTextToken;

                        Notification::make()
                            ->success()
                            ->title('Token creado exitosamente')
                            ->body('Copia el token ahora, no podrás verlo de nuevo.')
                            ->persistent()
                            ->send();
                    }),
            ]);
    }

    public function clearNewToken(): void
    {
        $this->newTokenValue = null;
    }
}
