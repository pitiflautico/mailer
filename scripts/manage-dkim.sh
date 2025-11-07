#!/bin/bash

################################################################################
# MailCore - DKIM Management Script
#
# Este script ayuda a gestionar claves DKIM para dominios en MailCore
#
# Uso:
#   ./manage-dkim.sh generate <dominio>    # Generar nueva clave DKIM
#   ./manage-dkim.sh show <dominio>        # Mostrar clave pública DKIM
#   ./manage-dkim.sh list                  # Listar todos los dominios
#   ./manage-dkim.sh test <dominio>        # Verificar configuración DKIM
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DKIM_PATH="/etc/opendkim/keys"
DKIM_SELECTOR="default"
KEY_TABLE="/etc/opendkim/KeyTable"
SIGNING_TABLE="/etc/opendkim/SigningTable"

# Functions
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "Este script debe ejecutarse como root o con sudo"
        exit 1
    fi
}

# Generate DKIM key for domain
generate_dkim() {
    local DOMAIN=$1

    if [ -z "$DOMAIN" ]; then
        print_error "Debes especificar un dominio"
        echo "Uso: $0 generate <dominio>"
        exit 1
    fi

    print_info "Generando clave DKIM para: $DOMAIN"

    # Create domain directory
    DOMAIN_PATH="$DKIM_PATH/$DOMAIN"
    mkdir -p "$DOMAIN_PATH"

    # Generate key if not exists
    if [ -f "$DOMAIN_PATH/$DKIM_SELECTOR.private" ]; then
        print_warning "La clave DKIM ya existe para $DOMAIN"
        read -p "¿Sobrescribir? (s/n): " OVERWRITE
        if [ "$OVERWRITE" != "s" ] && [ "$OVERWRITE" != "S" ]; then
            print_info "Operación cancelada"
            exit 0
        fi
    fi

    # Generate key pair
    print_info "Generando par de claves RSA 2048..."
    opendkim-genkey -b 2048 -s "$DKIM_SELECTOR" -d "$DOMAIN" -D "$DOMAIN_PATH"

    # Rename files
    mv "$DOMAIN_PATH/$DKIM_SELECTOR.private" "$DOMAIN_PATH/$DKIM_SELECTOR.key"
    mv "$DOMAIN_PATH/$DKIM_SELECTOR.txt" "$DOMAIN_PATH/$DKIM_SELECTOR.dns"

    # Set permissions
    chown -R opendkim:opendkim "$DOMAIN_PATH"
    chmod 600 "$DOMAIN_PATH/$DKIM_SELECTOR.key"

    # Update KeyTable
    print_info "Actualizando KeyTable..."

    # Remove old entry if exists
    sed -i "/^$DKIM_SELECTOR._domainkey.$DOMAIN/d" "$KEY_TABLE"

    # Add new entry
    echo "$DKIM_SELECTOR._domainkey.$DOMAIN $DOMAIN:$DKIM_SELECTOR:$DOMAIN_PATH/$DKIM_SELECTOR.key" >> "$KEY_TABLE"

    # Update SigningTable
    print_info "Actualizando SigningTable..."

    # Remove old entry if exists
    sed -i "/^*@$DOMAIN/d" "$SIGNING_TABLE"

    # Add new entry
    echo "*@$DOMAIN $DKIM_SELECTOR._domainkey.$DOMAIN" >> "$SIGNING_TABLE"

    # Restart OpenDKIM
    print_info "Reiniciando OpenDKIM..."
    systemctl restart opendkim

    print_success "Clave DKIM generada exitosamente para $DOMAIN"
    echo ""

    # Show public key
    show_dkim "$DOMAIN"
}

# Show DKIM public key
show_dkim() {
    local DOMAIN=$1

    if [ -z "$DOMAIN" ]; then
        print_error "Debes especificar un dominio"
        echo "Uso: $0 show <dominio>"
        exit 1
    fi

    DOMAIN_PATH="$DKIM_PATH/$DOMAIN"
    DNS_FILE="$DOMAIN_PATH/$DKIM_SELECTOR.dns"

    if [ ! -f "$DNS_FILE" ]; then
        print_error "No se encontró clave DKIM para $DOMAIN"
        print_info "Genera una clave con: $0 generate $DOMAIN"
        exit 1
    fi

    echo ""
    print_info "Clave DKIM para: $DOMAIN"
    echo ""
    echo "========================================================================="
    echo "Añade este registro TXT en tu DNS:"
    echo "========================================================================="
    echo ""

    # Extract and format the DNS record
    cat "$DNS_FILE" | sed 's/[()]//g' | tr -d '\n' | sed 's/  */ /g'
    echo ""
    echo ""

    # Show simplified version
    echo "========================================================================="
    echo "Versión simplificada (algunos DNS requieren este formato):"
    echo "========================================================================="
    echo ""
    echo "Nombre: $DKIM_SELECTOR._domainkey"
    echo "Tipo: TXT"
    echo -n "Valor: "
    cat "$DNS_FILE" | grep -o '".*"' | tr -d '"' | tr -d '\n'
    echo ""
    echo ""

    # Show verification command
    echo "========================================================================="
    echo "Para verificar la configuración DNS:"
    echo "========================================================================="
    echo ""
    echo "dig TXT $DKIM_SELECTOR._domainkey.$DOMAIN +short"
    echo ""
}

