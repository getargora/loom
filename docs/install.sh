#!/usr/bin/env bash
set -euo pipefail

# ---------- Helpers ----------
log() { printf "\n\033[1;32m[%s]\033[0m %s\n" "$(date +%H:%M:%S)" "$*"; }
warn() { printf "\n\033[1;33m[WARN]\033[0m %s\n" "$*"; }
err() { printf "\n\033[1;31m[ERR]\033[0m %s\n" "$*" >&2; }
die() { err "$*"; exit 1; }

require_root() {
  if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    die "Please run as root (sudo bash $0)."
  fi
}

detect_os() {
  . /etc/os-release
  OS_ID="$ID"            # ubuntu/debian
  OS_VER="$VERSION_ID"   # e.g. 22.04, 24.04, 12, 13
  OS_CODENAME="${VERSION_CODENAME:-}"
  log "Detected: $PRETTY_NAME"
}

# Return best-guess A/AAAA for bind (optional)
detect_ips() {
  IPV4=$(hostname -I | awk '{print $1}' || true)
  IPV6=$(ip -6 addr show scope global 2>/dev/null | awk '/inet6/{print $2}' | cut -d/ -f1 | head -n1 || true)
}

prompt() {
  local var="$1"; local msg="$2"; local def="${3-}"; local secret="${4-}"
  local val
  while true; do
    if [[ -n "$def" ]]; then
      if [[ "$secret" == "secret" ]]; then
        read -r -s -p "$msg [$def]: " val; echo
      else
        read -r -p "$msg [$def]: " val
      fi
      val="${val:-$def}"
    else
      if [[ "$secret" == "secret" ]]; then
        read -r -s -p "$msg: " val; echo
      else
        read -r -p "$msg: " val
      fi
    fi
    [[ -n "$val" ]] && break || warn "Value cannot be empty."
  done
  eval "$var=\"\$val\""
}

# ---------- Pre-flight ----------
require_root
detect_os
detect_ips

log "Updating apt index and base tools…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y curl wget ca-certificates gnupg lsb-release software-properties-common ufw git unzip bzip2 net-tools whois

# ---------- Ask user inputs ----------
echo
log "Basic configuration"

DEFAULT_HOST="loom.local"
prompt HOSTNAME "Enter your Loom hostname (FQDN for HTTPS)" "$DEFAULT_HOST"
prompt TLS_EMAIL "Enter email for Caddy TLS/Cert notifications" "admin@$HOSTNAME"
prompt INSTALL_PATH "Install path for Loom" "/var/www/loom"

# DB choice
echo
echo "Choose database backend:"
select DB_BACKEND in "MariaDB" "PostgreSQL" "SQLite"; do
  case "$DB_BACKEND" in
    MariaDB|PostgreSQL|SQLite) break ;;
    *) echo "Invalid selection."; ;;
  esac
done

# DB credentials (used unless SQLite)
if [[ "$DB_BACKEND" != "SQLite" ]]; then
  prompt DB_NAME "Database name" "loom"
  prompt DB_USER "Database user" "loom"
  prompt DB_PASS "Database password" "" "secret"
fi

# Admin user for Loom
echo
log "Admin user for Loom"
prompt ADMIN_USER "Admin username" "admin"
prompt ADMIN_PASS "Admin password" "" "secret"

# Optional custom bind IPs for Caddy
USE_BIND="n"
if [[ -n "${IPV4:-}" || -n "${IPV6:-}" ]]; then
  echo
  echo "Detected IPs: IPv4=${IPV4:-none}, IPv6=${IPV6:-none}"
  read -r -p "Bind Caddy to these IPs? (y/N): " USE_BIND
  USE_BIND="${USE_BIND:-n}"
fi
if [[ "$USE_BIND" =~ ^[Yy]$ ]]; then
  CADDY_BIND_LINE="    bind ${IPV4:-} ${IPV6:-}"
else
  CADDY_BIND_LINE=""
fi

# ---------- PHP 8.3 repos ----------
log "Configuring PHP 8.3 repository…"
if [[ "$OS_ID" == "ubuntu" ]]; then
  add-apt-repository -y ppa:ondrej/php
