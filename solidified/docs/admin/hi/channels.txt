# Channels (चैनल)

Channels section आपके hub पर हर channel का read-only overview देता है।

[IMAGE: Channels table name, address, created और last post columns के साथ]

## Channels Table

| Column | विवरण |
|--------|-------|
| **Name** | Channel का display name |
| **Address** | Channel का short address (nick@hub) |
| **Created** | Channel कब बना |
| **Last post** | Channel की सबसे हालिया post की तारीख |

"Last post" date से inactive channels पहचानें।

## Pagination

**Previous** और **Next** बटन से pages navigate करें।

[IMAGE: Channels table के नीचे pagination row]

## Accounts से संबंध

एक account कई channels का मालिक हो सकता है। **Accounts** section दिखाता है कि हर account के कितने channels हैं। Account block होने पर उसके सभी channels inaccessible हो जाते हैं लेकिन delete नहीं होते।

## क्या कर सकते हैं

यह section वर्तमान में listing और auditing के लिए read-only है। किसी channel पर action के लिए Hubzilla server admin interface या database tools की ज़रूरत है।

> **सुझाव:** "Last post" से sort करके पूरी तरह inactive channels पहचानें — अगर आपके hub की abandonment policy है तो ये cleanup candidates हो सकते हैं।
