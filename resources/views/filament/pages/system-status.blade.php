<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Services Status -->
        <x-filament::section>
            <x-slot name="heading">
                Estado de Servicios
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($this->getViewData()['services'] as $service)
                    <div class="border rounded-lg p-4 {{ $service['status'] === 'running' || $service['status'] === 'open' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold text-lg">{{ $service['name'] }}</h3>
                            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $service['status'] === 'running' || $service['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($service['status']) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600">{{ $service['description'] }}</p>
                        @if($service['command'])
                            <div class="mt-2 p-2 bg-gray-100 rounded text-xs font-mono">
                                {{ $service['command'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <!-- SMTP Connection Test -->
        <x-filament::section>
            <x-slot name="heading">
                Prueba de Conexión SMTP
            </x-slot>
            
            <div class="p-4 rounded-lg {{ $this->getViewData()['smtpTest']['success'] ? 'bg-green-50' : 'bg-red-50' }}">
                <p class="{{ $this->getViewData()['smtpTest']['success'] ? 'text-green-700' : 'text-red-700' }}">
                    {{ $this->getViewData()['smtpTest']['message'] }}
                </p>
            </div>
        </x-filament::section>

        <!-- Mail Configuration -->
        <x-filament::section>
            <x-slot name="heading">
                Configuración de Correo
            </x-slot>
            
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($this->getViewData()['mailConfig'] as $key => $value)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ $key }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <!-- System Information -->
        <x-filament::section>
            <x-slot name="heading">
                Información del Sistema
            </x-slot>
            
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($this->getViewData()['systemInfo'] as $key => $value)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ $key }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ is_bool($value) ? ($value ? 'Sí' : 'No') : $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <!-- Recent Send Logs -->
        <x-filament::section>
            <x-slot name="heading">
                Últimos Envíos (10 más recientes)
            </x-slot>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">De</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Para</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Asunto</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->getViewData()['recentLogs'] as $log)
                            <tr>
                                <td class="px-3 py-2 text-sm">{{ $log['id'] }}</td>
                                <td class="px-3 py-2 text-sm">{{ $log['from'] }}</td>
                                <td class="px-3 py-2 text-sm">{{ $log['to'] }}</td>
                                <td class="px-3 py-2 text-sm">{{ Str::limit($log['subject'], 30) }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs {{ $log['status'] === 'sent' ? 'bg-green-100 text-green-800' : ($log['status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ $log['status'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm">{{ $log['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-sm text-gray-500 text-center">
                                    No hay registros de envío
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