elif [[ "$OS_ID" == "debian" ]]; then
  # Sury repo for PHP 8.3
  install -d -m 0755 /etc/apt/keyrings
  curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/keyrings/sury.gpg
  echo "deb [signed-by=/etc/apt/keyrings/sury.gpg] https://packages.sury.org/php/ $OS_CODENAME main" > /etc/apt/sources.list.d/sury-php.list
else
  die "Unsupported OS."
fi

apt-get update -y
log "Installing PHP 8.3 and extensions…"
apt-get install -y composer php8.3 php8.3-cli php8.3-common php8.3-fpm php8.3-bcmath php8.3-bz2 php8.3-curl php8.3-ds php8.3-gd php8.3-gmp php8.3-igbinary php8.3-imap php8.3-intl php8.3-mbstring php8.3-opcache php8.3-readline php8.3-redis php8.3-soap php8.3-swoole php8.3-uuid php8.3-xml php8.3-zip php8.3-sqlite3

# DB-specific PHP ext
if [[ "$DB_BACKEND" == "MariaDB" || "$DB_BACKEND" == "PostgreSQL" ]]; then
  case "$DB_BACKEND" in
    MariaDB) apt-get install -y php8.3-mysql ;;
    PostgreSQL) apt-get install -y php8.3-pgsql ;;
  esac
fi

# ---------- Secure PHP config ----------
log "Hardening PHP (opcache, sessions)…"
for INI in /etc/php/8.3/cli/php.ini /etc/php/8.3/fpm/php.ini; do
  [[ -f "$INI" ]] || continue
  sed -i 's/^;\?opcache.enable=.*/opcache.enable=1/' "$INI"
  sed -i 's/^;\?opcache.enable_cli=.*/opcache.enable_cli=1/' "$INI"
  if ! grep -q '^opcache.jit_buffer_size=' "$INI"; then echo "opcache.jit_buffer_size=100M" >> "$INI"; else sed -i 's/^opcache.jit_buffer_size=.*/opcache.jit_buffer_size=100M/' "$INI"; fi
  if ! grep -q '^opcache.jit=' "$INI"; then echo "opcache.jit=1255" >> "$INI"; else sed -i 's/^opcache.jit=.*/opcache.jit=1255/' "$INI"; fi

  sed -i 's/^;\?session.cookie_secure.*/session.cookie_secure = 1/' "$INI"
  sed -i 's/^;\?session.cookie_httponly.*/session.cookie_httponly = 1/' "$INI"
  if ! grep -q '^session.cookie_samesite' "$INI"; then echo 'session.cookie_samesite = "Strict"' >> "$INI"; else sed -i 's/^session.cookie_samesite.*/session.cookie_samesite = "Strict"/' "$INI"; fi
  if ! grep -q '^session.cookie_domain' "$INI"; then echo 'session.cookie_domain =' >> "$INI"; else sed -i 's/^session.cookie_domain.*/session.cookie_domain =/' "$INI"; fi
done

OPC_AVAIL="/etc/php/8.3/mods-available/opcache.ini"
if [[ -f "$OPC_AVAIL" ]]; then
  if ! grep -q '^opcache.jit=' "$OPC_AVAIL"; then echo "opcache.jit=1255" >> "$OPC_AVAIL"; else sed -i 's/^opcache.jit=.*/opcache.jit=1255/' "$OPC_AVAIL"; fi
  if ! grep -q '^opcache.jit_buffer_size=' "$OPC_AVAIL"; then echo "opcache.jit_buffer_size=100M" >> "$OPC_AVAIL"; else sed -i 's/^opcache.jit_buffer_size=.*/opcache.jit_buffer_size=100M/' "$OPC_AVAIL"; fi
fi

systemctl restart php8.3-fpm

# ---------- Caddy repo & install ----------
log "Installing Caddy…"
install -d -m 0755 /etc/apt/keyrings
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' -o /tmp/caddy-stable.gpg.key
gpg --dearmor -o /etc/apt/keyrings/caddy-stable-archive-keyring.gpg /tmp/caddy-stable.gpg.key
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
apt-get update -y
apt-get install -y caddy

