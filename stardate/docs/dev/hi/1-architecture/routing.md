# रूटिंग

## फ्रेमवर्क

SPA **@solidjs/router** (Solid Router) उपयोग करता है। रूट रूट सभी पेज को `Layout` में रैप करता है, और चाइल्ड रूट रजिस्टर्ड मॉड्यूल से डायनामिक रूप से बनते हैं।

## रूट कैसे बनते हैं

1. हर मॉड्यूल `registerModule()` कॉल करता है, जो उसके रूट एक Solid सिग्नल में जोड़ता है।
2. `src/router.tsx` में `getRoutes()` वह सिग्नल एक्सपोज़ करता है।
3. `App.tsx` उस सिग्नल को रिएक्टिव रूप से पढ़कर `<For>` से `<Route>` एलिमेंट रेंडर करता है।

```typescript
// App.tsx
<For each={getRoutes()()}>
  {(route) => {
    const Comp = lazy(route.component);
    const mid = route.moduleId;
    if (mid && getModule(mid)?.appName) {
      const Guarded = () => (
        <ModuleGuard moduleId={mid}>
          <Comp />
        </ModuleGuard>
      );
      return <Route path={route.path} component={Guarded} />;
    }
    return <Route path={route.path} component={Comp} />;
  }}
</For>
```

## लेज़ी लोडिंग

हर रूट कॉम्पोनेंट लेज़ी लोड होता है। मॉड्यूल `index.ts` फ़ाइलें उपयोग करती हैं:

```typescript
component: () => import("./views/MyView")
```

`App.tsx` इसे रेंडर के समय `lazy()` में रैप करता है। Vite कोड स्प्लिटिंग अपने आप करता है और `app-[name].js` चंक बनाता है।

## ModuleGuard

जब मॉड्यूल `appName` घोषित करे, उसके रूट `ModuleGuard` में रैप होते हैं:

```typescript
const ModuleGuard: ParentComponent<{ moduleId: string }> = (props) => {
  const installedApps = useInstalledApps();
  const navigate = useNavigate();
  const active = createMemo(() => isModuleActive(props.moduleId, installedApps()));

  createEffect(() => {
    if (!active()) navigate("/", { replace: true });
  });

  return <Show when={active()}>{props.children}</Show>;
};
```

अगर ज़रूरी ऐप इन्स्टॉल न हो तो यूज़र `/` (जो `/hq` पर रीडायरेक्ट करता है) पर भेजा जाता है।

## डिफ़ॉल्ट रीडायरेक्ट

```typescript
<Route path="/" component={() => <Redirect to="/hq" />} />
```

रूट पाथ हमेशा `/hq` पर रीडायरेक्ट करता है।

## 404

```typescript
<Route path="*404" component={NotFound} />
```

कोई भी अनमैच्ड रूट `src/shared/views/NotFound.tsx` रेंडर करता है।

## रूट पाथ परंपराएं

| पैटर्न | उदाहरण | उपयोग |
|--------|---------|-------|
| `/module` | `/network` | मॉड्यूल इंडेक्स, कोई सब्जेक्ट नहीं |
| `/module/:nick` | `/photos/alice` | चैनल-स्कोप्ड मॉड्यूल |
| `/module/:nick/:id` | `/articles/alice/abc123` | चैनल के मॉड्यूल में एकल आइटम |
| `/module/:nick/sub/:datum` | `/photos/alice/album/summer` | सब-रिसोर्स |

`:nick` सेगमेंट हमेशा चैनल nickname होता है। कॉम्पोनेंट में इसे रिएक्टिव रूप से पढ़ने के लिए `useSubjectNick()` (`@/shared/store/site-config` से) उपयोग करें।

## रिएक्टिव रूट सूची

चूंकि `getRoutes()` एक Solid सिग्नल रिटर्न करता है, इसलिए शुरुआती रेंडर के बाद (async इम्पोर्ट से) रजिस्टर हुए मॉड्यूल भी राउटर को रीमाउंट किए बिना अपने आप रूट के रूप में दिखेंगे।
