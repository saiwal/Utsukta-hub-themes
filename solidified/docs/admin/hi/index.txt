# Hubzilla SPA — एडमिन मार्गदर्शिका

यह मार्गदर्शिका `/admin` पर Admin panel को cover करती है। यह केवल administrator privileges वाले accounts को दिखता है।

## विषय-सूची

- [Summary](summary) — Site अवलोकन: user counts, queue, version, plugins
- [Site](site) — Site नाम, registration policy, access policy
- [Security](security) — Block lists, allowlists, HTTP security headers
- [Accounts](accounts) — Registered accounts: block, delete, paginate
- [Channels](channels) — Hub के सभी channels
- [Features](features) — Optional site features enable/disable
- [Addons](addons) — Plugins/addons install और manage करें
- [Logs](logs) — System log viewer level filtering के साथ
- [Queue](queue) — Outbound message queue inspection
- [Themes](themes) — Installed themes management
- [Profile-fields](profile-fields) — Custom profile field definitions
- [Db-updates](db-updates) — Pending database schema updates

## Admin Panel तक पहुँचना

Admin section केवल तभी accessible है जब आपके account पर administrator flag हो। `/admin` पर navigate करें या left sidebar के action menu में **Admin** link से।

Admin panel में Settings जैसा sub-page layout है — बाईं ओर sections की सूची और दाईं ओर content।

[IMAGE: Admin panel overview section list के साथ]

## त्वरित स्वास्थ्य जाँच

1. **Summary** खोलें — queue count देखें। बड़ा और बढ़ता queue federation delivery problems का संकेत है।
2. **Logs** खोलें — **Error** level से filter करें। यहाँ persistent errors पर तुरंत ध्यान दें।
3. **Accounts** खोलें — blocked या expired accounts की समीक्षा करें।
