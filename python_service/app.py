import os
import numpy as np
from bs4 import BeautifulSoup
from flask import Flask, request, jsonify
from flask_cors import CORS
import pickle
from urllib.parse import urlparse

app = Flask(__name__)
CORS(app)  # allow cross‑origin requests from the Laravel frontend

# Load the AI brains using paths relative to this script
base_dir = os.path.dirname(__file__)
model_path = os.path.join(base_dir, 'model.pkl')
tfidf_path = os.path.join(base_dir, 'tfidf.pkl')


def load_pickle(path):
    if not os.path.exists(path):
        raise FileNotFoundError(f"Missing required file: {path}")
    with open(path, 'rb') as f:
        return pickle.load(f)


model = load_pickle(model_path)
tfidf = load_pickle(tfidf_path)


from playwright.sync_api import sync_playwright


def is_valid_http_url(url):
    try:
        parsed = urlparse(url)
        return parsed.scheme in ('http', 'https') and bool(parsed.netloc)
    except Exception:
        return False


def get_feature_importance_vector(model_obj, feature_count):
    if hasattr(model_obj, 'feature_importances_'):
        return np.asarray(model_obj.feature_importances_)
    if hasattr(model_obj, 'coef_'):
        coef = np.asarray(model_obj.coef_)
        if coef.ndim == 2:
            return np.abs(coef[0])
        return np.abs(coef)
    return np.zeros(feature_count)


def scrape_text_from_url(url):
    try:
        # Launch a headless Chromium browser using Playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            try:
                page = browser.new_page()

                # Pretend to be a real browser heavily
                page.set_extra_http_headers({'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'})

                # Go to the URL and wait until the network is quiet (avoids reading empty skeletons)
                page.goto(url, wait_until="networkidle", timeout=20000)

                # Grab the fully loaded HTML that the user would see
                html_content = page.content()
            finally:
                browser.close()

            # Parse it the same way as before
            soup = BeautifulSoup(html_content, 'html.parser')
            lines = [p_tag.get_text() for p_tag in soup.find_all(['p', 'h1', 'h2', 'li'])]
            return " ".join(lines)
    except Exception as e:
        print(f"[Scraper Error] {e}")
        return None


@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok'})


@app.route('/predict-url', methods=['POST'])
def predict_url():
    data = request.get_json(silent=True) or {}
    job_url = data.get('url', '').strip()

    if not is_valid_http_url(job_url):
        return jsonify({'status': 'Error', 'message': 'Please provide a valid http/https URL'}), 400
    
    # 1. Scrape the website
    raw_text = scrape_text_from_url(job_url)
    
    if not raw_text or len(raw_text.strip()) < 20:
        return jsonify({'status': 'Error', 'message': 'Could not read text from this link'}), 422

    # 2. Let the AI analyze the text
    try:
        vectorized_text = tfidf.transform([raw_text]).toarray()
        fraud_prob = model.predict_proba(vectorized_text)[0][1] * 100
        score = round(float(fraud_prob))

        # Top fraud keywords (feature importance * feature value)
        feature_importance = get_feature_importance_vector(model, vectorized_text.shape[1])
        feature_vals = vectorized_text[0]
        contrib = feature_importance * feature_vals
        top_indices = np.argsort(contrib)[-5:][::-1]
        feature_names = tfidf.get_feature_names_out()
        fraud_keywords = [feature_names[i] for i in top_indices if contrib[i] > 0]

        status = "Safe" if score < 40 else "Medium Risk" if score < 70 else "High Risk"

        return jsonify({
            'status': status,
            'url': job_url,
            'score': score,
            'fraud_prob': float(fraud_prob),
            'keywords': fraud_keywords,
            'explain': f"Top indicators: {', '.join(fraud_keywords[:3])}" if fraud_keywords else "No strong indicators."
        })
    except Exception as e:
        print(f"[Prediction Error] {e}")
        return jsonify({'status': 'Error', 'message': 'Prediction failed on server'}), 500

if __name__ == '__main__':
    # Start the engine on port 5000
    print("[app.py] __main__ reached, starting server...")
    try:
        app.run(port=int(os.getenv('PORT', '5000')), debug=True)
    except Exception as e:
        print(f"[app.py] server failed: {e}")
    print("[app.py] app.run() has returned and script is exiting")
