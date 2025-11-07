# Configuración de Nginx para Servidor Compartido

Esta guía explica cómo configurar múltiples proyectos en el mismo servidor usando Nginx.

## 📁 Estructura de Archivos

```
/etc/nginx/
├── nginx.conf                 # Configuración principal
├── sites-available/           # Configuraciones disponibles
│   ├── mailcore.conf
│   ├── another-app.conf
│   └── website.conf
└── sites-enabled/             # Configuraciones activas (symlinks)
    ├── mailcore.conf -> ../sites-available/mailcore.conf
    ├── another-app.conf -> ../sites-available/another-app.conf
    └── website.conf -> ../sites-available/website.conf
```

## 🚀 Cómo Añadir un Nuevo Proyecto

### Paso 1: Crear Configuración

Crea un nuevo archivo en `/etc/nginx/sites-available/`:

```bash
sudo nano /etc/nginx/sites-available/nuevo-proyecto.conf
```

Contenido básico para Laravel:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name nuevo.tudominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name nuevo.tudominio.com;

    root /var/www/nuevo-proyecto/public;
    index index.php index.html;

    # SSL (se configurará después con Certbot)
    ssl_certificate /etc/letsencrypt/live/nuevo.tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/nuevo.tudominio.com/privkey.pem;

    access_log /var/log/nginx/nuevo-access.log;
    error_log /var/log/nginx/nuevo-error.log;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Paso 2: Habilitar el Sitio

```bash
# Crear symlink
sudo ln -s /etc/nginx/sites-available/nuevo-proyecto.conf /etc/nginx/sites-enabled/

# Verificar configuración
sudo nginx -t

# Recargar Nginx
sudo systemctl reload nginx
```

### Paso 3: Configurar SSL

```bash
# Obtener certificado SSL con Certbot
sudo certbot --nginx -d nuevo.tudominio.com

# Certbot modificará automáticamente la configuración
```

### Paso 4: Configurar DNS

Añade un registro A en tu proveedor de DNS:

```
nuevo.tudominio.com    A    123.456.789.10
```

## 📋 Gestión de Proyectos

### Ver Sitios Activos

```bash
ls -la /etc/nginx/sites-enabled/
```

### Deshabilitar un Sitio

```bash
sudo rm /etc/nginx/sites-enabled/proyecto.conf
sudo systemctl reload nginx
```

### Habilitar un Sitio Deshabilitado

```bash
sudo ln -s /etc/nginx/sites-available/proyecto.conf /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### Verificar Sintaxis

```bash
sudo nginx -t
```

### Ver Logs de un Proyecto

```bash
# Access log
sudo tail -f /var/log/nginx/proyecto-access.log

# Error log
sudo tail -f /var/log/nginx/proyecto-error.log
```

## 🔧 Configuraciones Específicas por Tipo de Proyecto

### Laravel / PHP

```nginx
server {
    root /var/www/proyecto/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Node.js / Express

```nginx
server {
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Static HTML

```nginx
server {
    root /var/www/website/public;
    index index.html index.htm;

    location / {
        try_files $uri $uri/ =404;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

### WordPress

```nginx
server {
    root /var/www/wordpress;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WordPress security
    location ~ /\. {
        deny all;
    }

    location ~* /(?:uploads|files)/.*\.php$ {
        deny all;
    }
}
```

### API REST

```nginx
server {
    # CORS Headers
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Authorization, Content-Type" always;

    location / {
        # Handle preflight
        if ($request_method = 'OPTIONS') {
            return 204;
        }

        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🔐 Gestión de SSL con Let's Encrypt

### Obtener Certificado para un Dominio

```bash
sudo certbot --nginx -d dominio.com
```

### Obtener Certificado para Múltiples Dominios

```bash
sudo certbot --nginx -d dominio.com -d www.dominio.com -d api.dominio.com
```

### Renovar Certificados

```bash
# Test de renovación
sudo certbot renew --dry-run

# Renovación manual
sudo certbot renew

# Ver certificados instalados
sudo certbot certificates
```

### Auto-renovación

Let's Encrypt configura automáticamente la renovación. Verificar:

```bash
sudo systemctl status certbot.timer
```

## 📊 Optimización y Best Practices

### 1. Usar PHP-FPM Pools Separados (Opcional)

Para mejor aislamiento, crea pools de PHP-FPM para cada proyecto:

```bash
# Crear pool para proyecto
sudo cp /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/proyecto.conf
sudo nano /etc/php/8.2/fpm/pool.d/proyecto.conf
```

Modificar:
```ini
[proyecto]
user = proyecto-user
group = proyecto-user
listen = /run/php/php8.2-fpm-proyecto.sock
```

En Nginx:
```nginx
fastcgi_pass unix:/run/php/php8.2-fpm-proyecto.sock;
```

### 2. Rate Limiting

Proteger contra abuso:

```nginx
# En http block (/etc/nginx/nginx.conf)
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;

# En server block
location /api/ {
    limit_req zone=api_limit burst=20 nodelay;
    # ... resto de configuración
}
```

### 3. Compresión Gzip

```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml;
```

### 4. Cacheo de Assets Estáticos

```nginx
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

### 5. Security Headers

```nginx
# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### 6. Logging Separado

Cada proyecto debe tener sus propios logs:

```nginx
access_log /var/log/nginx/proyecto-access.log;
error_log /var/log/nginx/proyecto-error.log;
```

Rotar logs regularmente:

```bash
# Configuración en /etc/logrotate.d/nginx
/var/log/nginx/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
```

## 🐛 Troubleshooting

### Error: "nginx: [emerg] bind() to 0.0.0.0:80 failed"

Puerto ya en uso. Ver qué lo está usando:

```bash
sudo lsof -i :80
```

### Error: "connect() failed (111: Connection refused) while connecting to upstream"

PHP-FPM no está corriendo:

```bash
sudo systemctl status php8.2-fpm
sudo systemctl start php8.2-fpm
```

### Error 502 Bad Gateway

- Verificar que PHP-FPM esté corriendo
- Verificar permisos del socket
- Revisar logs: `sudo tail -f /var/log/nginx/error.log`

### Error 403 Forbidden

Problema de permisos:

```bash
# Verificar permisos del directorio
ls -la /var/www/proyecto

# Corregir permisos
sudo chown -R www-data:www-data /var/www/proyecto
sudo chmod -R 755 /var/www/proyecto
```

### Error 404 Not Found

- Verificar que `root` apunte al directorio correcto
- Para Laravel, debe apuntar a `/var/www/proyecto/public`
- Verificar que `try_files` esté configurado correctamente

## 📚 Recursos Adicionales

- [Nginx Documentation](https://nginx.org/en/docs/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [Nginx Config Generator](https://www.digitalocean.com/community/tools/nginx)

## 🆘 Comandos Útiles

```bash
# Test de configuración
sudo nginx -t

# Recargar configuración (sin downtime)
sudo systemctl reload nginx

# Reiniciar Nginx
sudo systemctl restart nginx

# Ver estado
sudo systemctl status nginx

# Ver configuración activa
nginx -T

# Ver versión
nginx -v

# Ver logs en tiempo real
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# Verificar sintaxis de un archivo específico
sudo nginx -t -c /etc/nginx/sites-available/proyecto.conf
```
