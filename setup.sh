#!/usr/bin/env bash
set -e
mkdir -p data logs ml_models assets/css assets/js
[ -f data/users.json ] || printf '{}' > data/users.json
[ -f data/messages.json ] || printf '[]' > data/messages.json
[ -f data/weather_cache.json ] || printf '{}' > data/weather_cache.json
python3 -m pip install -r ml_models/requirements.txt
python3 ml_models/train_models.py
printf 'Run: php -S localhost:8000\n'
