import json
import numpy as np
from sklearn.naive_bayes import CategoricalNB
from sklearn.preprocessing import LabelEncoder
import joblib
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_FILE = os.path.join(BASE_DIR, 'data', 'prior_knowledge.json')
MODEL_FILE = os.path.join(BASE_DIR, 'models', 'naive_bayes_model.joblib')
ENCODER_FILE = os.path.join(BASE_DIR, 'models', 'label_encoder.joblib')

with open(DATA_FILE, 'r') as f:
    data = json.load(f)

answer_map = {'A': 0, 'B': 1, 'C': 2}

X = []
y = []

for item in data:
    features = [answer_map[ans] for ans in item['jawaban']]
    X.append(features)
    y.append(item['profil'])

X = np.array(X)

le = LabelEncoder()
y_encoded = le.fit_transform(y)

model = CategoricalNB()
model.fit(X, y_encoded)

joblib.dump(model, MODEL_FILE)
joblib.dump(le, ENCODER_FILE)

print("Training selesai.")
print(f"Kelas: {le.classes_}")
print(f"Jumlah data training: {len(X)}")
print(f"Model disimpan: {MODEL_FILE}")
