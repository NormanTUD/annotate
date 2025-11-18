#!/bin/bash
set -euo pipefail

# Prüfen, ob grundlegende Tools vorhanden sind
for cmd in apt dpkg-query sudo; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "$cmd not found. This script is only for Debian-like systems."
        exit 5
    fi
done

# Docker installieren falls nötig
if ! command -v docker &>/dev/null; then
    echo "docker not found. Installing docker..."
    sed -i 's/main$/main contrib non-free/g' /etc/apt/sources.list
    sudo apt update
    sudo apt install -y docker.io
fi

# Whiptail & git installieren falls nötig
for cmd in whiptail git; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "$cmd not found. Installing $cmd..."
        sudo apt install -y "$cmd"
    fi
done

# Default values
DB_HOST=""
INSTANCE_NAME=annotate
DB_USER=root
DB_PASSWORD=""
LOCAL_PORT=""
custom_local_db_dir=0

help_message() {
    echo "Usage: bash docker.sh [OPTIONS]"
    echo "Options:"
    echo "  --local-db-dir     Path to local db dir (i.e. on NFS for large images)"
    echo "  --local-port       Local port to bind for the GUI"
    echo "  --instance-name    Name of the instance (if you run several ones)"
    echo "  --help             Show this help message"
}

# CLI argument parsing
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --instance-name*)
            INSTANCE_NAME="$2"
            shift
            ;;
        --local-db-dir*)
            local_db_dir="$(realpath "$2")"
            custom_local_db_dir=1
            shift
            ;;
        --local-port*)
            LOCAL_PORT="$2"
            shift
            ;;
        --help)
            help_message
            exit 0
            ;;
        *)
            echo "Error: Unknown option '$1'. Use --help for usage."
            help_message
            exit 1
            ;;
    esac
    shift
done

# Set defaults
if [[ $custom_local_db_dir -eq 0 ]]; then
    local_db_dir="$HOME/${INSTANCE_NAME}_db"
fi
if [[ -z $LOCAL_PORT ]]; then
    echo "Error: Missing required parameter --local-port. Use --help for usage."
    exit 2
fi

# IP Auswahl für localhost
ips=$(ip addr | grep inet | grep -v : | sed -e 's#.*inet\s*##' | sed -e 's#/.*##' | grep -v "^127.")
if [[ $DB_HOST == "localhost" || $DB_HOST == "127.0.0.1" ]]; then
    ip_array=()
    while read -r ip; do
        ip_array+=("$ip" "")
    done <<< "$ips"
    selected_ip=$(whiptail --title "Local IPs" --menu "Choose a local IP:" 15 60 6 "${ip_array[@]}" 3>&1 1>&2 2>&3)
    if [[ -n $selected_ip ]]; then
        DB_HOST="$selected_ip"
        echo "DB_HOST set to: $DB_HOST"
    else
        echo "No IP selected. Exiting..."
        exit 3
    fi
fi

export DB_HOST
export LOCAL_PORT

# .env erstellen
echo "DB_HOST=${INSTANCE_NAME}_mariadb
DB_PORT=3306
LOCAL_PORT=$LOCAL_PORT
DB_PASSWORD=root
DB_USER=root
" > .env

mkdir -p "$local_db_dir"

# docker-compose.yml schreiben
cat > docker-compose.yml <<EOL
services:
  ${INSTANCE_NAME}_mariadb:
    image: mariadb:latest
    container_name: ${INSTANCE_NAME}_mariadb
    restart: always
    environment:
      - MYSQL_ROOT_PASSWORD=root
      - MYSQL_ROOT_HOST=%
    volumes:
      - $local_db_dir:/var/lib/mysql
      - ./my.cnf:/etc/mysql/conf.d/disable_locks.cnf:ro
    networks:
      - ${INSTANCE_NAME}_network

  ${INSTANCE_NAME}_annotate:
    build:
      context: .
      args:
        - INSTANCE_NAME=${INSTANCE_NAME}
    container_name: annotate
    restart: unless-stopped
    depends_on:
      - ${INSTANCE_NAME}_mariadb
    environment:
      - DB_HOST=${INSTANCE_NAME}_mariadb
      - DB_PORT=3306
      - DB_USER=root
      - DB_PASSWORD=root
    networks:
      - ${INSTANCE_NAME}_network
    ports:
      - $LOCAL_PORT:80
    tmpfs:
      - /tmp:rw
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /usr/bin/docker:/usr/bin/docker

volumes:
  mariadb_data:

networks:
  ${INSTANCE_NAME}_network:
    driver: bridge
EOL

# Docker Compose installieren falls nötig
if ! command -v docker compose >/dev/null 2>&1 && ! command -v docker-compose >/dev/null 2>&1; then
    sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
        -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
fi

# Syntax-Check (optional)
SYNTAX_ERRORS=0
# { for i in $(ls *.php); do if ! php -l $i 2>&1; then SYNTAX_ERRORS=1; fi ; done } | grep -v mongodb
if [[ "$SYNTAX_ERRORS" -ne "0" ]]; then
    echo "Tests failed"
    exit 4
fi

# Compose Command setzen
if [ "$(id -u)" -eq 0 ] || groups "$USER" | grep -q '\bdocker\b'; then
    CMD="docker compose"
else
    echo "Building container. This may need sudo-permissions"
    CMD="sudo docker compose"
fi

if [[ -S /var/run/docker.sock ]]; then
    SOCKET_GID=$(stat -c '%g' /var/run/docker.sock)
    # Prüfen, ob Gruppe mit dieser GID existiert
    EXISTING_GROUP=$(getent group "$SOCKET_GID" | cut -d: -f1 || true)
    if [[ -z "$EXISTING_GROUP" ]]; then
        sudo groupadd -g "$SOCKET_GID" docker_host || true
    fi
    # Prüfen, ob Gruppe existiert, bevor usermod
    if getent group docker_host >/dev/null 2>&1; then
        sudo usermod -aG docker_host www-data || true
    fi
fi

# Stop and remove existing containers
$CMD -p "${INSTANCE_NAME}" down --remove-orphans

# Build and start fresh
$CMD -p "${INSTANCE_NAME}" build
$CMD -p "${INSTANCE_NAME}" up -d
