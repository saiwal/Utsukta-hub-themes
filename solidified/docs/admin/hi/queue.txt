# Message Queue (संदेश कतार)

Queue section **undelivered outbound messages** दिखाता है — federation activity जो आपके hub ने भेजने की कोशिश की लेकिन remote servers तक पहुँच नहीं पाई।

[IMAGE: Queue section total count और message table के साथ]

## Queue क्या है

जब आपका hub content post करता है या remote channel follow/unfollow करता है, तो federation messages भेजे जाते हैं। अगर remote server temporarily down हो, तो message queue में रखा जाता है और बाद में retry होता है।

**Healthy queue** = छोटा और घटता हुआ।
**Unhealthy queue** = बड़ा होता या कभी खाली न होता।

## Queue Table

| Column | विवरण |
|--------|-------|
| **Destination** | Remote server URL |
| **Updated** | अंतिम delivery attempt कब |
| **Priority** | Message priority (कम number = उच्च priority) |

[IMAGE: Queue table rows destination URLs और timestamps के साथ]

## Queue की व्याख्या

**Empty queue** → सभी messages delivered। Federation healthy।

**Small queue (< ~20)** → Normal। कुछ messages हमेशा transit में होते हैं।

**Large या growing queue** → Problem। सामान्य कारण:
- एक specific remote server down — देखें कि ज़्यादातर entries एक ही destination share करते हैं क्या
- **Queue worker** (cron job) नहीं चल रहा
- Network/firewall outbound connections रोक रहा है

## Queue Worker की जाँच

Queue background PHP cron job से process होता है। Cron न चले तो messages indefinitely accumulate होते हैं।

**Cron verify करने के लिए:**
- Server का cron configuration देखें (`crontab -l` या `/etc/cron.d/`)
- Hubzilla cron entry ढूंढें (typically हर कुछ minutes में चलता है)
- **Queueworker** admin section देखें (देखें [queueworker.txt](queueworker.txt) अगर उपलब्ध हो)

## Stuck Messages साफ़ करना

अगर specific destinations कई दिनों से fail हो रहे हैं और आप sure हैं कि remote server permanently offline है, तो उन entries को remove करना server database या Hubzilla के cleanup tools से होगा — इस UI view से नहीं।

## Refresh

**Refresh** क्लिक करके current queue state reload करें।

[IMAGE: Queue page पर Refresh बटन]
