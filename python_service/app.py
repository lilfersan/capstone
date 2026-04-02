import os
import requests
import numpy as np
from bs4 import BeautifulSoup
from flask import Flask, request, jsonify
from flask_cors import CORS
import pickle

app = Flask(__name__)
CORS(app)  # allow cross‑origin requests from the Laravel frontend

# Load the AI brains using paths relative to this script
base_dir = os.path.dirname(__file__)
model_path = os.path.join(base_dir, 'model.pkl')
tfidf_path = os.path.join(base_dir, 'tfidf.pkl')
model = pickle.load(open(model_path, 'rb'))
tfidf = pickle.load(open(tfidf_path, 'rb'))


from playwright.sync_api import sync_playwright

def scrape_text_from_url(url):
    try:
        # Launch a headless Chromium browser using Playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            page = browser.new_page()
            
            # Pretend to be a real browser heavily
            page.set_extra_http_headers({'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'})
            
            # Go to the URL and wait until the network is quiet (avoids reading empty skeletons)
            page.goto(url, wait_until="networkidle", timeout=20000)
            
            # Grab the fully loaded HTML that the user would see
            html_content = page.content()
            browser.close()
            
            # Parse it the same way as before
            soup = BeautifulSoup(html_content, 'html.parser')
            lines = [p.get_text() for p in soup.find_all(['p', 'h1', 'h2', 'li'])]
            return " ".join(lines)
    except Exception as e:
        print(f"[Scraper Error] {e}")
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
    vectorized_text = tfidf.transform([raw_text]).toarray()
    pred = model.predict(vectorized_text)[0]
    fraud_prob = model.predict_proba(vectorized_text)[0][1] * 100
    score = round(fraud_prob)
    
    # Top fraud keywords (feature importance * feature value)
    feature_importance = model.feature_importances_
    feature_vals = vectorized_text[0]
    contrib = feature_importance * feature_vals
    top_indices = np.argsort(contrib)[-5:][::-1]
    fraud_keywords = [tfidf.get_feature_names_out()[i] for i in top_indices if contrib[i] > 0]
    
    status = "Safe" if score < 40 else "Medium Risk" if score < 70 else "High Risk"
    
    return jsonify({
        'status': status,
        'url': job_url,
        'score': score,
        'fraud_prob': fraud_prob,
        'keywords': fraud_keywords,
        'explain': f"Top indicators: {', '.join(fraud_keywords[:3])}" if fraud_keywords else "No strong indicators."
    })

if __name__ == '__main__':
    # Start the engine on port 5000
    print("[app.py] __main__ reached, starting server...")
    try:
        app.run(port=5000, debug=True)
    except Exception as e:
        print(f"[app.py] server failed: {e}")
    print("[app.py] app.run() has returned and script is exiting")