"""Small standard-library inference used by the Vercel ML endpoint."""

import math


CROPS = {
    "Maize": (18, 32, 50, 120),
    "Sorghum": (22, 35, 30, 80),
    "Beans": (18, 28, 60, 100),
    "Sweet Potatoes": (20, 30, 80, 150),
    "Cabbage": (15, 28, 70, 120),
    "Groundnuts": (25, 35, 30, 70),
    "Cassava": (22, 34, 70, 140),
    "Citrus": (18, 30, 40, 90),
    "Sugarcane": (25, 35, 80, 150),
    "Cotton": (20, 35, 50, 100),
    "Pineapple": (22, 32, 60, 120),
    "Mango": (20, 35, 40, 100),
}


def _fit(value, low, high, padding):
    if low <= value <= high:
        return 1.0
    distance = low - value if value < low else value - high
    return max(0.0, 1.0 - distance / padding)


def classify_weather(data):
    score = sum((
        15 <= float(data["temperature"]) <= 32,
        float(data["humidity"]) >= 40,
        float(data["rainfall"]) > 2,
        float(data["wind_speed"]) < 15,
    ))
    labels = ("poor", "moderate", "good", "excellent")
    classification = labels[min(score, len(labels) - 1)]
    confidence = min(95, 65 + 8 * abs(score - 1.5))
    return {
        "classification": classification,
        "confidence": round(confidence),
        "model": "weather-lightweight-v1",
    }


def recommend_crops(data):
    temperature = float(data["temperature"])
    rainfall = float(data["rainfall"])
    humidity = float(data["humidity"])
    ranked = []
    for crop, (temp_low, temp_high, rain_low, rain_high) in CROPS.items():
        temp_fit = _fit(temperature, temp_low, temp_high, 12)
        rain_fit = _fit(rainfall, rain_low, rain_high, 45)
        humidity_fit = _fit(humidity, 45, 85, 30)
        fit = temp_fit * 0.45 + rain_fit * 0.4 + humidity_fit * 0.15
        confidence = round(45 + fit * 50)
        ranked.append((fit, crop, confidence))

    ranked.sort(reverse=True)
    return [
        {
            "crop": crop,
            "confidence": confidence,
            "reason": "Fit estimated from this place's temperature, rainfall, and humidity.",
        }
        for _fit_score, crop, confidence in ranked[:3]
    ]


def predict_yield(data):
    temperature = float(data["temperature"])
    rainfall = float(data["rainfall"])
    soil_quality = float(data["soil_quality"])
    farm_size = float(data.get("farm_size", 1))
    per_hectare = round(soil_quality * 1150 + max(0, 180 - abs(rainfall - 90) * 4) - abs(temperature - 25) * 35)
    status = "Excellent" if per_hectare >= 5500 else "Good" if per_hectare >= 4000 else "Average" if per_hectare >= 2500 else "Below Average"
    return {
        "yield_kg_ha": per_hectare,
        "total_yield_kg": round(per_hectare * farm_size, 1),
        "status": status,
        "model": "yield-lightweight-v1",
    }
