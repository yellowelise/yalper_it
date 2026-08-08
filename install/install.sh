#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly SOURCE_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd -P)"
readonly APP_USER='yalperit'
readonly APP_HOME='/home/yalperit'
DEFAULT_CONFIG_FILE="${SCRIPT_DIR}/install.conf"
if [[ -f "${APP_HOME}/.config/yalper/install.conf" ]]; then
    DEFAULT_CONFIG_FILE="${APP_HOME}/.config/yalper/install.conf"
fi
readonly CONFIG_FILE="${1:-${DEFAULT_CONFIG_FILE}}"

TEMP_FILES=()
LAST_TEMP_FILE=''

log() {
    printf '[yalper-install] %s\n' "$*"
}

die() {
    printf '[yalper-install] ERRORE: %s\n' "$*" >&2
    exit 1
}

cleanup() {
    local file
    for file in "${TEMP_FILES[@]:-}"; do
        if [[ -n "${file}" && -f "${file}" ]]; then
            rm -f -- "${file}"
        fi
    done
}

on_error() {
    local exit_code=$?
    printf '[yalper-install] Installazione interrotta alla riga %s (codice %s).\n' "${BASH_LINENO[0]}" "${exit_code}" >&2
    exit "${exit_code}"
}

trap cleanup EXIT
trap on_error ERR

new_temp_file() {
    LAST_TEMP_FILE="$(mktemp /tmp/yalper-install.XXXXXX)"
    TEMP_FILES+=("${LAST_TEMP_FILE}")
    chmod 600 "${LAST_TEMP_FILE}"
}

require_value() {
    local name=$1
    local value=${!name:-}
    [[ -n "${value}" ]] || die "Valore mancante in install.conf: ${name}"
    [[ "${value}" != CHANGE_ME* ]] || die "Sostituire il valore di esempio: ${name}"
    [[ "${value}" != *$'\n'* && "${value}" != *$'\r'* ]] || die "Valore non valido: ${name}"
}

