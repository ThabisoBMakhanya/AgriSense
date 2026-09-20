import json, sys, os
try:
    from weather_classifier import classify_weather
    from crop_recommender import recommend_crops
    from yield_predictor import predict_yield
except Exception:
    classify_weather = recommend_crops = predict_yield = None

def main():
    request=json.loads(sys.argv[1]); data=request.get('input',{}); action=request.get('action')
    if action=='weather' and classify_weather: result=classify_weather(data)
    elif action=='crops' and recommend_crops: result={'recommendations':recommend_crops(data)}
    elif action=='yield' and predict_yield: result=predict_yield(data)
    else: result={}
    print(json.dumps(result))
if __name__=='__main__': main()
