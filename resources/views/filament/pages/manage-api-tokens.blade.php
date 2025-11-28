<x-filament-panels::page>
    @if ($newTokenValue)
        <x-filament::section>
            <x-slot name="heading">
                Token creado exitosamente
            </x-slot>

            <div class="space-y-4">
                <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                        ⚠️ Importante: Copia este token ahora
                    </p>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                        Por razones de seguridad, no podrás ver este token de nuevo. Guárdalo en un lugar seguro.
                    </p>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Tu token de API:
                    </label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            value="{{ $newTokenValue }}"
                            readonly
                            id="api-token-value"
                            class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary-500 dark:focus:border-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 rounded-lg shadow-sm"
                        />
                        <x-filament::button
                            color="gray"
                            onclick="copyToken()"
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
                tokenInput.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(tokenInput.value);
            }
        </script>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            Crear nuevo token
        </x-slot>

        <form wire:submit="createToken" class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Nombre del token
                </label>
                <input
                    type="text"
                    wire:model="tokenName"
                    placeholder="Ej: Mi proyecto Laravel"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary-500 dark:focus:border-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 rounded-lg shadow-sm"
                />
                @error('tokenName')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <x-filament::button type="submit">
                Crear token
            </x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Tus tokens
        </x-slot>

        <div class="space-y-3">
            @forelse ($this->getTokens() as $token)
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $token->name }}
                        </h4>
                        <div class="mt-1 space-y-1">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Token: ••••••••••••••••
                            </p>
                            @if ($token->last_used_at)
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Último uso: {{ $token->last_used_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Nunca usado
                                </p>
                            @endif
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Creado: {{ $token->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    </div>
                    <x-filament::button
                        color="danger"
                        size="sm"
                        wire:click="deleteToken({{ $token->id }})"
                        wire:confirm="¿Estás seguro? Las aplicaciones que usen este token dejarán de funcionar."
                    >
                        Eliminar
                    </x-filament::button>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">
                        No tienes tokens creados. Crea uno para comenzar.
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Cómo usar tu token
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Una vez que tengas tu token, úsalo en tus peticiones API:
            </p>

            <pre class="text-xs bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto"><code>curl -X POST https://mail.nebulio.es/api/send \
  -H "Authorization: Bearer TU-TOKEN-AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@tudominio.com",
    "to": "destinatario@ejemplo.com",
    "subject": "Hola",
    "body": "Contenido del email"
  }'</code></pre>
        </div>
    </x-filament::section>
</x-filament-panels::page>
