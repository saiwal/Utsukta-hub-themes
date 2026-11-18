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

Field जोड़ना **दो steps** का काम है — पहले define, फिर enable।

1. **Add field** क्लिक करें।
2. Provide करें:
   - **Field nickname** — internal name (जैसे `occupation`); step 4 में यही enable करना है
   - **Field name** — label जो users को दिखेगा (जैसे "Occupation")
   - **Field type** — text, textarea, checkbox या select
   - **Help text** — input के नीचे दिखने वाला optional hint
3. Save करें।
4. ऊपर के **Basic Profile Fields** या **Advanced Profile Fields** box में field का
   **nickname** जोड़ें और **Submit** करें।

Step 4 optional नहीं है: जो field किसी list में नहीं है वह invisible रहता है — न profile
editor में input, न profile page पर कुछ। (Advanced fields सिर्फ़ उन channels को दिखते हैं
जिनके पास "Advanced profiles" feature enabled है; Basic fields सबको।)

[IMAGE: Add profile field form name, type और order fields के साथ]

## Edit और Remove करना

Field के बगल में **Edit** क्लिक करें। Remove के लिए **Delete**।

> ⚠ Custom profile field remove करने पर उस field में stored सभी users का data भी हट जाता है। यह अपरिवर्तनीय है।

## User Experience

Enable होने के बाद users इसे **Profiles → edit** (पूरा profile editor) में "Additional
information" के नीचे भरते हैं। भरी हुई values channel के profile page और profile card पर
दिखती हैं। Existing users का profile blank रहेगा जब तक वे fill न करें।

Values हर profile के लिए अलग store होती हैं, इसलिए multiple profiles वाला channel हर profile
पर अलग जवाब दे सकता है।