sql_escape() {
    local value=$1
    value=${value//\\/\\\\}
    value=${value//\'/\'\'}
    printf '%s' "${value}"
}

php_escape() {
    local value=$1
    value=${value//\\/\\\\}
    value=${value//\'/\\\'}
    printf '%s' "${value}"
}

yes_value() {
    case "${1,,}" in
        yes|true|1) return 0 ;;
        no|false|0) return 1 ;;
        *) die "Valore booleano non valido: $1 (usare yes oppure no)" ;;
    esac
}

if [[ ${EUID} -ne 0 ]]; then
    die 'Eseguire come root: sudo bash install/install.sh'
fi

if [[ ! -f "${CONFIG_FILE}" ]]; then
    if [[ "${CONFIG_FILE}" == "${SCRIPT_DIR}/install.conf" ]]; then
        cp -- "${SCRIPT_DIR}/install.conf.example" "${CONFIG_FILE}"
        chmod 600 "${CONFIG_FILE}"
        die "Creato ${CONFIG_FILE}. Compilarlo e rilanciare lo script."
    fi
    die "File di configurazione non trovato: ${CONFIG_FILE}"
fi

chmod 600 "${CONFIG_FILE}"
# shellcheck source=/dev/null
source "${CONFIG_FILE}"

for required in DOMAIN APP_ROOT DB_NAME DB_USER DB_PASSWORD ADMIN_EMAIL ADMIN_PASSWORD ADMIN_FIRSTNAME ADMIN_LASTNAME PUSHER_KEY PUSHER_SECRET PUSHER_APP_ID PUSHER_CLUSTER IMPORT_DB_SCHEMA ENABLE_CRON ENABLE_TLS PHP_TIMEZONE; do
    require_value "${required}"
done

SERVER_ALIAS=${SERVER_ALIAS:-}
LETSENCRYPT_EMAIL=${LETSENCRYPT_EMAIL:-}
ADMIN_MOBILE=${ADMIN_MOBILE:-}

[[ "${DOMAIN}" =~ ^[A-Za-z0-9.-]+$ ]] || die 'DOMAIN non valido.'
if [[ -n "${SERVER_ALIAS}" ]]; then
    [[ "${SERVER_ALIAS}" =~ ^[A-Za-z0-9.-]+$ ]] || die 'SERVER_ALIAS non valido.'
fi
[[ "${DB_NAME}" =~ ^[A-Za-z0-9_]+$ ]] || die 'DB_NAME puo contenere solo lettere, numeri e underscore.'
[[ "${DB_USER}" =~ ^[A-Za-z0-9_]+$ ]] || die 'DB_USER puo contenere solo lettere, numeri e underscore.'
[[ "${ADMIN_EMAIL}" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ && ${#ADMIN_EMAIL} -le 50 ]] || die 'ADMIN_EMAIL non valida o troppo lunga.'
[[ ${#ADMIN_PASSWORD} -ge 12 && ${#ADMIN_PASSWORD} -le 72 ]] || die 'ADMIN_PASSWORD deve contenere da 12 a 72 caratteri.'
[[ ${#ADMIN_FIRSTNAME} -le 100 && ${#ADMIN_LASTNAME} -le 100 && ${#ADMIN_MOBILE} -le 50 ]] || die 'Dati amministratore troppo lunghi.'
[[ "${PUSHER_KEY}" =~ ^[A-Za-z0-9_-]+$ ]] || die 'PUSHER_KEY non valida.'
[[ "${PUSHER_APP_ID}" =~ ^[A-Za-z0-9_-]+$ ]] || die 'PUSHER_APP_ID non valido.'
[[ "${PUSHER_CLUSTER}" =~ ^[A-Za-z0-9_-]+$ ]] || die 'PUSHER_CLUSTER non valido.'
[[ "${PHP_TIMEZONE}" =~ ^[A-Za-z0-9_+/-]+$ ]] || die 'PHP_TIMEZONE non valido.'
[[ "${APP_ROOT}" =~ ^/[A-Za-z0-9._/-]+$ ]] || die 'APP_ROOT deve essere un percorso assoluto senza spazi.'
[[ ${#DB_PASSWORD} -ge 16 ]] || die 'DB_PASSWORD deve contenere almeno 16 caratteri.'
[[ "${APP_ROOT}" != */ ]] || die 'APP_ROOT non deve terminare con slash.'
[[ "$(basename -- "${APP_ROOT}")" == 'public_html' ]] || die 'Per sicurezza APP_ROOT deve terminare con /public_html.'
case "${APP_ROOT}" in
    "${APP_HOME}"/*) ;;
    *) die "Per sicurezza APP_ROOT deve trovarsi sotto ${APP_HOME}/" ;;
esac
[[ "${APP_ROOT}" != "${APP_HOME}" && "${APP_ROOT}" != '/' ]] || die 'APP_ROOT troppo ampio.'

if yes_value "${ENABLE_TLS}"; then
    require_value LETSENCRYPT_EMAIL
    [[ "${LETSENCRYPT_EMAIL}" == *@* ]] || die 'LETSENCRYPT_EMAIL non valida.'
fi
if yes_value "${IMPORT_DB_SCHEMA}"; then
    :
else
    :
fi
if yes_value "${ENABLE_CRON}"; then
    :
else
    :
fi

if [[ ! -r /etc/os-release ]]; then
    die 'Impossibile identificare il sistema operativo.'
fi
# shellcheck source=/dev/null
source /etc/os-release
[[ "${ID:-}" == 'ubuntu' && "${VERSION_ID:-}" == '24.04' ]] || die 'Questo installer supporta esclusivamente Ubuntu 24.04.'

readonly DB_SCHEMA_PATH="${SOURCE_ROOT}/database/schema.sql"
readonly MIGRATION_PATH="${SOURCE_ROOT}/database/migrations/001_create_user_sessions.sql"
[[ -r "${MIGRATION_PATH}" ]] || die "Migrazione non trovata: ${MIGRATION_PATH}"

log 'Installazione pacchetti di sistema...'
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends software-properties-common
add-apt-repository -y universe
apt-get update
apt-get install -y --no-install-recommends \
    apache2 \
    ca-certificates \
    certbot \
    composer \
    cron \
    curl \
    ffmpeg \
    gzip \
    libapache2-mod-php8.3 \
    mariadb-client \
    mariadb-server \
    openssl \
    php8.3-cli \
    php8.3-common \
    php8.3-curl \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-mysql \
    php8.3-xml \
    php8.3-zip \
    python3-certbot-apache \
    rsync \
    unzip \
    util-linux
unset DEBIAN_FRONTEND

timedatectl set-timezone "${PHP_TIMEZONE}"

log 'Creazione utente e directory applicative...'
if ! id -u "${APP_USER}" >/dev/null 2>&1; then
    useradd --create-home --home-dir "${APP_HOME}" --shell /bin/bash "${APP_USER}"
fi
getent group "${APP_USER}" >/dev/null || die "Gruppo ${APP_USER} non trovato."
usermod -a -G "${APP_USER}" www-data

install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${APP_HOME}/.config/yalper"
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${APP_HOME}/logs"
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${APP_HOME}/backups"
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${APP_HOME}/.cache/yalper"

external_install_config="${APP_HOME}/.config/yalper/install.conf"
if [[ "$(readlink -f -- "${CONFIG_FILE}")" != "$(readlink -m -- "${external_install_config}")" ]]; then
    install -o "${APP_USER}" -g "${APP_USER}" -m 0600 "${CONFIG_FILE}" "${external_install_config}"
fi

readonly RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
readonly BACKUP_DIR="${APP_HOME}/backups/install-${RUN_ID}"
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${BACKUP_DIR}"

log 'Avvio MariaDB e creazione database/utente...'
systemctl enable --now mariadb

db_host_php="$(php_escape 'localhost')"
db_name_php="$(php_escape "${DB_NAME}")"
db_user_php="$(php_escape "${DB_USER}")"
db_password_php="$(php_escape "${DB_PASSWORD}")"
pusher_key_php="$(php_escape "${PUSHER_KEY}")"
pusher_secret_php="$(php_escape "${PUSHER_SECRET}")"
pusher_app_id_php="$(php_escape "${PUSHER_APP_ID}")"
pusher_cluster_php="$(php_escape "${PUSHER_CLUSTER}")"
new_temp_file
secrets_temp="${LAST_TEMP_FILE}"
cat > "${secrets_temp}" <<PHP
<?php
return array(
    'database' => array(
        'host' => '${db_host_php}',
        'name' => '${db_name_php}',
        'user' => '${db_user_php}',
        'password' => '${db_password_php}',
    ),
    'pusher' => array(
        'key' => '${pusher_key_php}',
        'secret' => '${pusher_secret_php}',
        'app_id' => '${pusher_app_id_php}',
        'cluster' => '${pusher_cluster_php}',
    ),
);
PHP

db_password_sql="$(sql_escape "${DB_PASSWORD}")"
db_name_sql="$(sql_escape "${DB_NAME}")"
db_user_sql="$(sql_escape "${DB_USER}")"
new_temp_file
db_setup_sql="${LAST_TEMP_FILE}"
cat > "${db_setup_sql}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '${db_user_sql}'@'localhost' IDENTIFIED BY '${db_password_sql}';
ALTER USER '${db_user_sql}'@'localhost' IDENTIFIED BY '${db_password_sql}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${db_user_sql}'@'localhost';
FLUSH PRIVILEGES;
SQL
mariadb < "${db_setup_sql}"
if [[ -f "${APP_HOME}/.config/yalper/secrets.php" ]]; then
    cp -- "${APP_HOME}/.config/yalper/secrets.php" "${BACKUP_DIR}/secrets-before-install.php"
    chown "${APP_USER}:${APP_USER}" "${BACKUP_DIR}/secrets-before-install.php"
    chmod 0600 "${BACKUP_DIR}/secrets-before-install.php"
fi
install -o "${APP_USER}" -g "${APP_USER}" -m 0640 "${secrets_temp}" "${APP_HOME}/.config/yalper/secrets.php"

table_count="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db_name_sql}';")"
if [[ "${table_count}" -gt 0 ]]; then
    log "Database esistente (${table_count} tabelle): creazione backup..."
    mariadb-dump --single-transaction --routines --triggers "${DB_NAME}" | gzip -9 > "${BACKUP_DIR}/database-before-install.sql.gz"
    chown "${APP_USER}:${APP_USER}" "${BACKUP_DIR}/database-before-install.sql.gz"
    chmod 0600 "${BACKUP_DIR}/database-before-install.sql.gz"
elif yes_value "${IMPORT_DB_SCHEMA}"; then
    [[ -r "${DB_SCHEMA_PATH}" ]] || die "Schema DB non trovato: ${DB_SCHEMA_PATH}"
    if grep -Eq '^[[:space:]]*INSERT[[:space:]]+INTO' "${DB_SCHEMA_PATH}"; then
        die 'Lo schema contiene INSERT INTO: importazione rifiutata.'
    fi
    log 'Database vuoto: importazione dello schema senza dati...'
    mariadb "${DB_NAME}" < "${DB_SCHEMA_PATH}"
else
    die 'Database vuoto e IMPORT_DB_SCHEMA=no. Importare prima uno schema che contenga la tabella users.'
fi

users_table="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db_name_sql}' AND table_name='users';")"
[[ "${users_table}" == '1' ]] || die 'La tabella users non esiste dopo il bootstrap del DB.'

log 'Applicazione migrazione sessioni OTT multiple...'
mariadb "${DB_NAME}" < "${MIGRATION_PATH}"

user_count="$(mariadb --batch --skip-column-names "${DB_NAME}" -e 'SELECT COUNT(*) FROM users;')"
if [[ "${user_count}" == '0' ]]; then
    log 'Creazione del primo account amministratore...'
    admin_password_hash="$(printf '%s' "${ADMIN_PASSWORD}" | /usr/bin/php -r '$password = stream_get_contents(STDIN); echo password_hash($password, PASSWORD_DEFAULT);')"
    [[ -n "${admin_password_hash}" ]] || die 'Generazione hash password amministratore fallita.'
    admin_token="$(openssl rand -hex 16)"
    admin_ott="$(openssl rand -hex 14 | tr '[:lower:]' '[:upper:]')"
    admin_email_sql="$(sql_escape "${ADMIN_EMAIL}")"
    admin_firstname_sql="$(sql_escape "${ADMIN_FIRSTNAME}")"
    admin_lastname_sql="$(sql_escape "${ADMIN_LASTNAME}")"
    admin_mobile_sql="$(sql_escape "${ADMIN_MOBILE}")"
    admin_hash_sql="$(sql_escape "${admin_password_hash}")"
    admin_token_sql="$(sql_escape "${admin_token}")"
    admin_ott_sql="$(sql_escape "${admin_ott}")"
    new_temp_file
    admin_sql="${LAST_TEMP_FILE}"
    cat > "${admin_sql}" <<SQL
START TRANSACTION;
INSERT INTO users
    (firstname, lastname, email, mobilenumber, password, token, is_active, date_time, OTT)
VALUES
    ('${admin_firstname_sql}', '${admin_lastname_sql}', '${admin_email_sql}', '${admin_mobile_sql}', '${admin_hash_sql}', '${admin_token_sql}', '1', CURDATE(), '${admin_ott_sql}');
SET @yalper_admin_id = LAST_INSERT_ID();
INSERT INTO user_credits (id_user, user_token, used_credits, left_credits)
VALUES (@yalper_admin_id, '${admin_token_sql}', 0, 25);
COMMIT;
SQL
    mariadb "${DB_NAME}" < "${admin_sql}"
else
    log "Account esistenti: ${user_count}; creazione amministratore iniziale saltata."
fi

log 'Deploy dei file applicativi...'
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "$(dirname -- "${APP_ROOT}")"
install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${APP_ROOT}"

source_real="$(readlink -f -- "${SOURCE_ROOT}")"
app_real="$(readlink -f -- "${APP_ROOT}")"
if [[ "${source_real}" != "${app_real}" && "${app_real}/" == "${source_real}/"* ]]; then
    die 'APP_ROOT non puo essere una sottodirectory del sorgente: rsync ricorsivo non sicuro.'
fi
if [[ "${source_real}" != "${app_real}" ]]; then
    install -d -o "${APP_USER}" -g "${APP_USER}" -m 0750 "${BACKUP_DIR}/code"
    rsync -a \
        --delete \
        --backup \
        --backup-dir="${BACKUP_DIR}/code" \
        --exclude='/.git/' \
        --exclude='/install/install.conf' \
        --exclude='/node_modules/' \
        --exclude='/upload/uploads/***' \
        --exclude='/vendor/' \
        --exclude='/yalperit_db.mysql.sql' \
        "${SOURCE_ROOT}/" "${APP_ROOT}/"
else
    log 'Il sorgente coincide con APP_ROOT: copia dei file non necessaria.'
fi

if [[ -L "${APP_ROOT}/upload/uploads" ]]; then
    die 'upload/uploads non puo essere un link simbolico.'
fi
install -d -o "${APP_USER}" -g "${APP_USER}" -m 2770 "${APP_ROOT}/upload/uploads"

# Un dump eventualmente gia presente nel webroot viene messo al sicuro, non cancellato.
if [[ -f "${APP_ROOT}/yalperit_db.mysql.sql" ]]; then
    mv -- "${APP_ROOT}/yalperit_db.mysql.sql" "${BACKUP_DIR}/yalperit_db.mysql.sql"
    chown "${APP_USER}:${APP_USER}" "${BACKUP_DIR}/yalperit_db.mysql.sql"
    chmod 0600 "${BACKUP_DIR}/yalperit_db.mysql.sql"
fi
if [[ -f "${APP_ROOT}/install/install.conf" ]]; then
    mv -- "${APP_ROOT}/install/install.conf" "${BACKUP_DIR}/install.conf"
    chown "${APP_USER}:${APP_USER}" "${BACKUP_DIR}/install.conf"
    chmod 0600 "${BACKUP_DIR}/install.conf"
fi

# La chiave Pusher e pubblica e deve essere leggibile dal browser; il secret
# resta esclusivamente nel file PHP esterno al webroot.
new_temp_file
public_config_temp="${LAST_TEMP_FILE}"
cat > "${public_config_temp}" <<JAVASCRIPT
// Generato da install/install.sh. Non inserire segreti in questo file.
window.YALPER_PUBLIC_CONFIG = Object.freeze({
    pusherKey: '${PUSHER_KEY}',
    pusherCluster: '${PUSHER_CLUSTER}'
});
JAVASCRIPT
install -o "${APP_USER}" -g "${APP_USER}" -m 0640 "${public_config_temp}" "${APP_ROOT}/public-config.js"

log 'Installazione dipendenze Composer...'
chown -R "${APP_USER}:${APP_USER}" "${APP_ROOT}"
runuser -u "${APP_USER}" -- composer install \
    --working-dir="${APP_ROOT}" \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

log 'Configurazione PHP...'
new_temp_file
php_ini_temp="${LAST_TEMP_FILE}"
cat > "${php_ini_temp}" <<INI
date.timezone=${PHP_TIMEZONE}
display_errors=Off
log_errors=On
memory_limit=512M
max_execution_time=120
post_max_size=72M
upload_max_filesize=70M
session.cookie_httponly=1
session.cookie_samesite=Lax
INI
install -o root -g root -m 0644 "${php_ini_temp}" /etc/php/8.3/apache2/conf.d/99-yalper.ini
install -o root -g root -m 0644 "${php_ini_temp}" /etc/php/8.3/cli/conf.d/99-yalper.ini

log 'Configurazione Apache...'
a2enmod headers rewrite expires ssl >/dev/null
new_temp_file
vhost_temp="${LAST_TEMP_FILE}"
server_alias_line=''
if [[ -n "${SERVER_ALIAS}" ]]; then
    server_alias_line="    ServerAlias ${SERVER_ALIAS}"
fi
cat > "${vhost_temp}" <<APACHE
<VirtualHost *:80>
    ServerName ${DOMAIN}
${server_alias_line}
    DocumentRoot ${APP_ROOT}
    DirectoryIndex index.html index.php

    <Directory ${APP_ROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory ${APP_ROOT}/install>
        Require all denied
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/yalper-error.log
    CustomLog \${APACHE_LOG_DIR}/yalper-access.log combined
</VirtualHost>
APACHE
install -o root -g root -m 0644 "${vhost_temp}" /etc/apache2/sites-available/yalper.conf
a2ensite yalper.conf >/dev/null
apache2ctl configtest

log 'Configurazione cron e rotazione log...'
new_temp_file
cron_temp="${LAST_TEMP_FILE}"
cron_prefix='# '
if yes_value "${ENABLE_CRON}"; then
    cron_prefix=''
fi
cat > "${cron_temp}" <<CRON
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
MAILTO=""

${cron_prefix}* * * * * ${APP_USER} /usr/bin/flock -n ${APP_HOME}/.cache/yalper/demondb.lock /usr/bin/php ${APP_ROOT}/demondb.php >> ${APP_HOME}/logs/demondb.log 2>&1
${cron_prefix}* * * * * ${APP_USER} /usr/bin/flock -n ${APP_HOME}/.cache/yalper/demonlive.lock /usr/bin/php ${APP_ROOT}/demonlive.php >> ${APP_HOME}/logs/demonlive.log 2>&1
CRON
install -o root -g root -m 0644 "${cron_temp}" /etc/cron.d/yalper

new_temp_file
logrotate_temp="${LAST_TEMP_FILE}"
cat > "${logrotate_temp}" <<LOGROTATE
${APP_HOME}/logs/*.log {
    weekly
    rotate 8
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
    su ${APP_USER} ${APP_USER}
}
LOGROTATE
install -o root -g root -m 0644 "${logrotate_temp}" /etc/logrotate.d/yalper

log 'Applicazione permessi finali...'
find "${APP_ROOT}" -type d -exec chmod 0750 {} +
find "${APP_ROOT}" -type f -exec chmod 0640 {} +
find "${APP_ROOT}/upload/uploads" -type d -exec chmod 2770 {} +
find "${APP_ROOT}/upload/uploads" -type f -exec chmod 0660 {} +
chown -R "${APP_USER}:${APP_USER}" "${APP_ROOT}"
chmod 0750 "${APP_HOME}" "${APP_HOME}/.config" "${APP_HOME}/.config/yalper"
chmod 0640 "${APP_HOME}/.config/yalper/secrets.php"

systemctl enable --now apache2 cron
systemctl restart apache2
systemctl restart cron

if yes_value "${ENABLE_TLS}"; then
    log 'Richiesta certificato TLS con Certbot...'
    certbot_args=(
        --apache
        --non-interactive
        --agree-tos
        --redirect
        --email "${LETSENCRYPT_EMAIL}"
        -d "${DOMAIN}"
    )
    if [[ -n "${SERVER_ALIAS}" ]]; then
        certbot_args+=( -d "${SERVER_ALIAS}" )
    fi
    certbot "${certbot_args[@]}"
fi

log 'Verifiche finali...'
php -l "${APP_ROOT}/demondb.php" >/dev/null
php -l "${APP_ROOT}/demonlive.php" >/dev/null
runuser -u www-data -- /usr/bin/php -r "require '${APP_ROOT}/config/db.php'; exit(isset(\$connection) && mysqli_ping(\$connection) ? 0 : 1);"
command -v ffmpeg >/dev/null
command -v ffprobe >/dev/null
apache2ctl configtest
mariadb --batch --skip-column-names "${DB_NAME}" -e "SELECT COUNT(*) FROM user_sessions;" >/dev/null
systemctl is-active --quiet apache2
systemctl is-active --quiet mariadb
systemctl is-active --quiet cron

printf '\n'
log 'Installazione completata.'
log "Applicazione: http://${DOMAIN}/"
if yes_value "${ENABLE_TLS}"; then
    log "TLS: https://${DOMAIN}/"
else
    log 'TLS non abilitato. Impostare ENABLE_TLS=yes dopo aver configurato il DNS e rilanciare.'
fi
log "Backup di questa esecuzione: ${BACKUP_DIR}"
log "Segreti: ${APP_HOME}/.config/yalper/secrets.php"
log "Log demoni: ${APP_HOME}/logs/"
if yes_value "${ENABLE_CRON}"; then
    log 'Cron demoni: attivi.'
else
    log 'Cron demoni: configurati ma disattivati. Impostare ENABLE_CRON=yes e rilanciare quando gli upload sono pronti.'
fi
