# Themes (थीम)

Themes section आपके hub पर installed सभी Hubzilla server-side themes दिखाता है।

[IMAGE: Themes section installed theme names की listing के साथ]

## Themes क्या Control करते हैं

Hubzilla themes interface के **server-side template और layout** को control करते हैं — non-SPA pages के colors, fonts और HTML structure। SPA (यह interface) का अपना अलग color scheme setting है।

## Theme List

हर theme नाम से listed है। Currently active default theme indicated है।

## Default Theme सेट करना

Default theme नए users और visitors को apply होता है। यह server के configuration file में configure होता है — इस UI से नहीं।

## नए Themes Install करना

Server filesystem पर `extend/theme/` में theme folder रखें, फिर यह page reload करें।

## इस Interface को Upgrade करने के बाद

कुछ features Hubzilla server के hooks से जुड़ते हैं, और hooks सिर्फ़ theme enable होने पर register होते हैं। नया hook लाने वाले upgrade के बाद admin Themes पेज में theme को **एक बार disable करके फिर enable करें**। मौजूदा hooks: web push notifications, और hubs के बीच chat notices (दूसरे hub पर bookmark किए rooms के unread dots)।

## User Theme Choice

Users **Settings → Display → Theme** में hub default override कर सकते हैं। Admin existing users पर theme force नहीं कर सकता — केवल नए users के लिए default बदला जा सकता है।

[IMAGE: Theme list theme names और selection indicator के साथ]