# ---------- Adminer (randomized path) ----------
log "Installing Adminer…"
mkdir -p /usr/share/adminer
wget -q "https://www.adminer.org/latest.php" -O /usr/share/adminer/latest.php
ln -sf /usr/share/adminer/latest.php /usr/share/adminer/adminer.php
ADMINER_SLUG="adminer-$(tr -dc a-z0-9 </dev/urandom | head -c 8).php"
ln -sf /usr/share/adminer/latest.php "/usr/share/adminer/${ADMINER_SLUG}"

# ---------- Database setup ----------
case "$DB_BACKEND" in
  MariaDB)
    log "Configuring MariaDB repository…"
    install -d -m 0755 /etc/apt/keyrings
    curl -fsSL 'https://mariadb.org/mariadb_release_signing_key.pgp' -o /etc/apt/keyrings/mariadb-keyring.pgp

    if [[ "$OS_ID" == "ubuntu" ]]; then
      # MariaDB 11.4 for noble / 10.11 for jammy as in README
      if [[ "$OS_CODENAME" == "noble" ]]; then
        echo "deb [signed-by=/etc/apt/keyrings/mariadb-keyring.pgp] https://deb.mariadb.org/11.4/ubuntu noble main" > /etc/apt/sources.list.d/mariadb.list
      else
        cat > /etc/apt/sources.list.d/mariadb.sources <<EOF
X-Repolib-Name: MariaDB
Types: deb
URIs: https://deb.mariadb.org/10.11/ubuntu
Suites: ${OS_CODENAME}
Components: main
Signed-By: /etc/apt/keyrings/mariadb-keyring.pgp
EOF
      fi
    else
      # Debian bookworm/trixie -> 10.11 as per README (you can bump here if you wish)
      cat > /etc/apt/sources.list.d/mariadb.sources <<EOF
X-Repolib-Name: MariaDB
Types: deb
URIs: https://deb.mariadb.org/10.11/debian
Suites: ${OS_CODENAME}
Components: main
Signed-By: /etc/apt/keyrings/mariadb-keyring.pgp
EOF
    fi

    apt-get update -y
    log "Installing MariaDB server + client…"
    apt-get install -y mariadb-server mariadb-client
    log "Securing MariaDB (mysql_secure_installation)…"
    # Non-interactive secure setup: set root auth to unix_socket and remove test DB, etc.
    mysql --user=root <<'SQL' || true
ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket;
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;
SQL

    log "Creating database and user…"
    mysql --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ;;

  PostgreSQL)
    log "Installing PostgreSQL…"
    apt-get install -y postgresql
    systemctl enable --now postgresql
    log "Creating database and role…"
    sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO
$$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '$DB_USER') THEN
      CREATE ROLE "$DB_USER" LOGIN PASSWORD '$DB_PASS';
   END IF;
END
$$;
CREATE DATABASE "$DB_NAME" OWNER "$DB_USER";
GRANT ALL PRIVILEGES ON DATABASE "$DB_NAME" TO "$DB_USER";
SQL
    ;;

  SQLite)
    log "Using SQLite (no server install)."
    apt-get install -y sqlite3
    ;;
esac

# ---------- Create Loom project ----------
log "Creating Loom project in $INSTALL_PATH …"
mkdir -p "$INSTALL_PATH"
if [[ -z "$(ls -A "$INSTALL_PATH")" ]]; then
  composer create-project argora/loom "$INSTALL_PATH"
else
  warn "$INSTALL_PATH is not empty. Skipping composer create-project."
fi

# ---------- .env configuration ----------
log "Configuring .env …"
cd "$INSTALL_PATH"
if [[ ! -f ".env" ]]; then
  cp env-sample .env
fi

