import os
import re
import ssl
import socket
import datetime
import numpy as np
import requests
import whois
from bs4 import BeautifulSoup
from flask import Flask, request, jsonify
from flask_cors import CORS
import pickle
from urllib.parse import urlparse

app = Flask(__name__)
CORS(app)  # allow cross-origin requests from the Laravel frontend

# ── Load the AI brains ────────────────────────────────────────────────────────
base_dir   = os.path.dirname(__file__)
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


# ══════════════════════════════════════════════════════════════════════════════
#  HELPER UTILITIES
# ══════════════════════════════════════════════════════════════════════════════

def is_valid_http_url(url: str) -> bool:
    try:
        parsed = urlparse(url)
        return parsed.scheme in ('http', 'https') and bool(parsed.netloc)
    except Exception:
        return False


def get_hostname(url: str) -> str:
    return urlparse(url).netloc.split(':')[0]


def get_feature_importance_vector(model_obj, feature_count: int):
    if hasattr(model_obj, 'feature_importances_'):
        return np.asarray(model_obj.feature_importances_)
    if hasattr(model_obj, 'coef_'):
        coef = np.asarray(model_obj.coef_)
        return np.abs(coef[0]) if coef.ndim == 2 else np.abs(coef)
    return np.zeros(feature_count)


# ══════════════════════════════════════════════════════════════════════════════
#  STEP 1 — URL & DOMAIN ANALYSIS
# ══════════════════════════════════════════════════════════════════════════════

def analyze_url(url: str) -> dict:
    result = {}
    hostname = get_hostname(url)

    # 1a. SSL certificate check
    try:
        ctx = ssl.create_default_context()
        with ctx.wrap_socket(socket.socket(), server_hostname=hostname) as s:
            s.settimeout(5)
            s.connect((hostname, 443))
        result['ssl_valid'] = True
        result['ssl_flag']  = False
    except Exception:
        result['ssl_valid'] = False
        result['ssl_flag']  = True          # RED FLAG — no valid HTTPS

    # 1b. Domain age via WHOIS
    try:
        domain_info   = whois.whois(hostname)
        creation_date = domain_info.creation_date
        if isinstance(creation_date, list):
            creation_date = creation_date[0]
        age_days = (datetime.datetime.now() - creation_date).days
        result['domain_age_days'] = age_days
        result['domain_age_flag'] = age_days < 180  # < 6 months = RED FLAG
    except Exception:
        result['domain_age_days'] = None
        result['domain_age_flag'] = True            # Unknown = suspicious

    # 1c. Redirect chain check
    try:
        resp = requests.get(url, allow_redirects=True, timeout=10,
                            headers={'User-Agent': 'Mozilla/5.0'})
        result['redirect_count'] = len(resp.history)
        result['final_url']      = resp.url
        result['redirect_flag']  = len(resp.history) > 2
    except Exception:
        result['redirect_count'] = None
        result['final_url']      = url
        result['redirect_flag']  = True

    # 1d. Typosquatting — naive check against known legit job boards
    KNOWN_BOARDS = ['jobstreet', 'indeed', 'linkedin', 'kalibrr',
                    'jobsdb', 'monster', 'glassdoor', 'ph.jobstreet']
    hostname_lower = hostname.lower()
    result['typosquat_flag'] = not any(board in hostname_lower for board in KNOWN_BOARDS)

    return result


# ══════════════════════════════════════════════════════════════════════════════
#  STEP 2 — SCRAPE JOB CONTENT  (your existing Playwright scraper, unchanged)
# ══════════════════════════════════════════════════════════════════════════════

def scrape_text_from_url(url: str):
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            try:
                page = browser.new_page()
                page.set_extra_http_headers({
                    'User-Agent': (
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        'AppleWebKit/537.36 (KHTML, like Gecko) '
                        'Chrome/121.0.0.0 Safari/537.36'
                    )
                })
                page.goto(url, wait_until='networkidle', timeout=20000)
                html_content = page.content()
            finally:
                browser.close()

        soup  = BeautifulSoup(html_content, 'html.parser')
        lines = [tag.get_text() for tag in soup.find_all(['p', 'h1', 'h2', 'li'])]
        return ' '.join(lines)
    except Exception as e:
        print(f'[Scraper Error] {e}')
        return None


