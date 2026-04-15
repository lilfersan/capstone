from typing import Any
import re
from urllib.parse import urlparse

from fastapi import FastAPI
from pydantic import BaseModel, HttpUrl, model_validator

from job_scam_analyzer import JobScamAnalyzer


app = FastAPI(title="Fake Job Posting Detection API", version="1.0.0")
analyzer = JobScamAnalyzer()


class AnalyzeRequest(BaseModel):
    url: HttpUrl


class ValidationRequest(BaseModel):
    email: str | None = None
    domain: str | None = None
    url: HttpUrl | None = None

    @model_validator(mode="after")
    def check_at_least_one_value(self):
        if not self.email and not self.domain and not self.url:
            raise ValueError("Provide at least one of: email, domain, or url")
        return self


class ValidationResponse(BaseModel):
    email: str | None
    email_is_valid: bool | None
    email_domain: str | None
    domain: str | None
    domain_is_valid: bool
    is_disposable_domain: bool
    recommendation: str


EMAIL_PATTERN = re.compile(r"^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$")
DOMAIN_PATTERN = re.compile(
    r"^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$",
    re.IGNORECASE,
)

DISPOSABLE_DOMAINS = {
    "mailinator.com",
    "10minutemail.com",
    "guerrillamail.com",
    "temp-mail.org",
    "yopmail.com",
}


def normalize_domain(value: str) -> str:
    val = value.strip().lower()
    if "@" in val:
        val = val.split("@")[-1]
    if "://" in val:
        parsed = urlparse(val)
        val = (parsed.hostname or "").lower()
    else:
        # If this is a bare host with path, keep only host part.
        val = val.split("/")[0]
    return val.strip(".")


def validate_email(email: str | None) -> tuple[bool | None, str | None]:
    if not email:
        return None, None
    value = email.strip()
    if not EMAIL_PATTERN.match(value):
        return False, None
    return True, normalize_domain(value)


def validate_domain(domain: str | None, url: str | None, email_domain: str | None) -> str:
    if domain:
        return normalize_domain(domain)
    if url:
        return normalize_domain(str(url))
    return email_domain or ""


def domain_is_valid(domain: str) -> bool:
    if not domain:
        return False
    return bool(DOMAIN_PATTERN.match(domain))


@app.post("/analyze")
def analyze_job_posting(payload: AnalyzeRequest) -> dict[str, Any]:
    # Return the analyzer's full structured output as requested.
    return analyzer.analyze(str(payload.url))


@app.post("/validate", response_model=ValidationResponse)
def validate_email_domain(payload: ValidationRequest) -> ValidationResponse:
    email_valid, email_domain = validate_email(payload.email)
    domain = validate_domain(payload.domain, str(payload.url) if payload.url else None, email_domain)
    valid_domain = domain_is_valid(domain)
    disposable = domain in DISPOSABLE_DOMAINS if domain else False

    if email_valid is False:
        recommendation = "Email format is invalid. Ask user to provide a valid business email."
    elif not valid_domain:
        recommendation = "Domain format is invalid or missing. Verify the domain before proceeding."
    elif disposable:
        recommendation = "Domain appears disposable. Treat this contact as higher risk."
    else:
        recommendation = "Email/domain format looks valid. Continue with additional scam checks."

    return ValidationResponse(
        email=payload.email,
        email_is_valid=email_valid,
        email_domain=email_domain,
        domain=domain or None,
        domain_is_valid=valid_domain,
        is_disposable_domain=disposable,
        recommendation=recommendation,
    )
