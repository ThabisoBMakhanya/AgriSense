import os, joblib
from functools import lru_cache
from sklearn.ensemble import RandomForestRegressor
MODEL=os.path.join(os.path.dirname(__file__),'yield_model.pkl')
def _fit_model():
    x=[]; y=[]
    for temp in range(15,36,2):
        for rain in range(30,151,20):
            for humidity in (40,60,80):
                for soil in range(1,5):
                    x.append([temp,rain,humidity,soil,1]); y.append(soil*1150+max(0,180-abs(rain-90)*4)-abs(temp-25)*35)
    return RandomForestRegressor(n_estimators=100,random_state=3).fit(x,y)

def train():
    model=_fit_model(); joblib.dump(model,MODEL); return model

@lru_cache(maxsize=1)
def _model():
    return joblib.load(MODEL) if os.path.exists(MODEL) else _fit_model()

def predict_yield(data):
    model=_model(); farm=float(data.get('farm_size',1)); values=[[float(data['temperature']),float(data['rainfall']),float(data['humidity']),float(data['soil_quality']),farm]]; per=round(float(model.predict(values)[0])); status='Excellent' if per>=5500 else 'Good' if per>=4000 else 'Average' if per>=2500 else 'Below Average'; return {'yield_kg_ha':per,'total_yield_kg':round(per*farm,1),'status':status,'model':'yield-random-forest-v1'}
if __name__=='__main__': train()
