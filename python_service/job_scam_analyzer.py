from urllib.parse import urlparse
import re
from datetime import datetime, date
import os
import json
from urllib import request, error


def get_domain_age(url: str) -> dict:
    """Get domain creation date and age in days using python-whois."""
    hostname = (urlparse(url).hostname or "").lower()
    if not hostname:
        return {
            "creation_date": "unknown",
            "domain_age_in_days": "unknown",
        }


def check_google_safe_browsing(url: str, api_key: str | None = None) -> dict:
    """Check URL against Google Safe Browsing API and return threat status."""
    key = api_key or os.getenv("GOOGLE_SAFE_BROWSING_API_KEY", "")
    if not key:
        return {
            "status": "not_configured",
            "is_malicious": "unknown",
            "threat_matches": [],
            "notes": "Set GOOGLE_SAFE_BROWSING_API_KEY to enable real-time malicious URL checks.",
        }

    endpoint = f"https://safebrowsing.googleapis.com/v4/threatMatches:find?key={key}"
    payload = {
        "client": {
            "clientId": "job-scam-detector",
            "clientVersion": "1.0.0",
        },
        "threatInfo": {
            "threatTypes": [
                "MALWARE",
                "SOCIAL_ENGINEERING",
                "UNWANTED_SOFTWARE",
                "POTENTIALLY_HARMFUL_APPLICATION",
            ],
            "platformTypes": ["ANY_PLATFORM"],
            "threatEntryTypes": ["URL"],
            "threatEntries": [{"url": url}],
        },
    }

    req = request.Request(
        endpoint,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    try:
        with request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode("utf-8") or "{}")

        matches = data.get("matches", [])
        compact_matches = [
            {
                "threatType": m.get("threatType", "unknown"),
                "platformType": m.get("platformType", "unknown"),
                "threatEntryType": m.get("threatEntryType", "unknown"),
            }
            for m in matches
        ]

        return {
            "status": "ok",
            "is_malicious": bool(matches),
            "threat_matches": compact_matches,
        }
    except (error.URLError, TimeoutError, json.JSONDecodeError):
        return {
            "status": "error",
            "is_malicious": "unknown",
            "threat_matches": [],
            "notes": "Google Safe Browsing lookup failed. Check API key/network quota.",
        }

    try:
        import whois

        w = whois.whois(hostname)
        creation = w.creation_date

        # Some WHOIS providers return a list of dates; use the earliest non-null.
        if isinstance(creation, list):
            creation_values = [c for c in creation if c]
            if not creation_values:
                raise ValueError("Missing creation date")
            creation = min(creation_values)

        if isinstance(creation, datetime):
            creation_dt = creation
        elif isinstance(creation, date):
            creation_dt = datetime.combine(creation, datetime.min.time())
        else:
            raise ValueError("Unsupported creation date format")

        age_days = (datetime.utcnow() - creation_dt).days

        return {
            "creation_date": creation_dt.date().isoformat(),
            "domain_age_in_days": age_days,
        }
    except Exception:
        return {
            "creation_date": "unknown",
            "domain_age_in_days": "unknown",
        }


def check_suspicious_url(url: str) -> dict:
    """Check common suspicious URL patterns and return flag dictionary."""
    parsed = urlparse(url)
    hostname = (parsed.hostname or "").lower()

    shorteners = ("bit.ly", "tinyurl.com")
    uses_shortener = any(hostname == s or hostname.endswith(f".{s}") for s in shorteners)

    # Flag numeric-heavy or random-looking token patterns in URL path/query.
    url_body = f"{parsed.path} {parsed.query}".lower()
    has_numeric_token = bool(re.search(r"\b[a-z0-9]*\d[a-z0-9]*\b", url_body))
    has_random_string = bool(re.search(r"\b[a-z0-9]{12,}\b", url_body))

    return {
        "contains_numbers_or_random_strings": has_numeric_token or has_random_string,
        "uses_url_shortener": uses_shortener,
        "uses_http_not_https": parsed.scheme.lower() == "http",
    }


