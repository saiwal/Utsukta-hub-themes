# Profile Fields (प्रोफ़ाइल फ़ील्ड)

Profile Fields section hub के सभी user profiles पर **custom profile fields** define करने देता है। ये default profile fields (name, description, hometown आदि) को extend करते हैं।

[IMAGE: Profile fields section existing fields और add form के साथ]

## Custom Profile Fields क्या हैं

Extra input fields जो हर user के profile edit screen और public profile page पर दिखते हैं। उदाहरण:

- "Occupation"
- "Preferred pronoun"
- "Programming languages"
- "Research interests"

Themed या specialist communities (academic hubs, professional networks, hobbyist groups) के लिए उपयोगी।

## Field जोड़ना

1. **Add field** क्लिक करें।
2. Provide करें:
   - **Field name** — label जो users को दिखेगा (जैसे "Occupation")
   - **Field type** — text input, textarea, dropdown आदि
   - **Order** — display position
3. Save करें।

[IMAGE: Add profile field form name, type और order fields के साथ]

## Edit और Remove करना

Field के बगल में **Edit** क्लिक करें। Remove के लिए **Delete**।

> ⚠ Custom profile field remove करने पर उस field में stored सभी users का data भी हट जाता है। यह अपरिवर्तनीय है।

## User Experience

Field जोड़ने के बाद users अगली बार **Settings → Profile** खोलने पर नया field देखेंगे। Existing users का profile blank रहेगा जब तक वे fill न करें।
