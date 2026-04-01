import os
import requests
from bs4 import BeautifulSoup
from flask import Flask, request, jsonify
from flask_cors import CORS
import pickle

app = Flask(__name__)
CORS(app)  # allow cross‑origin requests from the Laravel frontend

# Load the AI brains using paths relative to this script
base_dir = os.path.dirname(__file__)
model_path = os.path.join(base_dir, 'model.pkl')
vectorizer_path = os.path.join(base_dir, 'vectorizer.pkl')
model = pickle.load(open(model_path, 'rb'))
cv = pickle.load(open(vectorizer_path, 'rb'))

def scrape_text_from_url(url):
    try:
        # Pretend to be a browser so the website doesn't block us
        headers = {'User-Agent': 'Mozilla/5.0'}
        response = requests.get(url, headers=headers, timeout=10)
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Pull all text from paragraphs and headers
        lines = [p.get_text() for p in soup.find_all(['p', 'h1', 'h2', 'li'])]
        return " ".join(lines)
    except:
        return None

@app.route('/predict-url', methods=['POST'])
def predict_url():
    data = request.get_json()
    job_url = data.get('url')
    
    # 1. Scrape the website
    raw_text = scrape_text_from_url(job_url)
    
    if not raw_text or len(raw_text.strip()) < 20:
        return jsonify({'status': 'Error', 'message': 'Could not read text from this link'})

    # 2. Let the AI analyze the text
    vectorized_text = cv.transform([raw_text])
    prediction = model.predict(vectorized_text)[0]
    
    # 3. Send the result back (0 = Safe, 1 = High Risk).
    # convert to a 0‑100 scale so the frontend gauge still works
    score = int(prediction * 100)
    status = "High Risk" if prediction == 1 else "Safe"
    return jsonify({
        'status': status,
        'url': job_url,
        'score': score
    })

if __name__ == '__main__':
    # Start the engine on port 5000
    print("[app.py] __main__ reached, starting server...")
    try:
        app.run(port=5000, debug=True)
    except Exception as e:
        print(f"[app.py] server failed: {e}")
    print("[app.py] app.run() has returned and script is exiting")