# API क्लाइंट

## अवलोकन

सारा API संचार एक पतले रैपर, `apiFetch`, से होता है जो
`packages/spa-core/src/lib/fetch.ts` में है। यह कच्चा `Response` लौटाता है — JSON
एनवेलप को खोलता नहीं — ताकि कॉल साइट status हैंडलिंग के बारे में स्पष्ट रहें।

> एक पुरानी `api.ts` `/api/z/1.0/` के लिए `apiGet` / `moduleGet` / `modulePost`
> एक्सपोर्ट करती थी। वे हटा दिए गए हैं; ट्री में कोई उन्हें उपयोग नहीं करता। अगर
> कहीं उनका ज़िक्र मिले, तो वह पुराना है।

ज़्यादातर कंपोनेंट कोड को GET के लिए सीधे नेटवर्क लेयर कॉल नहीं करनी चाहिए — रीड
TanStack Query कैश से होते हैं; देखें [data-fetching.md](data-fetching.md)।

## apiFetch

```typescript
import { apiFetch, apiError } from "@utsukta/spa-core/lib/fetch";

const res = await apiFetch("/spa/network?start=0");
if (!res.ok) throw await apiError(res);
const { data } = await res.json();
```

हर रिक्वेस्ट पर यह जोड़ता है:

- `credentials: "include"`
- `Content-Type: application/json`

और `POST` / `PUT` / `PATCH` / `DELETE` पर:

- `X-CSRF-Token`, `getCsrfToken()` से फ़ेच किया हुआ

`init.headers` में दिए गए हेडर मर्ज होते हैं, और CSRF हेडर सबसे आख़िर में लगता है
ताकि वह गलती से ओवरराइट न हो।

### स्वचालित CSRF रीट्राई

अगर कोई म्यूटेटिंग रिक्वेस्ट `403` लौटाए और बॉडी CSRF एरर हो
(`error.code === "csrf_invalid"` या `error.message === "Invalid CSRF token"`),
तो `apiFetch` `resetCsrfToken()` कॉल करता है और नए टोकन के साथ रिक्वेस्ट **एक बार**
दोबारा भेजता है। हाथ से लिखे `fetch` की बजाय इसे पसंद करने की मुख्य वजह यही है —
वरना 30-मिनट के टोकन कैश से लंबा चलने वाला सेशन सेव पर फ़ेल हो जाता है।

### म्यूटेशन लिखना

```typescript
export async function renameThing(id: number, name: string) {
  const res = await apiFetch(`/spa/thing/${id}/edit`, {
    method: "POST",
    body: JSON.stringify({ name }),
  });
  if (!res.ok) throw await apiError(res);
  return (await res.json()).data;
}
```

## कब कच्चा `fetch` उपयोग करें

`apiFetch` हमेशा JSON कंटेंट टाइप सेट करता है, इसलिए दो मामलों में इसे छोड़ना होगा:

- **Multipart अपलोड** — `FormData` बनाएं और `X-CSRF-Token` खुद सेट करें
  (`Avatar.php`, `Photos.php` और vCard/iCal इम्पोर्ट पाथ ऐसा करते हैं)।
- **फ़ाइल डाउनलोड** — सादा `<a href>` उपयोग करें; हैंडलर एनवेलप लौटाने की बजाय
  अपने हेडर लिखकर `exit` करता है (देखें `Cal.php` का `?export=ical` और `Files.php`)।

दोनों ऑफ़लाइन-सुरक्षित रहते हैं:
`packages/spa-core/src/lib/offline-fallback.ts` का fetch fallback `window.fetch` को
ग्लोबली रैप करता है, हर कॉल साइट के नीचे।

## एरर हैंडलिंग

`apiError(res, label?)` रिस्पॉन्स से एक `Error` बनाता है, खाली status कोड की बजाय
सर्वर के `error.message` को प्राथमिकता देता है, और उसे 200 अक्षरों तक काटता है
(सर्वर संदेशों में PHP stack trace हो सकता है)।

```typescript
import { apiError, truncateError } from "@utsukta/spa-core/lib/fetch";
```

इसे दिखाने का मानक पैटर्न:

```typescript
import { toast } from "@utsukta/spa-core/store/toast";

try {
  await renameThing(id, name);
} catch (err) {
  toast.error(err instanceof Error ? err.message : "Rename failed");
}
```

## CSRF टोकन मैनेजमेंट

फ़ाइल: `packages/spa-core/src/lib/csrf.ts`

टोकन `/spa/csrf` से एक बार फ़ेच होता है और 30 मिनट तक कैश रहता है। `apiFetch`
यह आपके लिए संभालता है; इसे सीधे सिर्फ़ ऊपर के कच्चे-`fetch` मामलों में कॉल करें।

```typescript
import { getCsrfToken, resetCsrfToken } from "@utsukta/spa-core/lib/csrf";

const token = await getCsrfToken();  // कैश्ड, या फ़ेच किया हुआ
resetCsrfToken();                    // अमान्य करें (जैसे 403 के बाद)
```

सर्वर पर, `Auth::requireLocalJson()` हेडर को `$_SESSION['solidified_csrf']` से
मिलाता है।

## API रिस्पॉन्स एनवेलप

सभी PHP API रिस्पॉन्स एक समान एनवेलप उपयोग करते हैं (देखें [php-api.md](php-api.md)):

```json
{ "data": <payload> }
{ "data": [...], "meta": { "offset": 0, "limit": 20, "has_more": true, ... } }
{ "error": { "status": 404, "message": "Not found" } }
```

`data` को मॉड्यूल की `api.ts` में खोलें, ताकि views और widgets कभी एनवेलप न देखें।

## Dev प्रॉक्सी

Vite dev सर्वर `https://hz-ddev.ddev.site` पर प्रॉक्सी करता है, इसलिए मॉड्यूल कोड
dev और प्रोडक्शन दोनों में relative `/spa/` URL उपयोग कर सकता है।

सीधे प्रॉक्सी होते हैं: `/spa`, `/perfstats`, `/cloud`, `/photo`, `/attach`,
`/wall_upload`, `/wall_attach`, `/item`, `/acl`, `/follow`, `/subthread`,
`/sse_bs`, `/starred`, `/smilies`।

`/hq`, `/notify`, `/notifications` और `/cdav` **SPA रूट भी हैं**, इसलिए वे सिर्फ़ ऐप
के अपने `fetch()` के लिए प्रॉक्सी होते हैं — ब्राउज़र नेविगेशन
(`sec-fetch-mode: navigate`) dev सर्वर पर ही रहते हैं, ताकि हार्ड रीलोड पर भी
पुरानी थीम की बजाय SPA खुले।
