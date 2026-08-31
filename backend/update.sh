php artisan migrate
php artisan seed:run --permissions-only
yarn
yarn build
php artisan optimize:clear
chmod -R 777 storage
chmod -R 777 bootstrap/cache
echo "Done ------------"
