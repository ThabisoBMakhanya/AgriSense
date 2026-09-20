# Technical notes

PHP owns authentication, JSON persistence, weather caching, and the dashboard. Passwords use `password_hash` and `password_verify`; sessions are regenerated after login. `ml_integration.php` sends JSON to `ml_models/run_ml.py` through `shell_exec`, while fallback calculations keep the UI functional if Python is unavailable.

The weather classifier is a Random Forest classifier, crop recommendations use a Random Forest classifier trained from crop climate ranges, and yield is a Random Forest regressor. Models are generated with `train_models.py` and persisted with joblib.
