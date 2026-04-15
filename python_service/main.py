from __future__ import annotations

from typing import Any

from fastapi import FastAPI
from pydantic import BaseModel, HttpUrl

from analyzer import analyze_job_content, compute_subscores, fetch_job_html, get_domain_info


app = FastAPI(title="Fake Job Posting Detection API", version="2.0.0")


class AnalyzeRequest(BaseModel):
	url: HttpUrl


@app.get("/health")
def health() -> dict[str, str]:
	return {"status": "ok"}


@app.post("/analyze")
def analyze(payload: AnalyzeRequest) -> dict[str, Any]:
	target_url = str(payload.url)

	domain_info = get_domain_info(target_url)
	html = fetch_job_html(target_url)
	content_info = analyze_job_content(html)
	subscores = compute_subscores(domain_info, content_info)

	score = int(subscores.get("overall_threat_score", 0))
	verdict = str(subscores.get("verdict", "SAFE"))
	recommendation = str(subscores.get("recommended_action", "Analysis complete."))

	status = {
		"FRAUD": "High Risk",
		"CAUTION": "Medium Risk",
		"SAFE": "Safe",
	}.get(verdict, "Safe")

	return {
		"url": target_url,
		**domain_info,
		**content_info,
		**subscores,
		# Backward-compatible legacy fields
		"threat_score": score,
		"key_phrases": content_info.get("scam_phrases_matched", []),
		"risk_score": score,
		"score": score,
		"status": status,
		"recommendation": recommendation,
		"explain": recommendation,
		"keywords": content_info.get("scam_phrases_matched", []),
	}
