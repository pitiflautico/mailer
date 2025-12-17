@php
    $hostname = config('mailcore.hostname', 'mail.tudominio.com');
    $email = $getRecord()?->email ?? 'tu-email@dominio.com';
@endphp

<div class="mt-4 space-y-4">
    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Ejemplos de configuración:
    </div>

    {{-- Laravel .env --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Laravel (.env)</span>
            <button
                type="button"
                x-data
                x-on:click="navigator.clipboard.writeText($refs.laravelCode.textContent); $tooltip('Copiado!')"
                class="text-xs text-gray-500 hover:text-primary-600"
            >
                Copiar
            </button>
        </div>
        <pre x-ref="laravelCode" class="p-3 text-xs bg-gray-100 dark:bg-gray-800 rounded-lg overflow-x-auto font-mono">MAIL_MAILER=smtp
MAIL_HOST={{ $hostname }}
MAIL_PORT=587
MAIL_USERNAME={{ $email }}
MAIL_PASSWORD=tu_contraseña_aqui
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="{{ $email }}"
MAIL_FROM_NAME="${APP_NAME}"</pre>
    </div>

    {{-- PHP/PHPMailer --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">PHPMailer</span>
            <button
                type="button"
                x-data
                x-on:click="navigator.clipboard.writeText($refs.phpCode.textContent); $tooltip('Copiado!')"
                class="text-xs text-gray-500 hover:text-primary-600"
            >
                Copiar
            </button>
        </div>
        <pre x-ref="phpCode" class="p-3 text-xs bg-gray-100 dark:bg-gray-800 rounded-lg overflow-x-auto font-mono">$mail->isSMTP();
$mail->Host       = '{{ $hostname }}';
$mail->SMTPAuth   = true;
$mail->Username   = '{{ $email }}';
$mail->Password   = 'tu_contraseña_aqui';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;</pre>
    </div>

    {{-- Node.js/Nodemailer --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Node.js (Nodemailer)</span>
            <button
                type="button"
                x-data
                x-on:click="navigator.clipboard.writeText($refs.nodeCode.textContent); $tooltip('Copiado!')"
                class="text-xs text-gray-500 hover:text-primary-600"
            >
                Copiar
            </button>
        </div>
        <pre x-ref="nodeCode" class="p-3 text-xs bg-gray-100 dark:bg-gray-800 rounded-lg overflow-x-auto font-mono">const transporter = nodemailer.createTransport({
  host: '{{ $hostname }}',
  port: 587,
  secure: false, // true for 465, false for other ports
  auth: {
    user: '{{ $email }}',
    pass: 'tu_contraseña_aqui'
  }
});</pre>
    </div>
</div>
