#!/bin/sh
# Initialisation idempotente du backend Laravel, exécutée à chaque démarrage
# du conteneur "app". Chaque étape ne fait quelque chose que si nécessaire :
# relancer `docker compose up` ne réinstalle ni ne re-seede rien.
set -e

cd /var/www/html

# 1. Dépendances PHP (vendor/ est ignoré par git).
if [ ! -f vendor/autoload.php ]; then
    echo "[init] composer install..."
    composer install --no-interaction --no-progress --prefer-dist
fi

# 2. Fichier .env (valeurs de démo ; DB_* sont aussi injectées par docker-compose).
if [ ! -f .env ]; then
    echo "[init] copie de .env.example vers .env"
    cp .env.example .env
fi

# 3. Clé d'application.
if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[init] php artisan key:generate"
    php artisan key:generate --force --no-interaction
fi

# 4. Droits d'écriture pour php-fpm (www-data) sans changer le propriétaire des fichiers.
chmod -R ugo+rwX storage bootstrap/cache

# 5. Migrations (la base est prête : depends_on attend le healthcheck MySQL).
php artisan migrate --force --no-interaction

# 6. Données de démonstration, uniquement si la base est vide.
if [ "$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n 1)" = "0" ]; then
    echo "[init] php artisan db:seed (données de démo)"
    php artisan db:seed --force --no-interaction
fi

# 7. Lien public/storage -> storage/app/public (preuves de livraison).
if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

php artisan config:clear --no-interaction >/dev/null

touch /tmp/allo-ready
echo "[init] backend prêt"

exec "$@"