def extract_company_and_verify(url: str, page_title: str | None = None) -> dict:
    """Extract a probable company name and simulate verification status."""
    parsed = urlparse(url)
    hostname = (parsed.hostname or "").lower()

    known_job_platforms = (
        "linkedin.com",
        "indeed.com",
        "glassdoor.com",
        "monster.com",
        "ziprecruiter.com",
        "jobstreet.com",
    )

    company_name = "unknown"

    if page_title:
        # Common title patterns: "Role at Company", "Company Careers", "Company | Jobs"
        title = re.sub(r"\s+", " ", page_title).strip()
        if " at " in title.lower():
            company_name = title.split(" at ")[-1].split("|")[0].split("-")[0].strip()
        elif " careers" in title.lower():
            company_name = re.split(r"(?i) careers", title)[0].strip()
        else:
            company_name = title.split("|")[0].split("-")[0].strip()

    if company_name == "unknown" or not company_name:
        # Fallback to domain token as a rough company name.
        domain_part = hostname.split(".")[0] if hostname else "unknown"
        company_name = domain_part.replace("-", " ").replace("_", " ").strip() or "unknown"

    verification_status = (
        "likely valid"
        if any(hostname == d or hostname.endswith(f".{d}") for d in known_job_platforms)
        else "unknown"
    )

    return {
        "company_name": company_name,
        "verification_status": verification_status,
    }


def detect_scam_indicators(text: str = "", url: str = "") -> dict:
    """Detect common scam indicators from free text and URL content."""
    corpus = f"{text} {url}".lower()

    payment_terms = ("pay", "fee", "registration")
    urgency_terms = ("urgent hiring", "limited slots")
    unrealistic_terms = ("earn quickly",)

    return {
        "payment_request_detected": any(term in corpus for term in payment_terms),
        "urgency_detected": any(term in corpus for term in urgency_terms),
        "unrealistic_promises": any(term in corpus for term in unrealistic_terms),
    }


def compute_risk_score(
    suspicious_domain: bool,
    payment_request: bool,
    urgency_language: bool,
    unknown_company: bool,
) -> dict:
    """Compute a 0-100 scam risk score and class label from indicator flags."""
    score = 0

    if suspicious_domain:
        score += 30
    if payment_request:
        score += 40
    if urgency_language:
        score += 15
    if unknown_company:
        score += 15

    score = max(0, min(score, 100))

    if score <= 30:
        classification = "Safe"
    elif score <= 70:
        classification = "Suspicious"
    else:
        classification = "High Risk"

    return {
        "risk_score": score,
        "classification": classification,
    }


def generate_human_readable_explanations(
    suspicious_domain: bool,
    domain_age_in_days,
    payment_request_detected: bool,
    urgency_detected: bool,
    unrealistic_promises: bool,
    verification_status: str,
) -> dict:
    """Generate user-friendly reasoning from scam signal flags."""
    top_risk_factors = []
    positive_signals = []

    if suspicious_domain:
        top_risk_factors.append("Domain appears suspicious based on URL/domain indicators")
    else:
        positive_signals.append("Domain does not match common suspicious URL patterns")

    if isinstance(domain_age_in_days, int):
        if domain_age_in_days < 90:
            top_risk_factors.append("Domain is newly registered")
        else:
            positive_signals.append("Domain has been registered for a longer period")
    elif domain_age_in_days == "unknown":
        top_risk_factors.append("Domain registration age could not be verified")

    if payment_request_detected:
        top_risk_factors.append("Payment request language detected")
    else:
        positive_signals.append("No payment request detected")

    if urgency_detected:
        top_risk_factors.append("Urgency language detected (e.g., urgent hiring, limited slots)")
    else:
        positive_signals.append("No strong urgency pressure language detected")

    if unrealistic_promises:
        top_risk_factors.append("Unrealistic earning promises detected")
    else:
        positive_signals.append("No unrealistic earning promises detected")

    if verification_status == "unknown":
        top_risk_factors.append("Company/source verification status is unknown")
    else:
        positive_signals.append("Source appears on a known job platform")

    return {
        "top_risk_factors": top_risk_factors,
        "positive_signals": positive_signals,
    }


