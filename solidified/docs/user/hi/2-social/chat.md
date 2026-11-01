# चैट

Chat ऐप (`/chat/<nick>`) चैनल से जुड़े real-time chatrooms देता है। एक चैनल में कई chatrooms हो सकते हैं।

[IMAGE: Chat rooms सूची पेज]

## Chatroom सूची

मुख्य Chat पेज उपलब्ध chatrooms दिखाता है:
- Room का नाम और विवरण
- मौजूदा participants की संख्या (अगर दिखती हो)

Room में प्रवेश के लिए उसके नाम पर क्लिक करें।

[IMAGE: Chatroom सूची नाम और विवरण के साथ]

## Chatroom के अंदर

Room में:
- **Message history** — scroll योग्य recent messages
- **Message input** — नीचे, type करें और send करें
- **Participant list** — और कौन है room में

[IMAGE: Chatroom view message history, input और participant list के साथ]

## Message भेजना

नीचे message input में type करें और **Enter** (या send बटन) दबाएं।

## नए संदेश की सूचनाएँ

हर chat window के title bar में एक bell बटन है, जो उस room के लिए तीन modes बदलता है:
- **Sound** — कोई और संदेश भेजे तो छोटी सी घंटी
- **Push** — browser notification (पहली बार browser अनुमति माँगेगा)
- **Silent** — कोई सूचना नहीं (default)

सूचनाएँ तभी आती हैं जब chat window खुली हो पर आप room नहीं देख रहे — tab पीछे हो, browser focus में न हो, या window minimise हो। आपकी पसंद हर room के लिए इसी browser में याद रहती है।

## Unread rooms

जिस room में आपके न देखे संदेश हों, उसके नाम के आगे एक छोटा रंगीन dot दिखता है — Chat पेज की room सूची, Chatrooms widget और Bookmarked Rooms widget में। Room खोलते ही dot हट जाता है। आपके अपने संदेश unread नहीं गिने जाते।

Navigation में **Chat** item पर *आपके अपने* unread rooms की गिनती भी दिखती है। Tab दिखने के दौरान यह लगभग हर मिनट जाँचता है, इसलिए नया संदेश दिखने में एक मिनट तक लग सकता है।

Unread स्थिति आपके browser में रखी जाती है: फ़ोन पर room पढ़ने से laptop का dot नहीं हटेगा। जो rooms आपने यह feature आने से पहले कभी नहीं खोले, वे पढ़े हुए माने जाते हैं।

## Room छोड़ना

Chatroom पेज से navigate करके room छोड़ें। बाद में कभी भी वापस आ सकते हैं।

## किसी और का Chat

`/chat/<उनका-nick>` पर जाएं — उनके पब्लिक chatrooms देखें और join करें।

> **ध्यान दें:** Chat ऐप चैनल पर install होना चाहिए। Room owner specific connections या groups तक access सीमित कर सकता है।

## Invitations और दूसरे hubs के rooms

जब owner कुछ ख़ास लोगों तक सीमित room बनाता है, तो वह **Notify invited members** चुन सकता है — हर invitee को room के link वाली एक private post मिलती है। Link खोलने पर आप owner के hub पर logged in पहुँचते हैं, इसलिए आपका channel कहीं और हो तब भी room आपको पहचानता है।

Room बाद के लिए रखने को room link के पास वाले bookmark icon (**इस लिंक को बुकमार्क करें**) पर क्लिक करें: link सीधे आपके **Bookmarked Rooms** में जुड़ जाता है। (पुराने invite से सहेजे bookmark का नाम "Join here" होगा — Bookmarks पेज से उसे बदल लें।) आप room खोलकर उसका bookmark बटन भी दबा सकते हैं। किसी और के hub का room देखते समय वह बटन नए tab में **आपका अपना hub** खोलता है, जहाँ "इस चैटरूम को बुकमार्क करें" card मिलता है: title जाँचें, **Save** करें, फिर **Room पर वापस** जाएँ।

यहाँ की chat window सिर्फ़ आपके अपने hub के rooms join कर सकती है। दूसरे hub का bookmarked room उसी hub पर नए tab में, आपके login के साथ खुलता है — अगर वह hub भी यही interface चलाता हो तो सीधे room में, पूरे पेज पर।

दूसरे hub के bookmarked rooms पर भी unread dot दिखता है, और वे Chat badge में गिने जाते हैं — **अगर वह hub भी यही interface चलाता हो**: room bookmark करने पर owner का hub, कोई संदेश भेजे तो, आपके hub को बता देता है। यह हर room के लिए ज़्यादा से ज़्यादा 5 मिनट में एक बार होता है, और तब नहीं जब आप room में हों। Bookmark पर क्लिक करते ही dot हट जाता है। सिर्फ़ classic Hubzilla चलाने वाले hub के rooms पर dot कभी नहीं दिखता।

ऐसे room के लिए आप **push notification** भी पा सकते हैं: Bookmarked Rooms widget में उसकी row पर bell पर क्लिक करें। पहली बार browser notification की अनुमति माँगेगा। Push default रूप से बंद है, हर room के लिए अलग सेट होता है, और उसी 5 मिनट की सीमा का पालन करता है। Notification पर tap करने से room उसके hub पर, आपके login के साथ खुलता है।