# ══════════════════════════════════════════════════════════════════════════════
#  STEP 3 — JOB CONTENT ANALYSIS (NLP keyword + pattern matching)
# ══════════════════════════════════════════════════════════════════════════════

FRAUD_KEYWORDS = [
    'no experience needed', 'earn fast', 'unlimited income',
    'work from home', 'apply now', 'limited slots', 'processing fee',
    'training fee', 'easy money', 'guaranteed income', 'high salary',
    'no interview', 'direct hire no experience', 'earn up to',
]

LEGIT_KEYWORDS = [
    'interview', 'qualifications', 'responsibilities', 'benefits',
    'company profile', 'submit your cv', 'job description', 'requirements',
    'annual leave', 'hmo', 'government mandated', 'probationary',
]

URGENCY_PATTERN  = re.compile(
    r'\b(now|urgent|immediately|hurry|limited|asap|today only|last chance)\b',
    re.IGNORECASE
)
PERSONAL_EMAIL   = re.compile(r'[\w.\-]+@(gmail|yahoo|hotmail|outlook)\.(com|ph)', re.IGNORECASE)
PAYMENT_PATTERN  = re.compile(r'\b(fee|payment|deposit|pay now|send money|bayad)\b', re.IGNORECASE)
SALARY_PATTERN   = re.compile(r'(₱|php)\s?[\d,]+\s?(\/day|per day)', re.IGNORECASE)
PHONE_ONLY       = re.compile(r'\b(09\d{9}|(\+63)\d{10})\b')


def analyze_content(text: str) -> dict:
    if not text:
        return {'error': 'No content scraped'}

    text_lower = text.lower()
    result = {}

    fraud_hits = [kw for kw in FRAUD_KEYWORDS if kw in text_lower]
    legit_hits = [kw for kw in LEGIT_KEYWORDS if kw in text_lower]

    result['fraud_keywords']       = fraud_hits
    result['legit_keywords']       = legit_hits
    result['fraud_keyword_count']  = len(fraud_hits)
    result['legit_keyword_count']  = len(legit_hits)

    urgency = URGENCY_PATTERN.findall(text)
    result['urgency_flag']         = len(urgency) > 2
    result['urgency_words']        = list(set(w.lower() for w in urgency))

    result['personal_email_flag']  = bool(PERSONAL_EMAIL.search(text))
    result['payment_flag']         = bool(PAYMENT_PATTERN.search(text))
    result['suspicious_salary']    = bool(SALARY_PATTERN.search(text))   # "₱5,000/day" type claims
    result['phone_only_contact']   = bool(PHONE_ONLY.search(text)) and not result.get('personal_email_flag')

    # Grammar/quality proxy — ratio of very short sentences
    sentences   = [s.strip() for s in re.split(r'[.!?]', text) if s.strip()]
    short_sents = [s for s in sentences if len(s.split()) < 4]
    result['low_quality_text']     = (len(short_sents) / max(len(sentences), 1)) > 0.6

    return result


# ══════════════════════════════════════════════════════════════════════════════
#  STEP 4 — COMPANY VERIFICATION
# ══════════════════════════════════════════════════════════════════════════════

def extract_company_name(text: str) -> str | None:
    """Very simple heuristic — looks for 'Company: Foo Inc' style patterns."""
    match = re.search(
        r'(?:company|employer|hiring company|posted by)[:\s]+([A-Z][A-Za-z0-9\s&.,]+)',
        text
    )
    return match.group(1).strip()[:60] if match else None