# DB DSN/env
case "$DB_BACKEND" in
  MariaDB)
    sed -i "s/^DB_DRIVER=.*/DB_DRIVER=mysql/" .env
    sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env
    sed -i "s/^DB_PORT=.*/DB_PORT=3306/" .env
    sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" .env
    sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" .env
    sed -i "s/^DB_PASS=.*/DB_PASS=${DB_PASS}/" .env
    ;;
  PostgreSQL)
    sed -i "s/^DB_DRIVER=.*/DB_DRIVER=pgsql/" .env
    sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env
    sed -i "s/^DB_PORT=.*/DB_PORT=5432/" .env
    sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" .env
    sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" .env
    sed -i "s/^DB_PASS=.*/DB_PASS=${DB_PASS}/" .env
    ;;
  SQLite)
    sed -i "s/^DB_DRIVER=.*/DB_DRIVER=sqlite/" .env
    sed -i "s/^DB_PATH=.*/DB_PATH=${INSTALL_PATH//\//\\/}\/storage\/loom.sqlite/" .env
    mkdir -p storage
    touch storage/loom.sqlite
    ;;
esac

# ---------- Permissions ----------
log "Setting permissions…"
mkdir -p logs cache /var/log/loom
chown -R www-data:www-data logs cache /var/log/loom
chmod -R 775 logs cache

# ---------- Install DB schema ----------
log "Running Loom DB installer…"
php bin/install-db.php

# ---------- Create admin user (best effort) ----------
log "Creating admin user (attempting non-interactive)…"
if php -v >/dev/null 2>&1; then
  set +e
  # Try to feed username/password via stdin (works if script reads from STDIN)
  printf "%s\n%s\n" "$ADMIN_USER" "$ADMIN_PASS" | php bin/create-admin-user.php >/tmp/loom-admin.log 2>&1
  CREATE_EXIT=$?
  set -e
  if [[ "$CREATE_EXIT" -ne 0 ]]; then
    warn "Automatic admin creation may have failed. Check /tmp/loom-admin.log"
    warn "If needed, run: php bin/create-admin-user.php  (and enter credentials manually)"
  fi
else
  warn "PHP CLI not found when creating admin (unexpected)."
fi

# ---------- Caddyfile ----------
log "Writing Caddyfile for $HOSTNAME …"
cat > /etc/caddy/Caddyfile <<EOF
$HOSTNAME {
$CADDY_BIND_LINE
    root * $INSTALL_PATH/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    encode zstd gzip
    file_server
    tls $TLS_EMAIL
    header -Server
    log {
        output file /var/log/loom/caddy.log
    }
    # Adminer (randomized path)
    route /${ADMINER_SLUG}* {
        root * /usr/share/adminer
        php_fastcgi unix//run/php/php8.3-fpm.sock
    }
    header * {
        Referrer-Policy "same-origin"
        Strict-Transport-Security max-age=31536000;
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
        Content-Security-Policy: default-src 'none'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; img-src https: data:; font-src 'self' data:; style-src 'self' 'unsafe-inline' https://rsms.me; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/; form-action 'self'; worker-src 'none'; frame-src 'none';
        Permissions-Policy: accelerometer=(), autoplay=(), camera=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(self), usb=();
    }
}
EOF

systemctl enable caddy
systemctl restart caddy

# ---------- Firewall ----------
log "Configuring UFW…"
ufw allow OpenSSH >/dev/null 2>&1 || true
ufw allow 80,443/tcp >/dev/null 2>&1 || true
yes | ufw enable >/dev/null 2>&1 || true
ufw status || true

# ---------- Summary ----------
cat <<SUM

============================================================
✅ Installation complete!

• App path:          $INSTALL_PATH
• Hostname:          https://$HOSTNAME
• PHP-FPM:           php8.3-fpm (running)
• Web server:        Caddy (running)
• Adminer URL:       https://$HOSTNAME/${ADMINER_SLUG}

• Database backend:  $DB_BACKEND
$( [[ "$DB_BACKEND" != "SQLite" ]] && echo "• DB Name/User:     $DB_NAME / $DB_USER" )
$( [[ "$DB_BACKEND" == "MariaDB" ]] && echo "• MySQL Tuning:     Run MySQLTuner later: perl mysqltuner.pl" )

• Admin user:        $ADMIN_USER  (created best-effort)
  If admin creation failed, run inside $INSTALL_PATH:
     php bin/create-admin-user.php

Pro tip: Add your domain's A/AAAA records to point at this server
and wait for DNS to propagate before first TLS issuance.

Logs:
  - Caddy:           /var/log/loom/caddy.log
  - Loom (app):      $INSTALL_PATH/logs

Enjoy! 🚀
============================================================
SUM