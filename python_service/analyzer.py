from __future__ import annotations

import re
import socket
import ssl
from datetime import datetime, timezone
from typing import Any
from urllib.parse import urlparse

import requests
import whois
from bs4 import BeautifulSoup


SCAM_PHRASES = [
    "no experience needed",
    "work from home earn",
    "send your id",
    "processing fee",
    "guaranteed salary",
    "no interview",
    "apply via gmail",
    "earn per day",
    "weekly salary",
    "fast hiring no requirements",
]

PERSONAL_EMAIL_DOMAINS = {
    "gmail.com",
    "yahoo.com",
    "hotmail.com",
    "outlook.com",
    "live.com",
    "aol.com",
    "icloud.com",
    "proton.me",
    "protonmail.com",
}


def _clamp(value: float, low: int = 0, high: int = 100) -> int:
    return int(max(low, min(high, round(value))))


def _extract_domain(url: str) -> str:
    parsed = urlparse(url)
    return (parsed.hostname or "").lower()


def _safe_creation_date(date_value: Any) -> datetime | None:
    if isinstance(date_value, list) and date_value:
        date_value = date_value[0]
    if not isinstance(date_value, datetime):
        return None
    if date_value.tzinfo is None:
        return date_value.replace(tzinfo=timezone.utc)
    return date_value.astimezone(timezone.utc)


def fetch_job_html(url: str) -> str:
    try:
        response = requests.get(
            url,
            timeout=10,
            allow_redirects=True,
            headers={"User-Agent": "Mozilla/5.0"},
        )
        return response.text or ""
    except Exception:
        return ""


def get_domain_info(url: str) -> dict[str, Any]:
    domain = _extract_domain(url)
    result: dict[str, Any] = {
        "domain_age_years": None,
        "whois_registrant": "Unknown",
        "ssl_valid": False,
        "ssl_issuer": "Unknown",
        "redirect_count": 0,
    }

    if not domain:
        return result

    try:
        response = requests.get(
            url,
            timeout=8,
            allow_redirects=True,
            headers={"User-Agent": "Mozilla/5.0"},
        )
        result["redirect_count"] = len(response.history)
    except Exception:
        result["redirect_count"] = 0

    try:
        w = whois.whois(domain)
        created = _safe_creation_date(getattr(w, "creation_date", None))
        if created:
            age_days = (datetime.now(timezone.utc) - created).days
            result["domain_age_years"] = round(max(age_days, 0) / 365.25, 2)

        registrant = (
            getattr(w, "org", None)
            or getattr(w, "name", None)
            or getattr(w, "registrant_name", None)
            or "Unknown"
        )
        if isinstance(registrant, list) and registrant:
            registrant = registrant[0]
        result["whois_registrant"] = str(registrant)
    except Exception:
        result["domain_age_years"] = result.get("domain_age_years")
        result["whois_registrant"] = result.get("whois_registrant", "Unknown")

    try:
        context = ssl.create_default_context()
        with socket.create_connection((domain, 443), timeout=5) as sock:
            with context.wrap_socket(sock, server_hostname=domain) as tls_sock:
                cert = tls_sock.getpeercert()

        issuer_items = cert.get("issuer", [])
        issuer_parts: list[str] = []
        for item in issuer_items:
            for key, value in item:
                if key == "organizationName":
                    issuer_parts.append(str(value))
        result["ssl_issuer"] = ", ".join(issuer_parts) if issuer_parts else "Unknown"
        result["ssl_valid"] = True
    except Exception:
        result["ssl_valid"] = False
        result["ssl_issuer"] = "Unknown"

    return result