def verify_company(company_name: str | None) -> dict:
    if not company_name:
        return {'company_name': None, 'wikipedia_found': False, 'verified': False}

    result = {'company_name': company_name}

    # Wikipedia existence as a lightweight legitimacy proxy
    try:
        wiki_url  = f"https://en.wikipedia.org/wiki/{company_name.replace(' ', '_')}"
        resp      = requests.get(wiki_url, timeout=8,
                                 headers={'User-Agent': 'Mozilla/5.0'})
        result['wikipedia_found'] = resp.status_code == 200 and 'Wikipedia does not have' not in resp.text
    except Exception:
        result['wikipedia_found'] = False

    result['verified'] = result['wikipedia_found']
    return result


# ══════════════════════════════════════════════════════════════════════════════
#  STEP 5 — RISK SCORE CALCULATOR
# ══════════════════════════════════════════════════════════════════════════════

def calculate_risk_score(url_data: dict, content_data: dict, company_data: dict) -> dict:
    score = 0
    flags = []
    green_signals = []

    # ── URL signals ──────────────────────────────────────────────────────────
    if not url_data.get('ssl_valid'):
        score += 20;  flags.append('No SSL certificate (HTTP only)')
    else:
        green_signals.append('Valid SSL certificate (HTTPS)')

    domain_age = url_data.get('domain_age_days')
    if url_data.get('domain_age_flag'):
        score += 20
        label = f"Domain is new ({domain_age} days old)" if domain_age else "Domain age unknown"
        flags.append(label)
    else:
        green_signals.append(f"Domain established ({domain_age} days old)")

    if url_data.get('redirect_flag'):
        score += 10;  flags.append(f"Excessive redirects ({url_data.get('redirect_count')})")
    else:
        green_signals.append('No suspicious redirects')

    if url_data.get('typosquat_flag'):
        score += 15;  flags.append('Domain not a known job board')
    else:
        green_signals.append('Recognised job board domain')

    # ── Content signals ───────────────────────────────────────────────────────
    fkw_count = content_data.get('fraud_keyword_count', 0)
    lkw_count = content_data.get('legit_keyword_count', 0)

    score += fkw_count * 8
    if fkw_count:
        flags.append(f"Fraud keywords detected: {', '.join(content_data.get('fraud_keywords', []))}")
    if lkw_count:
        green_signals.append(f"Legitimate job phrases found: {', '.join(content_data.get('legit_keywords', [])[:4])}")

    if content_data.get('urgency_flag'):
        score += 10;  flags.append(f"Urgency language: {', '.join(content_data.get('urgency_words', []))}")

    if content_data.get('personal_email_flag'):
        score += 15;  flags.append('Contact is a personal email (Gmail/Yahoo)')
    else:
        green_signals.append('No personal email contact')

    if content_data.get('payment_flag'):
        score += 25;  flags.append('Payment or fee demand detected')
    else:
        green_signals.append('No payment demands found')

    if content_data.get('suspicious_salary'):
        score += 15;  flags.append('Unrealistically high daily salary mentioned')

    if content_data.get('low_quality_text'):
        score += 10;  flags.append('Low-quality or fragmented text detected')

    # ── Company signals ───────────────────────────────────────────────────────
    if company_data.get('verified'):
        green_signals.append(f"Company '{company_data.get('company_name')}' found on Wikipedia")
    elif company_data.get('company_name'):
        score += 10;  flags.append(f"Company '{company_data.get('company_name')}' not verified")
    else:
        score += 5;   flags.append('Could not identify company name from posting')

    score   = min(score, 100)
    verdict = 'Safe' if score < 40 else 'Medium Risk' if score < 70 else 'High Risk'

    return {
        'risk_score':     score,
        'verdict':        verdict,
        'flags':          flags,          # RED flags — reasons to be suspicious
        'green_signals':  green_signals,  # GREEN signals — reasons to trust
    }


# ══════════════════════════════════════════════════════════════════════════════
#  ROUTES
# ══════════════════════════════════════════════════════════════════════════════

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok'})


