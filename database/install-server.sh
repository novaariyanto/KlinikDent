
#!/usr/bin/env bash
set -Eeuo pipefail

LOG_FILE="/var/log/install-lamp.log"
MYSQL_ROOT_PASSWORD='Toor100%'

exec > >(tee -a "$LOG_FILE") 2>&1

trap 'rc=$?; echo "[ERROR] Gagal pada baris ${BASH_LINENO[0]:-unknown}, exit ${rc}. Log: ${LOG_FILE}"; exit "$rc"' ERR

if [[ "$EUID" -ne 0 ]]; then
    echo "Jalankan dengan: sudo bash install-lamp.sh"
    exit 1
fi

. /etc/os-release

if [[ "$ID" != "ubuntu" ]]; then
    echo "Script ini hanya untuk Ubuntu."
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "=========================================="
echo " Instalasi Apache2 + PHP 8.3 + MySQL"
echo " phpMyAdmin"
echo " OS: ${PRETTY_NAME}"
echo "=========================================="

echo "[1/8] Update paket..."
apt-get update

echo "[2/8] Install Apache dan utilitas..."
apt-get install -y \
    apache2 software-properties-common \
    ca-certificates curl unzip zip git \
    gnupg lsb-release debconf-utils

echo "[3/8] Menambahkan repository PHP..."
if ! grep -Rqs "ondrej/php" \
    /etc/apt/sources.list /etc/apt/sources.list.d 2>/dev/null; then
    add-apt-repository -y ppa:ondrej/php
fi
apt-get update

echo "[4/8] Install PHP 8.3 dan ekstensi..."
apt-get install -y \
    php8.3 \
    php8.3-cli \
    libapache2-mod-php8.3 \
    php8.3-common \
    php8.3-mysql \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-gd \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-redis \
    php8.3-soap \
    php8.3-opcache \
    php8.3-readline

echo "[5/8] Install MySQL Server..."
apt-get install -y mysql-server
systemctl enable --now mysql

echo "[6/8] Konfigurasi password MySQL root..."

# Coba autentikasi socket yang umum digunakan Ubuntu.
if mysql --protocol=socket -uroot -e "SELECT 1" >/dev/null 2>&1; then

    mysql --protocol=socket -uroot <<SQL
ALTER USER 'root'@'localhost'
IDENTIFIED WITH caching_sha2_password BY '${MYSQL_ROOT_PASSWORD}';
FLUSH PRIVILEGES;
SQL

# Jika password sudah sesuai, tidak perlu mengubahnya lagi.
elif MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" \
    mysql --protocol=socket -uroot -e "SELECT 1" >/dev/null 2>&1; then

    echo "Password MySQL root sudah sesuai."

else
    echo "ERROR: Tidak dapat login sebagai MySQL root."
    echo "Script berhenti untuk melindungi database yang sudah ada."
    echo "Periksa akses dengan: sudo mysql"
    echo "Jika MySQL sudah pernah dikonfigurasi, gunakan kredensial admin yang valid."
    exit 1
fi

echo "[7/8] Install phpMyAdmin..."

# Nonaktifkan konfigurasi database otomatis phpMyAdmin
# agar instalasi tidak meminta password interaktif.
printf '%s\n' \
    'phpmyadmin phpmyadmin/dbconfig-install boolean false' \
    'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' \
    | debconf-set-selections

apt-get install -y \
    phpmyadmin \
    php8.3-mbstring \
    php8.3-mysql \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip

# Aktifkan konfigurasi Apache phpMyAdmin.
if [[ -f /etc/phpmyadmin/apache.conf ]]; then
    if [[ ! -e /etc/apache2/conf-available/phpmyadmin.conf ]]; then
        ln -s /etc/phpmyadmin/apache.conf \
            /etc/apache2/conf-available/phpmyadmin.conf
    fi

    a2enconf phpmyadmin
fi

echo "[8/8] Mengaktifkan layanan..."

a2enmod rewrite headers expires

systemctl enable --now apache2
systemctl enable --now mysql

systemctl restart apache2
systemctl restart mysql

echo
echo "=========================================="
echo " VERIFIKASI INSTALASI"
echo "=========================================="

php -v | head -n 2
echo
php -m
echo
apache2 -v | head -n 2
mysql --version

echo
echo "Apache: $(systemctl is-active apache2)"
echo "MySQL:  $(systemctl is-active mysql)"
echo
echo "Document root : /var/www/html"
echo "phpMyAdmin    : http://IP_SERVER/phpmyadmin"
echo "MySQL user    : root"
echo "MySQL password: ${MYSQL_ROOT_PASSWORD}"
echo "Log           : ${LOG_FILE}"
echo
echo "Instalasi selesai."
echo "Akses root MySQL hanya untuk localhost."
echo "Batasi akses phpMyAdmin dengan firewall atau VPN."
