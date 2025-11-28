<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ManageApiTokens extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.manage-api-tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'Mis Tokens de API';

    protected static ?int $navigationSort = 99;

    public ?string $newTokenValue = null;
    public string $tokenName = '';

    public function mount(): void
    {
        $this->tokenName = '';
        $this->newTokenValue = null;
    }

    public function getTokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function createToken()
    {
        if (empty($this->tokenName)) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('El nombre del token es requerido.')
                ->send();
            return;
        }

        $token = Auth::user()->createToken($this->tokenName);

        $this->newTokenValue = $token->plainTextToken;
        $this->tokenName = '';

        Notification::make()
            ->success()
            ->title('Token creado exitosamente')
            ->body('Copia el token ahora, no podrás verlo de nuevo.')
            ->persistent()
            ->send();
    }

    public function deleteToken($tokenId)
    {
        try {
            Auth::user()->tokens()->where('id', $tokenId)->delete();

            Notification::make()
                ->success()
                ->title('Token eliminado')
                ->body('El token ha sido revocado exitosamente.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se pudo eliminar el token.')
                ->send();
        }
    }

    public function clearNewToken(): void
    {
        $this->newTokenValue = null;
    }
}
