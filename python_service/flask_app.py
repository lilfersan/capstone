from flask import Flask, request, jsonify

from url_analysis import analyze_url
from scraper import scrape_job_content
from content_analysis import analyze_content
from company_verification import verify_company
from risk_scoring import calculate_risk_score

app = Flask(__name__)


@app.route("/analyze", methods=["POST"])
def analyze():
    data = request.json
    url = data.get("url")

    url_data = analyze_url(url)
    job_text = scrape_job_content(url)
    content_data = analyze_content(job_text)
    company_data = verify_company("extracted_company_name")  # extract from content
    risk = calculate_risk_score(url_data, content_data, company_data)

    return jsonify({
        "url_analysis": url_data,
        "content_analysis": content_data,
        "company_analysis": company_data,
        "risk_assessment": risk
    })


if __name__ == "__main__":
    app.run(debug=True)
