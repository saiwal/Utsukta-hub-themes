import{H as e,U as t,h as n,m as r}from"./app-web.js";import{n as i,r as a,t as o}from"./app-vendor-image-editor.js";var s=r(`<div style=position:fixed;inset:0;z-index:9999>`);function c(){let e=getComputedStyle(document.documentElement),t=t=>e.getPropertyValue(t).trim(),n=t(`--color-elevated`),r=t(`--color-surface`),i=t(`--color-txt`),a=t(`--color-subtle`),o=t(`--color-rim`),s=t(`--color-accent`),c=document.createElement(`style`);return c.textContent=`
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
  `,document.head.appendChild(c),c}function l(){let e=getComputedStyle(document.documentElement),t=t=>e.getPropertyValue(t).trim(),n=t(`--color-surface`),r=t(`--color-elevated`),i=t(`--color-base`),a=t(`--color-txt`),o=t(`--color-muted`),s=t(`--color-subtle`),c=t(`--color-rim`),l=t(`--color-rim-strong`),u=t(`--color-accent`),d=t(`--color-accent-fg`);return{palette:{"txt-primary":a,"txt-secondary":o,"txt-secondary-invert":n,"txt-placeholder":s,"accent-primary":u,"accent-primary-hover":u,"accent-primary-active":u,"accent-primary-disabled":o,"accent-stateless":u,"bg-primary":n,"bg-primary-light":r,"bg-primary-hover":r,"bg-primary-active":r,"bg-primary-stateless":r,"bg-secondary":r,"bg-grey":r,"bg-base-light":i,"bg-base-medium":i,"bg-stateless":r,"bg-hover":r,"bg-active":r,"bg-tooltip":r,"icon-primary":a,"icons-secondary":o,"icons-placeholder":s,"icons-muted":s,"icons-invert":n,"icons-primary-hover":a,"icons-secondary-hover":o,"btn-primary-text":d,"btn-primary-text-0-6":d,"btn-primary-text-0-4":d,"btn-disabled-text":s,"btn-secondary-text":a,"link-primary":u,"link-stateless":u,"link-hover":u,"link-active":u,"link-muted":o,"borders-primary":c,"borders-primary-hover":l,"borders-secondary":c,"borders-strong":l,"borders-button":c,"borders-item":c,"borders-base-light":c,"borders-base-medium":l,"borders-disabled":c,"border-hover-bottom":u,"border-active-bottom":u}}}function u(r){let u,d=null,f=``,p=null;return t(()=>{f=URL.createObjectURL(r.file),p=c();let e={autoResize:!1};if(r.aspect!==void 0){let t=r.aspect>1.5;e.ratio=r.aspect,e.noPresets=!1,e.presetsItems=[{titleKey:t?`cover`:`square`,descriptionKey:t?`${r.aspect.toFixed(2)}:1`:`1:1`,ratio:r.aspect}]}d=new o(u,{source:f,theme:l(),defaultSavedImageName:``,onSave:async e=>{let t=null;if(e.imageCanvas?t=await new Promise(t=>{e.imageCanvas.toBlob(t,`image/jpeg`,.92)}):e.imageBase64&&(t=await fetch(e.imageBase64).then(e=>e.blob()).catch(()=>null)),!t)return;let n=d;d=null,n?.terminate(),r.onConfirm(t)},onClose:()=>{r.onCancel()},Crop:e,tabsIds:[i.ADJUST,i.FILTERS,i.FINETUNE,i.ANNOTATE,i.RESIZE],defaultTabId:i.ADJUST,defaultToolId:a.CROP,savingPixelRatio:4,previewPixelRatio:2}),d.render()}),e(()=>{d?.terminate(),d=null,f&&URL.revokeObjectURL(f),p?.remove(),p=null}),(()=>{var e=s(),t=u;return typeof t==`function`?n(t,e):u=e,e})()}export{u as default};