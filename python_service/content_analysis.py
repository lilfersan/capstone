import spacy
import re

nlp = spacy.load("en_core_web_sm")

FRAUD_KEYWORDS = [
    "no experience needed", "earn fast", "unlimited income",
    "work from home", "apply now", "limited slots", "processing fee",
    "training fee", "send resume via gmail", "easy money", "guaranteed income"
]

LEGIT_KEYWORDS = [
    "interview", "qualifications", "responsibilities", "benefits",
    "company profile", "submit your cv", "job description", "requirements"
]

def analyze_content(text):
    if not text:
        return {"error": "No content found"}

    text_lower = text.lower()
    result = {}

    # Fraud keyword hits
    fraud_hits = [kw for kw in FRAUD_KEYWORDS if kw in text_lower]
    legit_hits = [kw for kw in LEGIT_KEYWORDS if kw in text_lower]

    result["fraud_keywords"] = fraud_hits
    result["legit_keywords"] = legit_hits
    result["fraud_keyword_count"] = len(fraud_hits)
    result["legit_keyword_count"] = len(legit_hits)

    # Urgency language detection
    urgency_patterns = r'\b(now|urgent|immediately|hurry|limited|asap|today only)\b'
    urgency_matches = re.findall(urgency_patterns, text_lower)
    result["urgency_flag"] = len(urgency_matches) > 2
    result["urgency_words"] = urgency_matches

    # Email type detection
    gmail_flag = bool(re.search(r'[\w.-]+@(gmail|yahoo|hotmail)\.com', text_lower))
    result["personal_email_flag"] = gmail_flag

    # Payment demand detection
    payment_flag = bool(re.search(r'\b(fee|payment|deposit|pay|send money)\b', text_lower))
    result["payment_flag"] = payment_flag

    return result
