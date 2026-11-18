# Summary (सारांश)

Summary section आपके Hubzilla installation की स्थिति और आकार का snapshot देता है।

[IMAGE: Summary section stat grids और version info के साथ]

## Account Stats

2×2 grid:

| Stat | अर्थ |
|------|------|
| **Total** | सभी registered accounts |
| **Blocked** | manually blocked accounts |
| **Expired** | expiry date पार कर चुके accounts |
| **Expiring soon** | configured `abandon_days` window में आने वाले accounts |

**Action:** अगर blocked या expiring-soon की संख्या असामान्य रूप से अधिक है, तो **Accounts** section और **Site** settings की registration/expiry सेटिंग देखें।

## मुख्य Counts

| Stat | अर्थ |
|------|------|
| **Channels** | Hub पर कुल channels |
| **Pending registrations** | Admin approval की प्रतीक्षा में accounts |
| **Queued messages** | Deliver होने की प्रतीक्षा में outbound messages |

> **Queued messages** की non-zero संख्या high activity में सामान्य है। लेकिन बड़ा, न घटने वाला queue (सैकड़ों या हज़ारों) federation delivery टूटने का संकेत है। **Logs** में delivery errors देखें और queue worker की जाँच करें।

[IMAGE: मुख्य counts row Channels, Pending, Queue के साथ]

## Version

Installed Hubzilla version string। Official changelog से compare करें।

[IMAGE: Version string]

## Active Plugins

सभी currently enabled plugins badge के रूप में। विस्तृत plugin management **Addons** में है।

[IMAGE: Plugin badge list]
