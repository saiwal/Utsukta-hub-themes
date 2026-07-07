# Hubzilla SPA — डेवलपर दस्तावेज़

यह **hubzilla-spa** के लिए डेवलपर संदर्भ है — Hubzilla फ़ेडरेटेड सोशल प्लेटफ़ॉर्म के लिए एक Solid.js सिंगल-पेज एप्लिकेशन।

## विषय-सूची

- [Overview](overview) — आर्किटेक्चर, टेक स्टैक, प्रोजेक्ट संरचना
- [Module-system](module-system) — फ़ीचर मॉड्यूल बनाना, रजिस्टर करना और गेटिंग
- [Slot-system](slot-system) — लेआउट क्षेत्रों में विजेट इंजेक्ट करना
- [Routing](routing) — SPA रूटिंग, लेज़ी लोडिंग और ModuleGuard
- [Stores](stores) — रिएक्टिव स्टेट: auth, nav, site-config
- [Nav-system](nav-system) — नेविगेशन की गणना और व्यूअर रोल
- [Api-client](api-client) — फ्रंटएंड API यूटिलिटी और CSRF हैंडलिंग
- [Php-api](php-api) — बैकएंड PHP API: router, auth, response, handlers
- [I18n](i18n) — अंतर्राष्ट्रीयकरण (i18n) सिस्टम

## त्वरित शुरुआत

```bash
npm run dev        # dev सर्वर (/api को https://hz-ddev.ddev.site पर प्रॉक्सी करता है)
npm run build      # प्रोडक्शन बिल्ड
npm run typecheck  # TypeScript वॉच मोड
```

ऐप का एंट्री पॉइंट `src/index.tsx` है। सभी फ़ीचर मॉड्यूल `src/modules/` के अंदर हैं और `src/App.tsx` में `import.meta.glob` के ज़रिए ख़ुद रजिस्टर होते हैं।
