# Database Updates (डेटाबेस अपडेट)

DB Updates section **pending database schema changes** दिखाता है — migrations जो installed Hubzilla version के अनुसार database को up-to-date करने के लिए ज़रूरी हैं।

[IMAGE: DB Updates section pending update list या "up to date" message के साथ]

## यह कब ज़रूरी है

Hubzilla upgrade (git pull या manual update) के बाद नए schema changes pending हो सकते हैं। अगर updates pending हों:

- कुछ features सही से काम नहीं करेंगे
- Logs में missing columns या tables से related errors आ सकते हैं

## Updates चलाना

Updates listed हों तो **Run updates** क्लिक करें। Updates sequentially चलते हैं। Page refresh होकर completion confirm करता है।

[IMAGE: Run updates button pending update descriptions के साथ]

## Up to Date

कोई pending updates न हों तो "Database is up to date." दिखता है।

[IMAGE: "Up to date" confirmation message]

## Upgrade के बाद

**अनुशंसित post-upgrade checklist:**
1. यह section — pending updates चलाएं।
2. **Logs** — नए errors देखें।
3. **Summary** — counts सही लगते हैं?
4. Regular user के रूप में login test करें।

> **सुझाव:** हर Hubzilla upgrade के बाद users को announce करने से पहले database updates तुरंत run करें।
