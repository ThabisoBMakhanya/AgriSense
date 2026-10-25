import json, sys, os
try:
    from weather_classifier import classify_weather
    from crop_recommender import recommend_crops
    from yield_predictor import predict_yield
except Exception:
    classify_weather = recommend_crops = predict_yield = None

def main():
    if len(sys.argv) > 2 and sys.argv[1] == '--request-file':
        with open(sys.argv[2], 'r', encoding='utf-8') as request_file:
            request = json.load(request_file)
    elif len(sys.argv) > 1:
        request = json.loads(sys.argv[1])
    else:
        raise ValueError('Expected a JSON request or --request-file <path>.')
    data=request.get('input',{}); action=request.get('action')
    if action=='weather' and classify_weather: result=classify_weather(data)
    elif action=='crops' and recommend_crops: result={'recommendations':recommend_crops(data)}
    elif action=='yield' and predict_yield: result=predict_yield(data)
    else: result={}
    print(json.dumps(result))
if __name__=='__main__': main()
