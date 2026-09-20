import os, joblib, numpy as np
from sklearn.ensemble import RandomForestRegressor
MODEL=os.path.join(os.path.dirname(__file__),'yield_model.pkl')
def train():
    x=[]; y=[]
    for temp in range(15,36,2):
        for rain in range(30,151,20):
            for humidity in (40,60,80):
                for soil in range(1,5):
                    x.append([temp,rain,humidity,soil,1]); y.append(soil*1150+max(0,180-abs(rain-90)*4)-abs(temp-25)*35)
    model=RandomForestRegressor(n_estimators=100,random_state=3).fit(x,y); joblib.dump(model,MODEL); return model
def predict_yield(data):
    model=joblib.load(MODEL) if os.path.exists(MODEL) else train(); farm=float(data.get('farm_size',1)); values=[[float(data['temperature']),float(data['rainfall']),float(data['humidity']),float(data['soil_quality']),farm]]; per=round(float(model.predict(values)[0])); status='Excellent' if per>=5500 else 'Good' if per>=4000 else 'Average' if per>=2500 else 'Below Average'; return {'yield_kg_ha':per,'total_yield_kg':round(per*farm,1),'status':status}
if __name__=='__main__': train()