@app.route('/predict-url', methods=['POST'])
def predict_url():
    data    = request.get_json(silent=True) or {}
    job_url = data.get('url', '').strip()

    if not is_valid_http_url(job_url):
        return jsonify({'status': 'Error', 'message': 'Please provide a valid http/https URL'}), 400

    # ── 1. Scrape ─────────────────────────────────────────────────────────────
    raw_text = scrape_text_from_url(job_url)
    if not raw_text or len(raw_text.strip()) < 20:
        return jsonify({'status': 'Error', 'message': 'Could not read text from this link'}), 422

    # ── 2. AI model prediction (your existing logic — untouched) ──────────────
    try:
        vectorized_text  = tfidf.transform([raw_text]).toarray()
        fraud_prob       = model.predict_proba(vectorized_text)[0][1] * 100
        ai_score         = round(float(fraud_prob))

        feature_importance = get_feature_importance_vector(model, vectorized_text.shape[1])
        feature_vals       = vectorized_text[0]
        contrib            = feature_importance * feature_vals
        top_indices        = np.argsort(contrib)[-5:][::-1]
        feature_names      = tfidf.get_feature_names_out()
        ai_keywords        = [feature_names[i] for i in top_indices if contrib[i] > 0]

    except Exception as e:
        print(f'[Prediction Error] {e}')
        return jsonify({'status': 'Error', 'message': 'Prediction failed on server'}), 500

    # ── 3. New enrichment layers ──────────────────────────────────────────────
    url_data     = analyze_url(job_url)
    content_data = analyze_content(raw_text)
    company_name = extract_company_name(raw_text)
    company_data = verify_company(company_name)
    risk         = calculate_risk_score(url_data, content_data, company_data)

    # ── 4. Combine AI score + rule-based score into one final score ───────────
    # Weight: 60% AI model, 40% rule-based signals
    final_score  = round(ai_score * 0.6 + risk['risk_score'] * 0.4)
    final_score  = min(final_score, 100)
    final_status = 'Safe' if final_score < 40 else 'Medium Risk' if final_score < 70 else 'High Risk'

    return jsonify({
        # ── Core verdict ──────────────────────────────────────────────────────
        'status':         final_status,
        'url':            job_url,
        'score':          final_score,

        # ── AI model output (your original fields — unchanged) ────────────────
        'ai_score':       ai_score,
        'fraud_prob':     float(fraud_prob),
        'keywords':       ai_keywords,
        'explain':        f"Top indicators: {', '.join(ai_keywords[:3])}" if ai_keywords else 'No strong indicators.',

        # ── URL analysis ──────────────────────────────────────────────────────
        'url_analysis': {
            'ssl_valid':        url_data.get('ssl_valid'),
            'domain_age_days':  url_data.get('domain_age_days'),
            'redirect_count':   url_data.get('redirect_count'),
            'final_url':        url_data.get('final_url'),
            'known_job_board':  not url_data.get('typosquat_flag'),
        },

        # ── Content analysis ──────────────────────────────────────────────────
        'content_analysis': {
            'fraud_keywords':      content_data.get('fraud_keywords', []),
            'legit_keywords':      content_data.get('legit_keywords', []),
            'urgency_words':       content_data.get('urgency_words', []),
            'personal_email':      content_data.get('personal_email_flag'),
            'payment_demanded':    content_data.get('payment_flag'),
            'suspicious_salary':   content_data.get('suspicious_salary'),
        },

        # ── Company verification ──────────────────────────────────────────────
        'company': {
            'name':     company_data.get('company_name'),
            'verified': company_data.get('verified'),
        },

        # ── Human-readable evidence ───────────────────────────────────────────
        'red_flags':     risk['flags'],           # why it might be fraud
        'green_signals': risk['green_signals'],   # why it might be legit
    })


# ══════════════════════════════════════════════════════════════════════════════
if __name__ == '__main__':
    print('[app.py] __main__ reached, starting server...')
    try:
        app.run(port=int(os.getenv('PORT', '5000')), debug=True)
    except Exception as e:
        print(f'[app.py] server failed: {e}')
    print('[app.py] app.run() has returned and script is exiting')