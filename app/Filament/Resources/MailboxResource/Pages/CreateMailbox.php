<?php

namespace App\Filament\Resources\MailboxResource\Pages;

use App\Filament\Resources\MailboxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMailbox extends CreateRecord
{
    protected static string $resource = MailboxResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
