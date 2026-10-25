import os, joblib
from functools import lru_cache
from sklearn.ensemble import RandomForestClassifier
MODEL=os.path.join(os.path.dirname(__file__),'crop_model.pkl')
CROPS={'Maize':(18,32,50,120),'Sorghum':(22,35,30,80),'Beans':(18,28,60,100),'Sweet Potatoes':(20,30,80,150),'Cabbage':(15,28,70,120),'Groundnuts':(25,35,30,70),'Cassava':(22,34,70,140),'Citrus':(18,30,40,90),'Sugarcane':(25,35,80,150),'Cotton':(20,35,50,100),'Pineapple':(22,32,60,120),'Mango':(20,35,40,100)}
def _fit_model():
    x=[]; y=[]
    for crop,(a,b,c,d) in CROPS.items():
        for i in range(8): x.append([(a+b)/2+(i-3), (c+d)/2+(i-3)*3, 55+i*3, i%4]); y.append(crop)
    return RandomForestClassifier(n_estimators=100,random_state=4).fit(x,y)

def train():
    model=_fit_model(); joblib.dump(model,MODEL); return model

@lru_cache(maxsize=1)
def _model():
    return joblib.load(MODEL) if os.path.exists(MODEL) else _fit_model()

def recommend_crops(data):
    model=_model(); values=[[float(data['temperature']),float(data['rainfall']),float(data['humidity']),0]]; probabilities=model.predict_proba(values)[0]; ranked=sorted(zip(model.classes_,probabilities),key=lambda z:z[1],reverse=True)[:3]; return [{'crop':crop,'confidence':round(prob*100),'reason':'Model fit using temperature, rainfall, and humidity.'} for crop,prob in ranked]
if __name__=='__main__': train()
