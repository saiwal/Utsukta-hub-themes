# System Logs (सिस्टम लॉग)

Logs section Hubzilla system log दिखाता है — server द्वारा generate errors, warnings और informational messages।

[IMAGE: Logs section filter bar और log entries के साथ]

## Log Entry की संरचना

- **Timestamp** — कब logged हुआ (hover से full precision)
- **Level badge** — severity classification
- **Message** — log message text

[IMAGE: Individual log entry timestamp, level badge और message के साथ]

## Severity Levels (रंग-कोडेड)

| Level | रंग | अर्थ |
|-------|-----|------|
| EMERG, ALERT, CRIT, ERROR | लाल | ध्यान देने की ज़रूरत वाली serious errors |
| WARN | Amber | Warnings — जो problems बन सकते हैं |
| NOTICE, INFO | नीला | Informational — normal operation |
| DEBUG | Grey (dimmed) | Verbose diagnostic output |

## Level से Filter करना

Filter बटन से severity चुनें:
- **All** — सब कुछ
- **Error** — केवल EMERG + ALERT + CRIT + ERROR
- **Warning** — केवल WARN
- **Info** — NOTICE + INFO
- **Debug** — केवल DEBUG

[IMAGE: Level filter buttons Error selected के साथ]

**अनुशंसित workflow:**
1. **Error** filter से शुरू — इन पर हमेशा response चाहिए।
2. **Warning** switch करें — escalate हो सकने वाले issues।
3. **Debug** केवल specific problem diagnose करते समय।

## सामान्य Error Patterns

| Pattern | संभावित कारण |
|---------|------------|
| `queue` / `delivery` errors | Federation delivery failures — remote server की reachability check करें |
| `database` errors | Database connection या query problems |
| `plugin` / `addon` errors | मisbehaving plugin — disable करके देखें |
| `photo` / `file` errors | Storage permissions या disk space |
| `curl` / `network` errors | Server remote hosts नहीं reach कर सकता |

## Refresh

Page reload करें नवीनतम entries देखने के लिए।

[IMAGE: Logs page पर Refresh बटन]

> **सुझाव:** Persistent alerting के लिए server पर external log monitoring (fail2ban, log aggregator) setup करें। In-app log viewer spot-checks के लिए है।
