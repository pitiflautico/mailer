<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ManageApiTokens extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.manage-api-tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'Mis Tokens de API';

    protected static ?int $navigationSort = 99;

    public ?string $newTokenValue = null;
    public ?string $tokenName = null;

    public function getTokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function createToken()
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
        ]);

        $token = Auth::user()->createToken($this->tokenName);

        $this->newTokenValue = $token->plainTextToken;
        $this->tokenName = null;

        Notification::make()
            ->success()
            ->title('Token creado exitosamente')
            ->body('Copia el token ahora, no podrás verlo de nuevo.')
            ->persistent()
            ->send();
    }

    public function deleteToken($tokenId)
    {
        Auth::user()->tokens()->where('id', $tokenId)->delete();

        Notification::make()
            ->success()
            ->title('Token eliminado')
            ->body('El token ha sido revocado exitosamente.')
            ->send();
    }

    public function clearNewToken(): void
    {
        $this->newTokenValue = null;
    }
}
