<x-filament-panels::page>
    @if ($newTokenValue)
        <div class="mb-6">
            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                    ⚠️ Importante: Copia este token ahora
                </p>
                <p class="text-sm text-yellow-700 dark:text-yellow-300 mb-4">
                    Por razones de seguridad, no podrás ver este token de nuevo. Guárdalo en un lugar seguro.
                </p>

                <div class="mb-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Tu token de API:
                    </label>
                </div>
                <div class="flex gap-2">
                    <input
                        type="text"
                        value="{{ $newTokenValue }}"
                        readonly
                        id="api-token-value"
                        class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary-500 dark:focus:border-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 rounded-lg shadow-sm"
                    />
                    <button
                        type="button"
                        onclick="copyToken()"
                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                    >
                        Copiar
                    </button>
                </div>

                <button
                    type="button"
                    wire:click="clearNewToken"
                    class="mt-4 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm"
                >
                    Entendido, he copiado el token
                </button>
            </div>
        </div>

        <script>
            function copyToken() {
                const tokenInput = document.getElementById('api-token-value');
                tokenInput.select();
                tokenInput.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(tokenInput.value);
                alert('Token copiado al portapapeles');
            }
        </script>
    @endif

    <div class="mb-6 p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Crear nuevo token</h3>

        <form wire:submit.prevent="createToken" class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
                    Nombre del token
                </label>
                <input
                    type="text"
                    wire:model="tokenName"
                    placeholder="Ej: Mi proyecto Laravel"
                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary-500 dark:focus:border-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 rounded-lg shadow-sm"
                />
            </div>

            <button
                type="submit"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"
            >
                Crear token
            </button>
        </form>
    </div>

    <div class="mb-6 p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Tus tokens</h3>

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
                    <button
                        type="button"
                        wire:click="deleteToken({{ $token->id }})"
                        onclick="return confirm('¿Estás seguro? Las aplicaciones que usen este token dejarán de funcionar.')"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-sm"
                    >
                        Eliminar
                    </button>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">
                        No tienes tokens creados. Crea uno para comenzar.
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Cómo usar tu token</h3>

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
    </div>
</x-filament-panels::page>
