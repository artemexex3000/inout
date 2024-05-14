# Inout

### Config app

- rename .env.example to .env and edit it:

```dotenv
APP_URL='domain/tunnel where Inout app places'

REDIS_HOST=
REDIS_PASSWORD=
REDIS_PORT=
REDIS_DB=

DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

GOOGLE_SPREADSHEET_URI='spreadsheet unique URI, example: 1YY5LNxidmbRarjRQk47NcgJ0UrfnDl4FVA22XVV7BFg'
```
- go to console.cloud.google.com, make and download credentials in json file and place it into the root of project (/project_root/credentials.json).
- go to console and enter some commands:
```console
composer install
php artisan migrate
php artisan telegraph:new-bot // enter id of your bot, you can skip other questions
php artisan telegraph:set-webhook
```