def analyze_job_content(html: str) -> dict[str, Any]:
    if not html:
        html = ""

    soup = BeautifulSoup(html, "html.parser")
    text = soup.get_text(" ", strip=True)
    lower_text = text.lower()

    words = re.findall(r"\b\w+\b", text)
    word_count = len(words)
    sentence_count = len(re.findall(r"[.!?]", text))
    long_word_count = sum(1 for w in words if len(w) >= 4)

    grammar_signal = 0.0
    if word_count > 0:
        grammar_signal += min(1.0, sentence_count / max(1, word_count / 18)) * 0.5
        grammar_signal += min(1.0, long_word_count / word_count) * 0.5
    grammar_score = _clamp(grammar_signal * 100)

    emails = re.findall(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}", text)
    contact_type = "unknown"
    if emails:
        domain = emails[0].split("@")[-1].lower()
        contact_type = "personal_email" if domain in PERSONAL_EMAIL_DOMAINS else "corporate_email"

    scam_phrases_matched = [phrase for phrase in SCAM_PHRASES if phrase in lower_text]

    warning_signals: list[str] = []
    authentic_signals: list[str] = []

    if word_count < 80:
        warning_signals.append("Short or low-detail job description")
    else:
        authentic_signals.append("Detailed job description present")

    if grammar_score < 55:
        warning_signals.append("Low grammar quality in posting")
    else:
        authentic_signals.append("Readable grammar quality")

    if contact_type == "personal_email":
        warning_signals.append("Uses personal email for job application")
    elif contact_type == "corporate_email":
        authentic_signals.append("Uses corporate email contact")

    if scam_phrases_matched:
        warning_signals.append("Contains known scam language patterns")
    else:
        authentic_signals.append("No known scam phrases detected")

    return {
        "desc_word_count": word_count,
        "grammar_score": grammar_score,
        "contact_type": contact_type,
        "scam_phrases_matched": scam_phrases_matched,
        "warning_signals": warning_signals,
        "authentic_signals": authentic_signals,
    }


def compute_subscores(domain_info: dict[str, Any], content_info: dict[str, Any]) -> dict[str, Any]:
    url_risk = 10
    age = domain_info.get("domain_age_years")
    if age is None:
        url_risk += 15
    elif age < 1:
        url_risk += 45
    elif age < 2:
        url_risk += 30
    elif age < 5:
        url_risk += 15

    if not domain_info.get("ssl_valid", False):
        url_risk += 25

    redirects = int(domain_info.get("redirect_count", 0) or 0)
    if redirects >= 3:
        url_risk += 20
    elif redirects == 2:
        url_risk += 10

    content_risk = 0
    phrase_count = len(content_info.get("scam_phrases_matched", []))
    content_risk += min(50, phrase_count * 15)

    grammar_score = int(content_info.get("grammar_score", 0) or 0)
    if grammar_score < 40:
        content_risk += 30
    elif grammar_score < 60:
        content_risk += 15

    word_count = int(content_info.get("desc_word_count", 0) or 0)
    if word_count < 80:
        content_risk += 20

    contact_type = str(content_info.get("contact_type", "unknown"))
    if contact_type == "personal_email":
        content_risk += 20

    registrant = str(domain_info.get("whois_registrant", "Unknown") or "Unknown").strip().lower()
    company_risk = 55 if registrant in {"", "unknown", "none"} else 20

    phrases = set(content_info.get("scam_phrases_matched", []))
    salary_risk = 15
    if any(p in phrases for p in {"guaranteed salary", "earn per day", "weekly salary", "work from home earn"}):
        salary_risk = 75

    application_risk = 10
    if any(p in phrases for p in {"send your id", "processing fee", "no interview", "apply via gmail"}):
        application_risk = 80

    url_risk = _clamp(url_risk)
    content_risk = _clamp(content_risk)
    company_risk = _clamp(company_risk)
    salary_risk = _clamp(salary_risk)
    application_risk = _clamp(application_risk)

    overall = _clamp(
        (0.25 * url_risk)
        + (0.30 * content_risk)
        + (0.15 * company_risk)
        + (0.15 * salary_risk)
        + (0.15 * application_risk)
    )

    observed_signals = len(content_info.get("warning_signals", [])) + (1 if age is not None else 0) + 1
    confidence_pct = _clamp(min(100, 35 + observed_signals * 9))

    if overall < 30:
        verdict = "SAFE"
        recommended_action = "Safe to apply. Verify company email before submitting personal data."
    elif overall <= 60:
        verdict = "CAUTION"
        recommended_action = "Proceed with caution. Verify the company independently before applying."
    else:
        verdict = "FRAUD"
        recommended_action = "Do not apply. Multiple fraud indicators detected."

    return {
        "url_risk": url_risk,
        "content_risk": content_risk,
        "company_risk": company_risk,
        "salary_risk": salary_risk,
        "application_risk": application_risk,
        "overall_threat_score": overall,
        "confidence_pct": confidence_pct,
        "verdict": verdict,
        "recommended_action": recommended_action,
    }