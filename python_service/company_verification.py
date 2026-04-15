import requests


def verify_company(company_name):
    result = {}

    # Check Wikipedia existence (simple proxy for legitimacy)
    try:
        search_url = f"https://en.wikipedia.org/wiki/{company_name.replace(' ', '_')}"
        response = requests.get(search_url, timeout=10)
        result["wikipedia_found"] = response.status_code == 200
    except:
        result["wikipedia_found"] = False

    return result
