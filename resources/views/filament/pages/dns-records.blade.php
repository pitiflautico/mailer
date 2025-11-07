<div class="space-y-6">
    @php
        $serverIp = config('mailcore.server_ip', request()->server('SERVER_ADDR', 'YOUR_SERVER_IP'));
        $mailHostname = config('mailcore.mail_hostname', "mail.{$domain->name}");
        $dkimSelector = $domain->dkim_selector ?? config('mailcore.dkim_selector', 'default');

        // Get DKIM public key if exists
        $dkimPublicKey = null;
        $dkimKeyExists = false;

        // Check if DKIM keys exist (either public key or private key means it was generated)
        if ($domain->dkim_public_key || $domain->dkim_private_key) {
            $dkimKeyExists = true;

            if ($domain->dkim_public_key) {
                // Extract p= value from DKIM public key
                if (preg_match('/p=([A-Za-z0-9+\/=]+)/', $domain->dkim_public_key, $matches)) {
                    $dkimPublicKey = "v=DKIM1; k=rsa; p={$matches[1]}";
                } else {
                    $dkimPublicKey = $domain->dkim_public_key;
                }
            } else {
                // Try to read from file system
                $dkimPath = config('mailcore.dkim_path', '/etc/opendkim/keys');
                $dnsFile = "{$dkimPath}/{$domain->name}/{$dkimSelector}.dns";

                if (file_exists($dnsFile)) {
                    $content = file_get_contents($dnsFile);
                    // Extract the public key value
                    if (preg_match('/\((.*?)\)/s', $content, $matches)) {
                        // Clean up the key
                        $key = str_replace(['"', ' ', "\t", "\n", "\r"], '', $matches[1]);
                        $dkimPublicKey = "v=DKIM1; k=rsa; p={$key}";
                    }
                }
            }
        }
    @endphp

    <!-- Instructions -->
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">📝 Instrucciones</h3>
        <p class="text-sm text-blue-800 dark:text-blue-200 mb-2">
            Añade los siguientes registros DNS en tu proveedor de dominio (GoDaddy, Namecheap, Cloudflare, etc.):
        </p>
        <ol class="text-sm text-blue-800 dark:text-blue-200 space-y-1 list-decimal list-inside">
            <li>Copia cada registro exactamente como se muestra</li>
            <li>Espera 5-30 minutos para la propagación DNS</li>
            <li>Haz clic en "Verificar DNS" para comprobar la configuración</li>
        </ol>
    </div>

    <!-- A Record -->
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
            <span class="text-2xl">📍</span> A Record (Mail Server)
        </h3>
        <div class="space-y-2">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">A</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">mail.{{ $domain->name }}</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Value:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $serverIp }}</code>
                </div>
            </div>
        </div>
    </div>

    <!-- MX Record -->
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
            <span class="text-2xl">📬</span> MX Record (Mail Exchange)
        </h3>
        <div class="space-y-2">
            <div class="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">MX</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $domain->name }}</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Value:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">mail.{{ $domain->name }}</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Priority:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">10</code>
                </div>
            </div>
        </div>
    </div>

    <!-- SPF Record -->
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
            <span class="text-2xl">📧</span> SPF Record (Sender Policy Framework)
            @if($domain->spf_verified)
                <span class="text-green-600 dark:text-green-400 text-sm">✓ Verificado</span>
            @else
                <span class="text-red-600 dark:text-red-400 text-sm">✗ No verificado</span>
            @endif
        </h3>
        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">TXT</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $domain->name }}</code>
                </div>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">Value:</span>
                <div class="mt-1">
                    <code class="block px-3 py-2 bg-white dark:bg-gray-900 rounded text-xs break-all">v=spf1 ip4:{{ $serverIp }} a:mail.{{ $domain->name }} -all</code>
                </div>
            </div>
        </div>
    </div>

    <!-- DKIM Record -->
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
            <span class="text-2xl">🔑</span> DKIM Record (Domain Keys Identified Mail)
            @if($domain->dkim_verified)
                <span class="text-green-600 dark:text-green-400 text-sm">✓ Verificado</span>
            @else
                <span class="text-red-600 dark:text-red-400 text-sm">✗ No verificado</span>
            @endif
        </h3>
        <div class="space-y-2">
            @if($dkimKeyExists)
                @if($dkimPublicKey)
                    <!-- DKIM key found and can be displayed -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                            <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">TXT</code>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                            <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $dkimSelector }}._domainkey.{{ $domain->name }}</code>
                        </div>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">Value:</span>
                        <div class="mt-1">
                            <code class="block px-3 py-2 bg-white dark:bg-gray-900 rounded text-xs break-all">{{ $dkimPublicKey }}</code>
                        </div>
                    </div>
                @else
                    <!-- DKIM key exists but couldn't be extracted -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-800">
                        <p class="text-sm text-blue-800 dark:text-blue-200 mb-2">
                            ✓ Las claves DKIM ya están generadas.
                        </p>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                            Para ver la clave pública y añadirla al DNS, ejecuta:
                        </p>
                        <code class="block px-3 py-2 bg-white dark:bg-gray-900 rounded text-xs">
                            php artisan mailcore:show-dns {{ $domain->name }}
                        </code>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm mt-3">
                        <div>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                            <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">TXT</code>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                            <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $dkimSelector }}._domainkey.{{ $domain->name }}</code>
                        </div>
                    </div>
                @endif
            @else
                <!-- DKIM key not generated yet -->
                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded border border-yellow-200 dark:border-yellow-800">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        ⚠️ La clave DKIM aún no ha sido generada.
                    </p>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-2">
                        Haz clic en el botón "Generar DKIM" para crear las claves.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- DMARC Record -->
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
            <span class="text-2xl">🛡️</span> DMARC Record (Domain-based Message Authentication)
            @if($domain->dmarc_verified)
                <span class="text-green-600 dark:text-green-400 text-sm">✓ Verificado</span>
            @else
                <span class="text-red-600 dark:text-red-400 text-sm">✗ No verificado</span>
            @endif
        </h3>
        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">TXT</code>
                </div>
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                    <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded">_dmarc.{{ $domain->name }}</code>
                </div>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">Value:</span>
                <div class="mt-1">
                    <code class="block px-3 py-2 bg-white dark:bg-gray-900 rounded text-xs break-all">v=DMARC1; p=quarantine; rua=mailto:postmaster@{{ $domain->name }}; ruf=mailto:postmaster@{{ $domain->name }}; fo=1</code>
                </div>
            </div>
        </div>
    </div>

    <!-- PTR Record Warning -->
    <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
        <h3 class="font-semibold text-orange-900 dark:text-orange-100 mb-2">🔄 PTR Record (Reverse DNS)</h3>
        <p class="text-sm text-orange-800 dark:text-orange-200 mb-2">
            El registro PTR debe configurarse con tu proveedor de hosting (no en tu proveedor de dominio):
        </p>
        <div class="text-sm">
            <code class="px-2 py-1 bg-white dark:bg-gray-900 rounded">{{ $serverIp }}</code> →
            <code class="px-2 py-1 bg-white dark:bg-gray-900 rounded">mail.{{ $domain->name }}</code>
        </div>
        <p class="text-xs text-orange-700 dark:text-orange-300 mt-2">
            Contacta con tu proveedor de hosting (AWS, DigitalOcean, Hetzner, etc.) para configurar el PTR.
        </p>
    </div>

    <!-- Test Commands -->
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-300 dark:border-gray-600">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">🔧 Comandos de Verificación</h3>
        <div class="space-y-2 text-sm">
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">SPF:</span>
                <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded text-xs">dig TXT {{ $domain->name }} +short</code>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">DKIM:</span>
                <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded text-xs">dig TXT {{ $dkimSelector }}._domainkey.{{ $domain->name }} +short</code>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">DMARC:</span>
                <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded text-xs">dig TXT _dmarc.{{ $domain->name }} +short</code>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">PTR:</span>
                <code class="ml-2 px-2 py-1 bg-white dark:bg-gray-900 rounded text-xs">dig -x {{ $serverIp }} +short</code>
            </div>
        </div>
    </div>
</div>
