# Site Configuration (साइट कॉन्फ़िगरेशन)

Site section आपके hub की पहचान और registration व्यवहार नियंत्रित करता है।

[IMAGE: Site settings form]

## Identity Fields

| Field | विवरण |
|-------|-------|
| **Site name** | आपके hub का नाम — header और metadata में दिखता है |
| **Site location** | Hub का canonical URL (जैसे `https://myhub.example.com`) |
| **Banner text** | Public pages पर छोटा tagline या स्वागत संदेश |
| **Admin info** | Administrator की contact जानकारी |
| **Site info** | Hub का लंबा विवरण — public info page पर दिखता है |

## Registration Policy

कौन नया account बना सकता है:

| मूल्य | व्यवहार |
|------|---------|
| **No (closed)** | Registration बंद। कोई नया account नहीं बना सकता। |
| **Yes – with approval** | कोई भी apply कर सकता है, लेकिन admin को approve करना होगा। |
| **Yes (open)** | कोई भी बिना approval के तुरंत register कर सकता है। |

**Max daily registrations** — हर दिन कितने नए accounts बन सकते हैं। `0` = unlimited।

> **नए hubs के लिए अनुशंसा:** spam controls पक्के होने तक "with approval" से शुरू करें।

## Access Policy

आपके hub की commercial प्रकृति — public site info pages पर दिखती है:

| मूल्य | अर्थ |
|------|------|
| **Not a public server** | Private या closed hub |
| **Paid access only** | सभी users paid |
| **Free access only** | सभी users free |
| **Free + optional paid upgrades** | Free tier + optional paid features |

## अन्य सेटिंग्स

| सेटिंग | विवरण |
|--------|-------|
| **Abandon days** | इतने दिन inactive accounts को abandoned माना जाए (0 = कभी नहीं) |
| **Max image size** | Images के लिए maximum upload size (bytes में) |
| **Login on homepage** | Public homepage पर login form दिखाएं |
| **Disable discover tab** | Users से network discovery/explore tab छुपाएं |
| **Site firehose** | Hub को network-wide public firehose में शामिल करें |
| **Open public stream** | Anonymous visitors को public stream देखने दें |

## Save करना

ऊपर दाईं ओर **Save** क्लिक करें।
