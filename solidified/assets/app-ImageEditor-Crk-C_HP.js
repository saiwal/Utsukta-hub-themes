import{H as e,U as t,h as n,m as r}from"./app-web-BeXRvOD7.js";import{n as i,r as a,t as o}from"./app-vendor-image-editor-DVQYknbP.js";var s=r(`<div style=position:fixed;inset:0;z-index:9999>`);function c(){let e=getComputedStyle(document.documentElement),t=t=>e.getPropertyValue(t).trim(),n=t(`--color-elevated`),r=t(`--color-surface`),i=t(`--color-txt`),a=t(`--color-subtle`),o=t(`--color-rim`),s=t(`--color-accent`),c=document.createElement(`style`);return c.textContent=`
    .SfxInput-root {
      background-color: ${n} !important;
      color: ${i} !important;
      border-color: ${o} !important;
    }
    .SfxInput-root:hover {
      background-color: ${n} !important;
      color: ${i} !important;
      border-color: ${o} !important;
    }
    .SfxInput-root:focus-within {
      background-color: ${r} !important;
      color: ${i} !important;
      border-color: ${s} !important;
    }
    .SfxInput-Base {
      color: ${i} !important;
    }
    .SfxInput-Base::placeholder {
      color: ${a} !important;
    }
  `,document.head.appendChild(c),c}function l(){let e=getComputedStyle(document.documentElement),t=t=>e.getPropertyValue(t).trim(),n=t(`--color-surface`),r=t(`--color-elevated`),i=t(`--color-base`),a=t(`--color-txt`),o=t(`--color-muted`),s=t(`--color-subtle`),c=t(`--color-rim`),l=t(`--color-rim-strong`),u=t(`--color-accent`),d=t(`--color-accent-fg`);return{palette:{"txt-primary":a,"txt-secondary":o,"txt-secondary-invert":n,"txt-placeholder":s,"accent-primary":u,"accent-primary-hover":u,"accent-primary-active":u,"accent-primary-disabled":o,"accent-stateless":u,"bg-primary":n,"bg-primary-light":r,"bg-primary-hover":r,"bg-primary-active":r,"bg-primary-stateless":r,"bg-secondary":r,"bg-grey":r,"bg-base-light":i,"bg-base-medium":i,"bg-stateless":r,"bg-hover":r,"bg-active":r,"bg-tooltip":r,"icon-primary":a,"icons-secondary":o,"icons-placeholder":s,"icons-muted":s,"icons-invert":n,"icons-primary-hover":a,"icons-secondary-hover":o,"btn-primary-text":d,"btn-primary-text-0-6":d,"btn-primary-text-0-4":d,"btn-disabled-text":s,"btn-secondary-text":a,"link-primary":u,"link-stateless":u,"link-hover":u,"link-active":u,"link-muted":o,"borders-primary":c,"borders-primary-hover":l,"borders-secondary":c,"borders-strong":l,"borders-button":c,"borders-item":c,"borders-base-light":c,"borders-base-medium":l,"borders-disabled":c,"border-hover-bottom":u,"border-active-bottom":u}}}async function u(e,t,n){let r=await new Promise((t,n)=>{let r=new Image;r.onload=()=>t(r),r.onerror=()=>n(Error(`image load failed`)),r.src=e}).catch(()=>null);if(!r||!n.width||!n.height)return null;let i=r.naturalWidth/n.width,a=r.naturalHeight/n.height,o=Math.round((t.x??0)*i),s=Math.round((t.y??0)*a),c=Math.round((t.width??n.width)*i),l=Math.round((t.height??n.height)*a);if(c<=0||l<=0)return null;let u=document.createElement(`canvas`);u.width=c,u.height=l;let d=u.getContext(`2d`);return d?(d.drawImage(r,o,s,c,l,0,0,c,l),new Promise(e=>u.toBlob(e,`image/jpeg`,.92))):null}function d(r){let d,f=null,p=``,m=null;return t(()=>{p=URL.createObjectURL(r.file),m=c();let e={autoResize:!1};if(r.aspect!==void 0){let t=r.aspect>1.5;e.ratio=r.aspect,e.noPresets=!1,e.presetsItems=[{titleKey:t?`cover`:`square`,descriptionKey:t?`${r.aspect.toFixed(2)}:1`:`1:1`,ratio:r.aspect}]}f=new o(d,{source:p,theme:l(),defaultSavedImageName:``,onSave:async(e,t)=>{let n=null,i=t?.adjustments;if(i&&!i.rotation&&!i.isFlippedX&&!i.isFlippedY&&!t?.filter&&!t?.finetunes?.length&&!(t?.annotations&&Object.keys(t.annotations).length)&&!t?.resize?.width&&!t?.resize?.height&&t?.shownImageDimensions&&(n=await u(p,i.crop,t.shownImageDimensions)),!n&&e.imageCanvas?n=await new Promise(t=>{e.imageCanvas.toBlob(t,`image/jpeg`,.92)}):!n&&e.imageBase64&&(n=await fetch(e.imageBase64).then(e=>e.blob()).catch(()=>null)),!n)return;let a=f;f=null,a?.terminate(),r.onConfirm(n)},onClose:()=>{r.onCancel()},Crop:e,tabsIds:[i.ADJUST,i.FILTERS,i.FINETUNE,i.ANNOTATE,i.RESIZE],defaultTabId:i.ADJUST,defaultToolId:a.CROP,savingPixelRatio:1,previewPixelRatio:4}),f.render()}),e(()=>{f?.terminate(),f=null,p&&URL.revokeObjectURL(p),m?.remove(),m=null}),(()=>{var e=s(),t=d;return typeof t==`function`?n(t,e):d=e,e})()}export{d as default};