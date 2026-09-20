# Kudla kwesive

Kudla kwesive is a PHP agricultural advisory dashboard for Eswatini farmers. It stores accounts and SMS simulations in JSON, uses regional OpenWeatherMap data when configured, and falls back to seasonal local data.

## Run locally

1. Install PHP 7.4+ and Python 3.7+.
2. From the project folder, run `python -m pip install -r ml_models/requirements.txt`.
3. Run `python ml_models/train_models.py` to generate model files.
4. Add an OpenWeatherMap key to `WEATHER_API_KEY` in `includes/config.php` (optional).
5. Start PHP with `php -S localhost:8000` and open `http://localhost:8000`.

The application works without Python packages or an API key using deterministic fallbacks. JSON and log directories must be writable by PHP.
