def calculate_risk_score(url_data, content_data, company_data):
    score = 0  # starts at 0 (safe), goes up with red flags
    flags = []

    # URL Checks (each flag adds to fraud score)
    if not url_data.get("ssl_valid"):
        score += 20
        flags.append("No SSL certificate")

    if url_data.get("domain_age_flag"):
        score += 20
        flags.append("Domain is new or unknown")

    if url_data.get("redirect_flag"):
        score += 10
        flags.append("Too many redirects")

    # Content Checks
    score += content_data.get("fraud_keyword_count", 0) * 10
    if content_data.get("fraud_keyword_count", 0) > 0:
        flags.append(f"Fraud keywords: {content_data['fraud_keywords']}")

    if content_data.get("urgency_flag"):
        score += 10
        flags.append("Urgency language detected")

    if content_data.get("personal_email_flag"):
        score += 15
        flags.append("Personal email (Gmail/Yahoo) used")

    if content_data.get("payment_flag"):
        score += 25
        flags.append("Payment demand detected")

    # Company Check
    if not company_data.get("wikipedia_found"):
        score += 10
        flags.append("Company not found on Wikipedia")

    # Clamp score to 100
    score = min(score, 100)

    verdict = "SAFE" if score < 40 else "SUSPICIOUS" if score < 70 else "FRAUD"

    return {
        "risk_score": score,
        "verdict": verdict,
        "flags": flags
    }
