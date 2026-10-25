import os, joblib
from functools import lru_cache
from sklearn.ensemble import RandomForestClassifier
MODEL=os.path.join(os.path.dirname(__file__),'weather_model.pkl')

def _fit_model():
    rows=[]
    for temp in range(10,41,3):
        for humidity in (25,45,65,85):
            for rain in (0,5,15,30):
                wind=4+(temp%7); score=(temp>=15 and temp<=32)+(humidity>=40)+(rain>2)+(wind<15)
                rows.append(([temp,humidity,rain,wind],['poor','moderate','good','excellent'][score-1]))
    return RandomForestClassifier(n_estimators=80,random_state=7).fit([x for x,y in rows],[y for x,y in rows])

def train():
    model=_fit_model(); joblib.dump(model,MODEL); return model

@lru_cache(maxsize=1)
def _model():
    return joblib.load(MODEL) if os.path.exists(MODEL) else _fit_model()

def classify_weather(data):
    model=_model(); values=[[float(data['temperature']),float(data['humidity']),float(data['rainfall']),float(data['wind_speed'])]]; prediction=model.predict(values)[0]; confidence=round(max(model.predict_proba(values)[0])*100); return {'classification':prediction,'confidence':confidence,'model':'weather-random-forest-v1'}
if __name__=='__main__': train(); print(classify_weather({'temperature':26,'humidity':60,'rainfall':8,'wind_speed':5}))
