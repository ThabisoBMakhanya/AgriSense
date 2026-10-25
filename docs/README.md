# agriSense

agriSense is a PHP farm operations and weather guidance dashboard for Eswatini farmers. It stores accounts and weather snapshots in MySQL/MariaDB and keeps message history and SMS attempt logs in JSON/text files. It uses OpenWeatherMap data when configured and falls back to seasonal local data.

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

Automatic farm-operation summaries use the Lunyazi SMS API for Eswatini. Create an account at [Lunyazi](https://www.lunyazi.com/sms/), obtain an API key, and add `define('LUNYAZI_SMS_API_KEY', 'your-key');` to the existing ignored `includes/secrets.local.php` file. Keep the OpenWeatherMap definition already in that file. Farmers opt in from registration or their farm profile. The recommendation job sends at most one concise checklist summary per opted-in farmer every two hours, using the saved weather town and farm activity. Provider credits and carrier delivery rules apply. The app records provider submission; that confirms acceptance by the provider, not handset delivery.

On Windows, PHP must have a trusted CA bundle to connect to OpenWeatherMap over HTTPS. The app checks the configured PHP CA file, common XAMPP/PHP paths, and the Git for Windows certificate bundle; set `WEATHER_CA_BUNDLE` if your CA bundle is somewhere else. The project setup registers Task Scheduler jobs for weather refresh every 15 minutes and automatic recommendations every two hours. They run as the current Windows user while that user is signed in. If you need to recreate them, register `cron/fetch_weather.php` and `cron/generate_recommendations.php` with Task Scheduler using your PHP CLI executable and the project folder as the working directory.
