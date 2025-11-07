<?php

namespace App\Filament\Pages;

use App\Models\Mailbox;
use App\Services\MailService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SendTestEmail extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string $view = 'filament.pages.send-test-email';

    protected static ?string $navigationLabel = 'Enviar Correo de Prueba';

    protected static ?string $title = 'Enviar Correo de Prueba';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'to' => '',
            'subject' => 'Correo de prueba desde MailCore',
            'body' => "Este es un correo de prueba para verificar la configuración de SPF, DKIM y DMARC.\n\nSi recibes este correo, tu servidor está funcionando correctamente.\n\n---\n\nEste es un correo transaccional de prueba.\n\nCompany Name\n123 Main Street\nCity, State 12345\nCountry",
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración del Correo')
                    ->description('Completa el formulario para enviar un correo de prueba')
                    ->schema([
                        Select::make('mailbox_id')
                            ->label('Desde (Buzón)')
                            ->options(function () {
                                return Mailbox::whereHas('domain', function ($query) {
                                    $query->where('is_active', true);
                                })
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(function ($mailbox) {
                                    $verified = $mailbox->domain->isFullyVerified() ? '✅' : '❌';
                                    return [$mailbox->id => "{$mailbox->email} {$verified}"];
                                });
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Selecciona el buzón desde el cual enviar el correo. ✅ = Dominio verificado'),

                        TextInput::make('to')
                            ->label('Para (Destinatario)')
                            ->email()
                            ->required()
                            ->placeholder('destinatario@ejemplo.com')
                            ->helperText('💡 Prueba con mail-tester.com para verificar tu configuración')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('useMailTester')
                                    ->label('Usar Mail-Tester')
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function (Set $set) {
                                        $randomId = 'test-' . substr(md5(time()), 0, 8);
                                        $set('to', "{$randomId}@mail-tester.com");

                                        Notification::make()
                                            ->info()
                                            ->title('Mail-Tester configurado')
                                            ->body('Después de enviar, ve a https://www.mail-tester.com y haz clic en "Check your score"')
                                            ->send();
                                    })
                            ),

                        TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('body')
                            ->label('Mensaje')
                            ->required()
                            ->rows(10)
                            ->helperText('Contenido del correo electrónico'),
                    ])
                    ->columns(1),

                Section::make('📊 Servicios de Prueba Recomendados')
                    ->description('Usa estos servicios para verificar tu configuración de correo')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('test_services')
                            ->label(false)
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="space-y-3">
                                    <div class="border rounded-lg p-3">
                                        <h4 class="font-semibold mb-1">🌟 Mail-Tester.com</h4>
                                        <p class="text-sm text-gray-600 mb-2">Analiza SPF, DKIM, DMARC y da una puntuación de 0-10</p>
                                        <a href="https://www.mail-tester.com" target="_blank" class="text-primary-600 hover:underline text-sm">
                                            Visitar Mail-Tester →
                                        </a>
                                    </div>
                                    <div class="border rounded-lg p-3">
                                        <h4 class="font-semibold mb-1">🔍 MXToolbox.com</h4>
                                        <p class="text-sm text-gray-600 mb-2">Verifica registros DNS y blacklists</p>
                                        <a href="https://mxtoolbox.com/SuperTool.aspx" target="_blank" class="text-primary-600 hover:underline text-sm">
                                            Visitar MXToolbox →
                                        </a>
                                    </div>
                                    <div class="border rounded-lg p-3">
                                        <h4 class="font-semibold mb-1">🔐 DKIM Validator</h4>
                                        <p class="text-sm text-gray-600 mb-2">Verifica específicamente la firma DKIM</p>
                                        <a href="https://dkimvalidator.com" target="_blank" class="text-primary-600 hover:underline text-sm">
                                            Visitar DKIM Validator →
                                        </a>
                                    </div>
                                </div>
                            '))
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function sendTestEmail(): void
    {
        $data = $this->form->getState();

        try {
            // Get mailbox
            $mailbox = Mailbox::findOrFail($data['mailbox_id']);

            // Check if domain is verified
            if (!$mailbox->domain->isFullyVerified()) {
                Notification::make()
                    ->warning()
                    ->title('Advertencia: Dominio no verificado')
                    ->body('El dominio no está completamente verificado. El correo puede ser rechazado o marcado como spam.')
                    ->persistent()
                    ->send();
            }

            // Send email using MailService
            $mailService = app(MailService::class);
            $result = $mailService->send([
                'from' => $mailbox->email,
                'to' => $data['to'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'email_type' => 'transactional',
            ]);

            if ($result['success']) {
                Notification::make()
                    ->success()
                    ->title('¡Correo enviado exitosamente!')
                    ->body("Message ID: {$result['message_id']}")
                    ->persistent()
                    ->send();

                // Show additional info for mail-tester
                if (str_contains($data['to'], 'mail-tester.com')) {
                    Notification::make()
                        ->info()
                        ->title('Usando Mail-Tester')
                        ->body('Ve a https://www.mail-tester.com y haz clic en "Then check your score" para ver el resultado.')
                        ->persistent()
                        ->send();
                }
            } else {
                Notification::make()
                    ->danger()
                    ->title('Error al enviar el correo')
                    ->body($result['error'] ?? 'Error desconocido')
                    ->persistent()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al enviar el correo')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('send')
                ->label('Enviar Correo de Prueba')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->action('sendTestEmail'),
        ];
    }
}
