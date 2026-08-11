# आर्किटेक्चर अवलोकन

## यह क्या है

**hubzilla-spa** एक Solid.js SPA है जो Hubzilla के सर्वर-रेंडर्ड UI की जगह लेता है। यह एक कस्टम PHP API लेयर (`src/Api/`) से बात करता है जो Hubzilla थीम के हिस्से के रूप में डिप्लॉय होती है और `/spa/*` पर उपलब्ध रहती है।

## टेक स्टैक

| लेयर | टेक्नोलॉजी |
|------|-----------|
| UI फ्रेमवर्क | Solid.js v1.9 |
| रूटिंग | @solidjs/router v0.15 |
| स्टाइलिंग | Tailwind CSS v4 |
| बिल्ड | Vite + vite-plugin-solid |
| i18n | @solid-primitives/i18n |
| मार्कअप | BBCode → HTML (bbcodeToHtml), DOMPurify सैनिटाइज़ेशन |
| आइकन | solid-icons (Iconify-आधारित) |
| एनिमेशन | solid-motionone |
| बैकएंड | PHP 8 (Hubzilla थीम के अंदर) |

## प्रोजेक्ट संरचना

```
src/
├── App.tsx             # रूट: import.meta.glob से मॉड्यूल लोड करता है, रूट बनाता है
├── Layout.tsx          # शेल: टॉप नेव, लेफ्ट नेव, राइट साइडबार, मोबाइल टैब बार
├── index.tsx           # एंट्री पॉइंट: PWA इनिट, थीम, फ़ॉन्ट सेटअप
├── router.tsx          # getRoutes() सिग्नल रि-एक्सपोर्ट करता है
├── index.css           # ग्लोबल CSS (Tailwind इम्पोर्ट, कस्टम प्रॉपर्टी)
├── pwa.ts              # Service worker अपडेट डिटेक्शन
├── i18n/               # I18nProvider + लोकेल डिक्शनरी
├── modules/            # 22 फ़ीचर मॉड्यूल (हर एक ख़ुद रजिस्टर होता है)
├── shared/
│   ├── lib/            # यूटिलिटी: module-registry, api, csrf, useNav, bbcode, …
│   ├── store/          # ग्लोबल रिएक्टिव स्टोर: auth, nav, site-config
│   ├── types/          # शेयर्ड TypeScript इंटरफ़ेस
│   ├── views/          # शेयर्ड UI कॉम्पोनेंट (NotFound, HelpOverlay, Slot, …)
│   ├── widgets/        # शेयर्ड विजेट (नोटिफ़िकेशन, स्ट्रीम विजेट)
│   ├── editor/         # रिच टेक्स्ट एडिटर (पोस्ट कम्पोज़र में उपयोग)
│   └── stream/         # स्ट्रीम / फ़ीड डिस्प्ले कॉम्पोनेंट
└── Api/                # PHP बैकएंड (Hubzilla थीम के साथ डिप्लॉय होता है)
    ├── Router.php
    ├── Auth.php
    ├── Response.php
    ├── Concerns/
    └── Handlers/       # हर API रिसोर्स के लिए एक हैंडलर क्लास
```

## बिल्ड आउटपुट

Vite बिल्ड इस डायरेक्टरी में लिखता है:
```
../hz-ddev/core/extend/theme/utsukta-themes/solidified/assets/
```

आउटपुट: `app.js`, `app-[name].js` (कोड-स्प्लिट चंक), `app.css`।
`src/docs/` और `src/Api/` को भी साथ में कॉपी किया जाता है।

## Dev प्रॉक्सी

डेवलपमेंट में Vite सर्वर ये प्रॉक्सी करता है:
- `/spa/*` → `https://hz-ddev.ddev.site/spa/*`
- `/perfstats` → वही टार्गेट

इससे SPA बिना CORS समस्या के असली Hubzilla इन्स्टेन्स से बात कर सकता है।

## मॉड्यूल ऑटो-इम्पोर्ट

```typescript
// App.tsx
import.meta.glob("./modules/*/index.ts", { eager: true });
```

यह एक लाइन हर `src/modules/*/index.ts` को eagerly इम्पोर्ट करती है, जो `registerModule()` कॉल करते हैं। कोई केंद्रीय सूची अपडेट नहीं करनी — नया फ़ोल्डर बनाना ही काफ़ी है।

## डिप्लॉयमेंट नोट

यह ऐप एक Hubzilla **थीम** आर्टिफैक्ट है। PHP API और बिल्ट JS/CSS `solidified` थीम का हिस्सा हैं। SPA एक ऐसे Hubzilla पेज के अंदर माउंट होता है जो `app.js` और `app.css` सर्व करता है।
