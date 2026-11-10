import hmac
import json
import math
import os
from http.server import BaseHTTPRequestHandler

from ml_models.lightweight_inference import recommend_crops, classify_weather, predict_yield


MAX_REQUEST_BYTES = 65536
REQUIRED_FIELDS = {
    "weather": ("temperature", "humidity", "rainfall", "wind_speed"),
    "crops": ("temperature", "rainfall", "humidity"),
    "yield": ("temperature", "rainfall", "humidity", "soil_quality", "farm_size"),
}


class handler(BaseHTTPRequestHandler):
    def _respond(self, status, payload):
        body = json.dumps(payload, allow_nan=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        expected_token = os.environ.get("ML_API_TOKEN", "")
        supplied_token = self.headers.get("Authorization", "")
        supplied_token = supplied_token.removeprefix("Bearer ")
        if not expected_token or not hmac.compare_digest(supplied_token, expected_token):
            self._respond(401, {"error": "Unauthorized"})
            return

        try:
            content_length = int(self.headers.get("Content-Length", "0"))
            if content_length < 1 or content_length > MAX_REQUEST_BYTES:
                self._respond(413, {"error": "Invalid request size"})
                return
            payload = json.loads(self.rfile.read(content_length))
            action = payload.get("action")
            data = payload.get("input")
            if action not in REQUIRED_FIELDS or not isinstance(data, dict):
                self._respond(400, {"error": "Invalid model request"})
                return
            for field in REQUIRED_FIELDS[action]:
                value = float(data[field])
                if not math.isfinite(value):
                    raise ValueError("Non-finite model input")

            if action == "weather":
                result = classify_weather(data)
            elif action == "crops":
                result = {"recommendations": recommend_crops(data)}
            else:
                result = predict_yield(data)
            self._respond(200, result)
        except (KeyError, TypeError, ValueError, json.JSONDecodeError):
            self._respond(400, {"error": "Model input is incomplete or invalid"})
        except Exception:
            self._respond(500, {"error": "Model inference failed"})

    def log_message(self, _format, *_args):
        return
