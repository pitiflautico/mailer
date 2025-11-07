<?php

namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use App\Services\DkimService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;

    protected function afterCreate(): void
    {
        // Auto-generate DKIM keys for new domain
        app(DkimService::class)->generateKeys($this->record);

        Notification::make()
            ->success()
            ->title('Dominio creado')
            ->body('Las claves DKIM han sido generadas automáticamente. Por favor, configure sus registros DNS.')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
