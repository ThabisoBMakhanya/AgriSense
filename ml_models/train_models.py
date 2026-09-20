from weather_classifier import train as train_weather
from crop_recommender import train as train_crops
from yield_predictor import train as train_yield
if __name__=='__main__':
    train_weather(); train_crops(); train_yield(); print('Trained weather classifier, crop recommender, and yield predictor.')
