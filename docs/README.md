# agriSense

agriSense is a PHP farm operations and weather guidance dashboard for Eswatini farmers. Production accounts, weather, SMS attempts, and farm journal data use Neon PostgreSQL. The farm journal also keeps an IndexedDB offline copy and syncs edits when the device reconnects. It uses OpenWeatherMap data when configured and falls back to seasonal local data.

## Run locally

1. Install PHP 7.4+ with PDO MySQL enabled, Python 3.7+, and MySQL or MariaDB.
2. Create a database (for example, `kudla_kwesive`) and run [`database/schema.sql`](../database/schema.sql) against it. On an existing installation, run [`database/upgrade_weather_locations.sql`](../database/upgrade_weather_locations.sql) to add the more precise weather location field.
3. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` as environment variables, or set the corresponding constants in `includes/config.php`. Keep credentials out of source control.
4. (Optional) Import accounts previously stored in `data/users.json` by running `php scripts/import_legacy_users.php` once after configuring the connection and creating the schema. The importer leaves existing database accounts unchanged.
5. From the project folder, run `python -m pip install -r ml_models/requirements.txt` and `python ml_models/train_models.py` to generate model files.
6. Add an OpenWeatherMap key to the ignored `includes/secrets.local.php` file (optional).
7. Start PHP with `php -S localhost:8000` and open `http://localhost:8000`.

At registration, farmers choose a region for regional crop guidance and a nearby weather town for localized forecasts. The available towns include Mbabane, Pigg’s Peak, Mankayane, Manzini, Matsapha, Malkerns, Bhunya, Nhlangano, Hlathikhulu, Lavumisa, Siteki, Big Bend, Mhlume, and Simunye. Farmers can change the weather town from their profile. JSON and log directories must be writable by PHP. Authentication requires the configured database and schema.

The dashboard builds a weather-aware operations checklist for the farmer's selected activity. Crop farmers also see crop-fit and yield estimates from the Python models. Fertilizer and crop-treatment guidance stays tied to soil tests and local extension recommendations.

## Live weather and crop prediction models

Set `OPENWEATHER_API_KEY` in the environment or keep `WEATHER_API_KEY` in the ignored `includes/secrets.local.php`. The weather service requests current conditions and a five-day forecast for each Eswatini region, derives the highest forecast rain probability over the next 24 hours, and caches the snapshot for 15 minutes in `weather_cache`. On XAMPP for Windows it uses Apache's CA bundle to verify HTTPS; set `WEATHER_CA_BUNDLE` if your XAMPP installation is in another location. ML inference reads the cached snapshot. Configure the database environment settings and apply `database/schema.sql` to create weather, model, training, and prediction audit tables. On an existing database, apply `database/upgrade_ml.sql` to add farmer category, soil type, and growth stage fields before using the updated registration or profile forms.

Install Composer, then run `composer install`, `php ml/generate_training_data.php`, and `php ml/train_model.php`. The generator creates 1,200 synthetic labeled examples with 12% label noise. Training uses a shuffled 80/20 holdout split, saves `ml/models/recommendation.model`, and prints holdout accuracy, a confusion matrix, and a sample prediction with its feature vector. Synthetic data is only a pipeline demonstration; validate labels with local agronomists and field observations before relying on advisories for production decisions.

The separate Rubix ML advisory model is an optional experiment and does not control the daily operations checklist shown to farmers.

Run `php cron/fetch_weather.php` every 15 minutes and `php cron/generate_recommendations.php` on the desired advice cadence. The recommendation job sends a short summary of the same weather-aware farm-operation tasks shown on the dashboard. Without a weather API key, the dashboard can use its seasonal preview fallback, which is not live weather.

## SMS delivery

Urgent weather alerts use the Lunyazi SMS API for Eswatini. Production secrets are configured in Vercel. Farmers opt in from registration or their farm profile. The Neon Function Trigger `agrisense-sms-every-2-hours` checks saved weather towns every two hours and sends only for high-risk rain, wind, heat, or cold conditions; duplicate alerts of the same type are held for six hours. The bridge function is in `neon-functions/smscron`. Provider credits and carrier delivery rules apply. The app records provider submission; that confirms acceptance by the provider, not handset delivery.

The farm journal adds separate Crops & vegetables and Animals views, task reminders, scoped expense/sales/harvest tracking, photo-based pest and disease reports, livestock/poultry logs, input inventory with low-stock/expiry flags, and harvest yield by field. Farm profiles can register multiple crops, animal groups, and other enterprises in `farm_enterprises`. The journal tables are defined in `database/upgrade_farm_journal.sql`; `database/upgrade_journal_domains.sql` adds category columns to shared journal records; `database/upgrade_farm_enterprises.sql` adds and backfills registered farm enterprises. Run those migrations after the base Neon schema, or use the matching MySQL migration files for a local database. Offline journal edits queue in IndexedDB and sync to Neon when the farmer is signed in and reconnected.

On Windows, PHP must have a trusted CA bundle to connect to OpenWeatherMap over HTTPS. The app checks the configured PHP CA file, common XAMPP/PHP paths, and the Git for Windows certificate bundle; set `WEATHER_CA_BUNDLE` if your CA bundle is somewhere else. The project setup registers Task Scheduler jobs for weather refresh every 15 minutes and automatic recommendations every two hours. They run as the current Windows user while that user is signed in. If you need to recreate them, register `cron/fetch_weather.php` and `cron/generate_recommendations.php` with Task Scheduler using your PHP CLI executable and the project folder as the working directory.
