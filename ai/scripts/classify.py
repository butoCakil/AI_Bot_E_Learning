import sys
import json
import numpy as np
import joblib
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_FILE = os.path.join(BASE_DIR, 'models', 'naive_bayes_model.joblib')
ENCODER_FILE = os.path.join(BASE_DIR, 'models', 'label_encoder.joblib')

def classify_learning_needs(jawaban_sjt):
    model = joblib.load(MODEL_FILE)
    le = joblib.load(ENCODER_FILE)

    answer_map = {'A': 0, 'B': 1, 'C': 2}
    features = np.array([[answer_map[ans] for ans in jawaban_sjt]])

    pred_encoded = model.predict(features)
    pred_proba = model.predict_proba(features)[0]

    profil = le.inverse_transform(pred_encoded)[0]
    probabilitas = {
        le.classes_[i]: round(float(pred_proba[i]), 4)
        for i in range(len(le.classes_))
    }

    return profil, probabilitas

def classify_level(skor):
    if skor <= 4:
        return 'beginner'
    elif skor <= 8:
        return 'intermediate'
    else:
        return 'advanced'

def tiebreaker(jawaban_sjt):
    counts = {'A': 0, 'B': 0, 'C': 0}
    for ans in jawaban_sjt[:4]:
        counts[ans] += 1
    profil_map = {'A': 'guided_step', 'B': 'conceptual', 'C': 'practice_oriented'}
    return profil_map[max(counts, key=counts.get)]

if __name__ == '__main__':
    try:
        input_data = json.loads(sys.argv[1])
        jawaban_sjt = input_data['sjt']
        skor_pengetahuan = int(input_data['skor'])

        profil_learning, probabilitas = classify_learning_needs(jawaban_sjt)
        level = classify_level(skor_pengetahuan)
        profil_gabungan = f"{level}_{profil_learning}"

        result = {
            'status': 'ok',
            'level': level,
            'profil_learning': profil_learning,
            'profil_gabungan': profil_gabungan,
            'probabilitas': probabilitas,
            'skor_pengetahuan': skor_pengetahuan
        }

        print(json.dumps(result))

    except Exception as e:
        error = {'status': 'error', 'message': str(e)}
        print(json.dumps(error))
