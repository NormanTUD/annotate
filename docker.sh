#!/bin/bash


if ! command -v apt 2>&1 >/dev/null; then
	echo "apt not installed. This script is only for debian and debianlike systems."
	exit 5
fi

if ! command -v dpkg-query 2>&1 >/dev/null; then
	echo "dpkg-query not found"
	exit 6
fi

if ! command -v sudo 2>&1 >/dev/null; then
	echo "sudo not installed. This script is only for debian and debianlike systems."
	exit 7
fi

if ! command -v docker &>/dev/null; then
	echo "docker not found. Installing docker..."
	sed -i 's/main$/main contrib non-free/g' /etc/apt/sources.list
	sudo apt update
	sudo apt install -y docker.io
fi

if ! command -v whiptail &>/dev/null; then
	echo "whiptail not found. Installing whiptail..."
	sudo apt install -y whiptail
fi

if ! command -v git &>/dev/null; then
	echo "git not found. Installing git..."
	sudo apt install -y git
fi

# Default values
DB_HOST=""
INSTANCE_NAME=annotate
DB_USER=root
DB_PASSWORD=""
LOCAL_PORT=""

custom_local_db_dir=0

# Help message
help_message() {
	echo "Usage: bash docker.sh [OPTIONS]"
	echo "Options:"
	echo "  --local-db-dir     Path to local db dir (i.e. on NFS for large images)"
	echo "  --local-port       Local port to bind for the GUI"
	echo "  --instance-name    Name of the instance (if you run several ones)"
	echo "  --help             Show this help message"
}

# Parse command-line arguments
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
			echo ""
			help_message

			exit 1
			;;
	esac

	shift
done

if [[ $custom_local_db_dir -eq 0 ]]; then
	local_db_dir="$HOME/${INSTANCE_NAME}_db"
fi

# Check for required parameters

if [[ -z $LOCAL_PORT ]]; then
	echo "Error: Missing required parameter --local-port. Use --help for usage."
	exit 2
fi


is_package_installed() {
	dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -c "ok installed"
}


ips=$(ip addr | grep inet | grep -v : | sed -e 's#.*inet\s*##' | sed -e 's#/.*##' | grep -v "^127.")
# Check if DB_HOST is localhost or 127.0.0.1
if [[ $DB_HOST == "localhost" || $DB_HOST == "127.0.0.1" ]]; then
	# Create an array of IPs
	ip_array=()
	while read -r ip; do
		ip_array+=("$ip" "")
	done <<< "$ips"

	# Show Whiptail menu
	selected_ip=$(whiptail --title "Local IPs" --menu "Choose a local IP:" 15 60 6 "${ip_array[@]}" 3>&1 1>&2 2>&3)

	# Check if a selection was made
	if [[ -n $selected_ip ]]; then
		echo "Selected IP: $selected_ip"
		# Use the selected IP for DB_HOST
		DB_HOST="$selected_ip"
		echo "DB_HOST set to: $DB_HOST"
		# Add your logic here using the updated DB_HOST variable
	else
		echo "No IP selected. Exiting..."
		exit 3
	fi
fi

export DB_HOST
export LOCAL_PORT

echo "DB_HOST=${INSTANCE_NAME}_mariadb
DB_PORT=3306
LOCAL_PORT=$LOCAL_PORT
DB_PASSWORD=root
DB_USER=root
" > .env

if [[ ! -d $local_db_dir ]]; then
	mkdir -p $local_db_dir
fi

echo "services:
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
  mariadb_data:

networks:
  ${INSTANCE_NAME}_network:
    driver: bridge" > docker-compose.yml

echo "=== Current git hash before auto-pulling ==="
git rev-parse HEAD
echo "=== Current git hash before auto-pulling ==="

git pull

#!/usr/bin/env bash
set -euo pipefail

if ! command -v docker compose >/dev/null 2>&1 || ! command -v docker-compose >/dev/null 2>&1; then
	sudo apt-get update -qq
	sudo apt-get install -y docker-compose-plugin
fi

SYNTAX_ERRORS=0
#{ for i in $(ls *.php); do if ! php -l $i 2>&1; then SYNTAX_ERRORS=1; fi ; done } | 2>&1 grep -v mongodb

if [[ "$SYNTAX_ERRORS" -ne "0" ]]; then
	echo "Tests failed";
	exit 4
fi

if [ "$(id -u)" -eq 0 ] || groups "$USER" | grep -q '\bdocker\b'; then
	CMD="docker compose"
else
	echo "Building container. This may need sudo-permissions"
	CMD="sudo docker compose"
fi

$CMD build && $CMD up -d || echo "Failed to build container"
