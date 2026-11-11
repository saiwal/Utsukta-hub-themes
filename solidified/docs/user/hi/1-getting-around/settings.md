# सेटिंग्स

Settings `/settings` पर हैं। बाईं पैनल में sections की सूची है — किसी भी section पर क्लिक करें।

[IMAGE: Settings पेज बाईं ओर section सूची और दाईं ओर content के साथ]

---

## Display (डिस्प्ले)

इंटरफ़ेस की visual appearance।

[IMAGE: Display settings section]

| सेटिंग | विवरण |
|--------|-------|
| **Color scheme** | थीम चुनें: Light, Dark, Nord, Dracula, Monokai, Gruvbox Dark, Catppuccin Mocha, Solarized Dark, Tokyo Night |
| **Custom colours** | theme के individual रंग बदलें |
| **Font size** | Small, Medium, Large, XL |
| **Font family** | System, Serif, Monospace, Nunito, Playfair, Comfortaa, Space Mono आदि |
| **Thread mode** | Comment threads flat या nested |
| **Background image** | Custom background URL और Tile/Cover fitting |
| **Items per page** | हर page पर कितनी posts लोड हों |
| **Update interval** | App कितने seconds में नया content check करे |
| **Hubzilla theme** | Server-side Hubzilla theme (page reload चाहिए) |

**Save** क्लिक करें। Theme और font बदलाव तुरंत लागू होते हैं।

---

## Profile (प्रोफ़ाइल)

आपकी public profile — दूसरे लोग आपके channel पर यही देखते हैं।

[IMAGE: Profile settings section]

| Field | विवरण |
|-------|-------|
| **Display name** | नेटवर्क पर आपका दिखने वाला नाम |
| **Short description** | एक-लाइन परिचय |
| **Homepage** | Profile पर दिखने वाला URL |
| **Hometown** | आप कहाँ से हैं |
| **Gender** | वैकल्पिक |
| **Birthday** | वैकल्पिक — connections को महीना/दिन दिखता है |
| **About** | लंबा bio |
| **Keywords** | रुचियाँ और tags |
| **Hide friend list** | Connections सूची दूसरों से छुपाएं |

---

## Account (खाता)

Login credentials और account-level settings।

[IMAGE: Account settings section]

- **ईमेल** बदलें
- **पासवर्ड** बदलें
- **Two-factor authentication** manage करें (hub द्वारा enable होने पर)

---

## Privacy (प्राइवेसी)

Content visibility और interaction control।

[IMAGE: Privacy settings section]

| सेटिंग | विवरण |
|--------|-------|
| **Permission limits** | Advanced per-permission controls |
| **Default permissions** | हर interaction type के लिए permission |
| **Auto-permissions** | नए connections पर permission set अपने आप लागू करें |
| **Opt out of directory** | Public directory से अपने आप को हटाएं |
| **Group actor** | Channel को forum/community के रूप में उपयोग करें |
| **Allow all mentions** | कोई भी @mention कर सके |
| **Moderate unsolicited comments** | Non-connections के comments approval के लिए रोकें |

---

## Notifications (नोटिफ़िकेशन)

Email और visual notifications की सेटिंग।

[IMAGE: Notifications settings section]

### Auto-post Activity
अपने आप post करें जब:
- Friend request accept करें
- किसी forum में join करें
- Profile में interesting बदलाव करें

### Email Notifications
इन पर email मिले:
- Connection request मिले / confirm हो
- कोई आपकी wall पर post करे
- New comment, private message, like आदि

### Visual Notifications
Notification widget में दिखे:
- Unseen stream/channel activity
- Private messages, events, birthdays
- System notifications और alerts

---

## Integrations (एकीकरण)

External services connect करें। Hub configuration के अनुसार विकल्प:
- RSS/Atom feed export
- Cross-posting
- API access tokens

---

## Danger Zone (खतरनाक क्षेत्र)

अपरिवर्तनीय actions। सावधानी से आगे बढ़ें।

[IMAGE: Danger zone section warning styling के साथ]

- **Account data export** — सारा content और connections download करें
- **Delete channel** — channel और उसका सारा content permanently हटाएं
- **Delete account** — account, सभी channels और सारा data permanently हटाएं

> ये actions वापस नहीं हो सकते। Interface confirm करेगा पहले।
