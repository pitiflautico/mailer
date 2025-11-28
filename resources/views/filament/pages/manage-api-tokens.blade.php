<x-filament-panels::page>
    @if ($newTokenValue)
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="h-6 w-6 text-success-500"
                    />
                    Token creado exitosamente
                </div>
            </x-slot>

            <div class="space-y-4">
                <div class="p-4 bg-warning-50 dark:bg-warning-950 rounded-lg border border-warning-200 dark:border-warning-800">
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-200 mb-2">
                        ⚠️ Importante: Copia este token ahora
                    </p>
                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        Por razones de seguridad, no podrás ver este token de nuevo. Guárdalo en un lugar seguro.
                    </p>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Tu token de API:
                    </label>
                    <div class="flex gap-2">
                        <x-filament::input.wrapper class="flex-1">
                            <input
                                type="text"
                                value="{{ $newTokenValue }}"
                                readonly
                                id="api-token-value"
                                class="fi-input block w-full border-none py-1.5 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6 bg-white/0 ps-3 pe-3"
                            />
                        </x-filament::input.wrapper>
                        <x-filament::button
                            color="gray"
                            onclick="copyToken()"
                            icon="heroicon-o-clipboard-document"
                        >
                            Copiar
                        </x-filament::button>
                    </div>
                </div>

                <x-filament::button
                    wire:click="clearNewToken"
                    color="gray"
                    size="sm"
                >
                    Entendido, he copiado el token
                </x-filament::button>
            </div>
        </x-filament::section>

        <script>
            function copyToken() {
                const tokenInput = document.getElementById('api-token-value');
                tokenInput.select();
                document.execCommand('copy');

                // Show notification
                new FilamentNotification()
                    .title('Token copiado')
                    .success()
                    .send();
            }
        </script>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            Tokens de API
        </x-slot>

        <x-slot name="description">
            Gestiona los tokens de API para tus aplicaciones. Usa estos tokens para autenticarte en el API de MailCore.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Cómo usar tu token
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Una vez que tengas tu token, úsalo en tus peticiones API:
            </p>

            <pre class="text-xs bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto"><code>curl -X POST {{ config('app.url') }}/api/send \
  -H "Authorization: Bearer TU-TOKEN-AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@tudominio.com",
    "to": "destinatario@ejemplo.com",
    "subject": "Hola",
    "body": "Contenido del email"
  }'</code></pre>

            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4">
                Para más información, consulta la <a href="{{ config('app.url') }}/docs" class="text-primary-600 hover:text-primary-700 dark:text-primary-400">documentación de API</a>.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
