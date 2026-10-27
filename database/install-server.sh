```bash
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

echo "[5/8] Mengatur PHP 8.3 sebagai default..."

# Daftarkan PHP 8.3 sebagai alternatif CLI jika belum terdaftar.
update-alternatives --install \
    /usr/bin/php php /usr/bin/php8.3 83

# Jadikan PHP 8.3 sebagai default CLI.
update-alternatives --set php /usr/bin/php8.3

# Pastikan alternatif phar dan phpize tersedia jika paket menyediakannya.
if [[ -x /usr/bin/phar8.3 ]]; then
    update-alternatives --install \
        /usr/bin/phar phar /usr/bin/phar8.3 83 || true
    update-alternatives --set phar /usr/bin/phar8.3 || true
fi

# Aktifkan mod_php 8.3 dan nonaktifkan mod_php versi lain.
for conf in /etc/apache2/mods-enabled/php*.conf; do
    [[ -e "$conf" ]] || continue
    module="${conf##*/}"
    module="${module%.conf}"

    if [[ "$module" != "php8.3" ]]; then
        a2dismod "$module" || true
    fi
done

a2enmod php8.3

echo "[6/8] Install MySQL Server..."
apt-get install -y mysql-server
systemctl enable --now mysql

echo "[7/8] Konfigurasi MySQL dan install phpMyAdmin..."

# Jangan mengubah autentikasi MySQL root jika akses admin belum terverifikasi.
if mysql --protocol=socket -uroot -e "SELECT 1" >/dev/null 2>&1; then
    echo "Akses MySQL root melalui socket berhasil."

    # Catatan:
    # Tidak mengganti autentikasi root secara otomatis.
    # Ini mencegah perubahan autentikasi yang dapat mengunci akses admin
    # pada server yang sebelumnya sudah memiliki konfigurasi/database.
elif MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" \
    mysql --protocol=socket -uroot -e "SELECT 1" >/dev/null 2>&1; then
    echo "Akses MySQL root dengan password yang ditentukan berhasil."
else
    echo "PERINGATAN: Login MySQL root belum dapat diverifikasi."
    echo "Instalasi akan tetap melanjutkan phpMyAdmin."
    echo "Periksa akses admin MySQL secara manual setelah instalasi."
fi

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

# Aktifkan konfigurasi Apache phpMyAdmin jika tersedia.
if [[ -f /etc/phpmyadmin/apache.conf ]]; then
    if [[ ! -e /etc/apache2/conf-available/phpmyadmin.conf ]]; then
        ln -s /etc/phpmyadmin/apache.conf \
            /etc/apache2/conf-available/phpmyadmin.conf
    fi

    a2enconf phpmyadmin
fi

echo "[8/8] Mengaktifkan layanan dan verifikasi..."

a2enmod rewrite headers expires

# Pastikan PHP 8.3 tetap menjadi default setelah instalasi paket.
update-alternatives --set php /usr/bin/php8.3

# Uji konfigurasi sebelum restart.
apache2ctl configtest

systemctl enable --now apache2
systemctl enable --now mysql

systemctl restart apache2
systemctl restart mysql

echo
echo "=========================================="
echo " VERIFIKASI INSTALASI"
echo "=========================================="

echo "PHP CLI:"
php -v | head -n 2

echo
echo "PHP CLI path:"
command -v php

echo
echo "Ekstensi penting:"
php -m | grep -Ei 'redis|intl|pdo_mysql|mysqli|curl|mbstring|opcache' || true

echo
echo "Apache:"
apache2 -v | head -n 2

echo
echo "MySQL:"
mysql --version

echo
echo "Status Apache: $(systemctl is-active apache2)"
echo "Status MySQL : $(systemctl is-active mysql)"

echo
echo "Document root : /var/www/html"
echo "phpMyAdmin    : http://IP_SERVER/phpmyadmin"
echo "PHP default   : $(php -r 'echo PHP_VERSION;')"
echo "Log           : ${LOG_FILE}"

echo
echo "Instalasi selesai."
echo "Pastikan autentikasi MySQL root sudah dikonfigurasi sesuai kebutuhan."
echo "Batasi akses phpMyAdmin menggunakan firewall, VPN, atau kontrol akses."
```