class JobScamAnalyzer:
    """Placeholder analyzer for fake job posting risk assessment."""

    def analyze_domain(self, url: str) -> dict:
        """Mock domain analysis based on simple URL heuristics."""
        parsed = urlparse(url)
        hostname = (parsed.hostname or "").lower()

        suspicious_tlds = (".xyz", ".click", ".top", ".loan", ".work", ".buzz")

        return {
            "hostname": hostname,
            "is_https": parsed.scheme == "https",
            "domain_age_days_estimate": 45 if any(hostname.endswith(tld) for tld in suspicious_tlds) else 720,
            "whois_privacy_enabled": True,
            "suspicious_tld": any(hostname.endswith(tld) for tld in suspicious_tlds),
            "notes": "Mock domain intelligence used. Replace with real WHOIS/domain APIs.",
        }

    def detect_scam_patterns(self, url: str) -> dict:
        """Mock scam pattern detection from URL tokens."""
        url_lower = url.lower()

        keyword_flags = {
            "urgent_hiring": "urgent" in url_lower,
            "upfront_payment": any(k in url_lower for k in ("fee", "payment", "deposit")),
            "chat_app_contact": any(k in url_lower for k in ("telegram", "whatsapp")),
            "too_good_salary": any(k in url_lower for k in ("easy-income", "quick-money", "high-pay")),
        }

        triggered = [name for name, hit in keyword_flags.items() if hit]

        return {
            "flags": keyword_flags,
            "triggered_patterns": triggered,
            "pattern_count": len(triggered),
            "notes": "Mock text/pattern analysis. Replace with NLP model inference.",
        }

    def _analyze_content(self, url: str) -> dict:
        """Mock content analysis output placeholder."""
        return {
            "has_company_profile": "linkedin" in url.lower(),
            "grammar_quality_score": 0.74,
            "salary_realism_score": 0.42,
            "contact_transparency_score": 0.35,
            "notes": "Mock content analysis. Replace with scraper + classifier outputs.",
        }

    def generate_risk_score(self, results: dict) -> dict:
        """Combine mock signals into a final risk score."""
        score = 0

        domain = results["domain_analysis"]
        patterns = results["scam_indicators"]
        content = results["content_analysis"]
        safe_browsing = results.get("safe_browsing", {})

        if not domain["is_https"]:
            score += 15
        if domain["suspicious_tld"]:
            score += 20
        if domain["domain_age_days_estimate"] < 90:
            score += 15

        score += patterns["pattern_count"] * 10

        if content["salary_realism_score"] < 0.5:
            score += 15
        if content["contact_transparency_score"] < 0.5:
            score += 10

        if safe_browsing.get("is_malicious") is True:
            score += 40

        score = min(score, 100)

        if score >= 70:
            level = "high"
        elif score >= 40:
            level = "medium"
        else:
            level = "low"

        return {
            "score": score,
            "level": level,
            "confidence": 0.68,
            "notes": "Confidence is mocked. Replace with model probability calibration.",
        }

    def analyze(self, url: str) -> dict:
        """Run end-to-end placeholder analysis and return structured result."""
        domain_analysis = self.analyze_domain(url)
        scam_indicators = self.detect_scam_patterns(url)
        content_analysis = self._analyze_content(url)
        safe_browsing = check_google_safe_browsing(url)

        domain_analysis["google_safe_browsing"] = safe_browsing

        results = {
            "domain_analysis": domain_analysis,
            "scam_indicators": scam_indicators,
            "content_analysis": content_analysis,
            "safe_browsing": safe_browsing,
        }

        final_risk = self.generate_risk_score(results)

        return {
            "domain_analysis": domain_analysis,
            "scam_indicators": scam_indicators,
            "content_analysis": content_analysis,
            "safe_browsing": safe_browsing,
            "final_risk_score": final_risk,
        }


if __name__ == "__main__":
    analyzer = JobScamAnalyzer()
    sample = analyzer.analyze("http://urgent-hiring-fast-cash.click/jobs/quick-money")
    print(sample)
