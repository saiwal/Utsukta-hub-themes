# Security (सुरक्षा)

Security section access restrictions, content filtering और HTTP security headers नियंत्रित करता है।

[IMAGE: Security settings form]

## Access Controls

### Block Public Access
Enable होने पर **सभी personal pages पर authentication ज़रूरी** होती है। Anonymous visitors को login prompt दिखता है। Private या corporate hubs के लिए उपयुक्त।

### Cloud Root Directory
Enable (default) होने पर cloud file system root URL पर public files वाले channels की listing दिखाता है। Channel enumeration रोकने के लिए disable करें।

### Show Total Disk Space in Cloud
Cloud interface में total disk usage दिखाने का toggle।

---

## Email Domain Filtering

| Field | विवरण |
|-------|-------|
| **Allowed email domains** | Comma-separated। **केवल** ये domains register कर सकते हैं। Blank = सभी allowed। |
| **Blocked email domains** | Comma-separated। ये domains **हमेशा denied** रहते हैं। |

**उदाहरण — केवल company domain:**
```
Allowed: mycompany.com
```

**उदाहरण — spam domains block:**
```
Blocked: mailinator.com, trashmail.com
```

---

## Federation Controls

### Allowed Sites (Whitelist)
एक URL प्रति line। Non-empty होने पर **केवल** listed sites ही आपके hub से federate कर सकते हैं।

Federation किसी भी site से allow करने के लिए blank छोड़ें।

### Blocked Sites (Blocklist)
एक URL प्रति line। ये sites आपके hub से **federate नहीं** कर सकतीं।

> **महत्वपूर्ण:** Whitelist और blocklist एक साथ न रखें — whitelist set करने पर blocklist redundant है।

---

## Embedded Content

### Embed SSL Only
Enable होने पर केवल HTTPS URLs embedded content में allowed। Mixed-content warnings रोकता है।

---

## HTTP Security Headers

| Header | विवरण |
|--------|-------|
| **Transport Security Header** | `Strict-Transport-Security` (HSTS) भेजता है। **केवल fully HTTPS hubs पर enable करें।** |
| **Content Security Policy** | `Content-Security-Policy` header। XSS risk कम करता है। Production पर enable करने से पहले test करें। |

> **अनुशंसा:** HTTPS पर चलने वाले public hubs पर दोनों headers enable करें। HTTP-only installations पर HSTS enable न करें।

## Save करना

**Save** क्लिक करें।
