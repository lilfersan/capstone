import whois
import requests
import datetime
import ssl, socket


def analyze_url(url):
    result = {}

    # 1. SSL Check
    try:
        hostname = url.replace("https://", "").replace("http://", "").split("/")[0]
        ctx = ssl.create_default_context()
        with ctx.wrap_socket(socket.socket(), server_hostname=hostname) as s:
            s.connect((hostname, 443))
        result["ssl_valid"] = True
    except:
        result["ssl_valid"] = False

    # 2. Domain Age
    try:
        domain_info = whois.whois(hostname)
        creation_date = domain_info.creation_date
        if isinstance(creation_date, list):
            creation_date = creation_date[0]
        age_days = (datetime.datetime.now() - creation_date).days
        result["domain_age_days"] = age_days
        result["domain_age_flag"] = age_days < 180  # True = RED FLAG
    except:
        result["domain_age_days"] = None
        result["domain_age_flag"] = True  # Unknown = suspicious

    # 3. Redirect Check
    try:
        response = requests.get(url, allow_redirects=True, timeout=10)
        result["redirect_count"] = len(response.history)
        result["final_url"] = response.url
        result["redirect_flag"] = len(response.history) > 2
    except:
        result["redirect_count"] = None
        result["redirect_flag"] = True

    return result