# List all domains with DKIM keys
list_domains() {
    print_info "Dominios con claves DKIM configuradas:"
    echo ""

    if [ ! -d "$DKIM_PATH" ] || [ -z "$(ls -A $DKIM_PATH 2>/dev/null)" ]; then
        print_warning "No hay dominios configurados"
        exit 0
    fi

    echo "========================================================================="
    printf "%-40s %-20s\n" "DOMINIO" "ESTADO"
    echo "========================================================================="

    for domain_dir in "$DKIM_PATH"/*; do
        if [ -d "$domain_dir" ]; then
            DOMAIN=$(basename "$domain_dir")
            KEY_FILE="$domain_dir/$DKIM_SELECTOR.key"

            if [ -f "$KEY_FILE" ]; then
                printf "%-40s ${GREEN}%-20s${NC}\n" "$DOMAIN" "✓ Configurado"
            else
                printf "%-40s ${RED}%-20s${NC}\n" "$DOMAIN" "✗ Sin clave"
            fi
        fi
    done

    echo "========================================================================="
    echo ""
}

# Test DKIM configuration
test_dkim() {
    local DOMAIN=$1

    if [ -z "$DOMAIN" ]; then
        print_error "Debes especificar un dominio"
        echo "Uso: $0 test <dominio>"
        exit 1
    fi

    print_info "Verificando configuración DKIM para: $DOMAIN"
    echo ""

    # Check if key exists
    DOMAIN_PATH="$DKIM_PATH/$DOMAIN"
    if [ ! -f "$DOMAIN_PATH/$DKIM_SELECTOR.key" ]; then
        print_error "No existe clave DKIM para $DOMAIN"
        exit 1
    fi

    print_success "✓ Clave DKIM existe localmente"

    # Check DNS record
    print_info "Verificando registro DNS..."

    DNS_RESULT=$(dig TXT "$DKIM_SELECTOR._domainkey.$DOMAIN" +short 2>/dev/null)

    if [ -z "$DNS_RESULT" ]; then
        print_error "✗ No se encontró registro DNS"
        echo ""
        print_warning "Añade el siguiente registro en tu DNS:"
        show_dkim "$DOMAIN"
        exit 1
    else
        print_success "✓ Registro DNS encontrado"
        echo ""
        echo "Registro actual:"
        echo "$DNS_RESULT"
        echo ""
    fi

    # Check KeyTable
    if grep -q "$DKIM_SELECTOR._domainkey.$DOMAIN" "$KEY_TABLE"; then
        print_success "✓ Dominio en KeyTable"
    else
        print_error "✗ Dominio no está en KeyTable"
    fi

    # Check SigningTable
    if grep -q "*@$DOMAIN" "$SIGNING_TABLE"; then
        print_success "✓ Dominio en SigningTable"
    else
        print_error "✗ Dominio no está en SigningTable"
    fi

    # Check OpenDKIM status
    if systemctl is-active --quiet opendkim; then
        print_success "✓ OpenDKIM está corriendo"
    else
        print_error "✗ OpenDKIM no está corriendo"
        print_info "Inicia OpenDKIM: sudo systemctl start opendkim"
    fi

    echo ""
    print_info "Configuración DKIM completa para $DOMAIN"
}

# Remove DKIM key for domain
remove_dkim() {
    local DOMAIN=$1

    if [ -z "$DOMAIN" ]; then
        print_error "Debes especificar un dominio"
        echo "Uso: $0 remove <dominio>"
        exit 1
    fi

    print_warning "Esto eliminará la clave DKIM para: $DOMAIN"
    read -p "¿Continuar? (s/n): " CONFIRM

    if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
        print_info "Operación cancelada"
        exit 0
    fi

    # Remove directory
    DOMAIN_PATH="$DKIM_PATH/$DOMAIN"
    if [ -d "$DOMAIN_PATH" ]; then
        rm -rf "$DOMAIN_PATH"
        print_success "Directorio eliminado"
    fi

    # Remove from KeyTable
    sed -i "/^$DKIM_SELECTOR._domainkey.$DOMAIN/d" "$KEY_TABLE"
    print_success "Entrada eliminada de KeyTable"

    # Remove from SigningTable
    sed -i "/^*@$DOMAIN/d" "$SIGNING_TABLE"
    print_success "Entrada eliminada de SigningTable"

    # Restart OpenDKIM
    systemctl restart opendkim
    print_success "OpenDKIM reiniciado"

    echo ""
    print_success "Clave DKIM eliminada para $DOMAIN"
}

# Show help
show_help() {
    cat << EOF
MailCore - DKIM Management Script

Uso: $0 <comando> [opciones]

Comandos:

  generate <dominio>    Generar nueva clave DKIM para un dominio
  show <dominio>        Mostrar clave pública DKIM para añadir al DNS
  list                  Listar todos los dominios con DKIM configurado
  test <dominio>        Verificar configuración DKIM (local y DNS)
  remove <dominio>      Eliminar clave DKIM de un dominio
  help                  Mostrar esta ayuda

Ejemplos:

  # Generar clave DKIM
  sudo $0 generate tudominio.com

  # Ver clave pública para DNS
  sudo $0 show tudominio.com

  # Listar dominios configurados
  sudo $0 list

  # Verificar configuración
  sudo $0 test tudominio.com

  # Eliminar clave
  sudo $0 remove tudominio.com

Notas:
  - Este script debe ejecutarse como root o con sudo
  - Las claves DKIM se almacenan en: $DKIM_PATH
  - Después de generar una clave, debes añadir el registro TXT a tu DNS
  - Usa 'test' para verificar que el DNS esté configurado correctamente

EOF
}

################################################################################
# Main
################################################################################

COMMAND=${1:-help}

case $COMMAND in
    generate)
        check_root
        generate_dkim "$2"
        ;;
    show)
        show_dkim "$2"
        ;;
    list)
        list_domains
        ;;
    test)
        test_dkim "$2"
        ;;
    remove)
        check_root
        remove_dkim "$2"
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        print_error "Comando desconocido: $COMMAND"
        echo ""
        show_help
        exit 1
        ;;
esac
