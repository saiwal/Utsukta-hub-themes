import{r as p,v as g,O as y,t as v}from"./app.js";import{F as f,T as x,a as d}from"./app-vendor-image-editor.js";import"./app-vendor-dompurify.js";var h=v("<div style=position:fixed;inset:0;z-index:9999>");function S(){const a=getComputedStyle(document.documentElement),t=m=>a.getPropertyValue(m).trim(),n=t("--color-elevated"),o=t("--color-surface"),i=t("--color-txt"),r=t("--color-subtle"),e=t("--color-rim"),s=t("--color-accent"),c=document.createElement("style");return c.textContent=`
    .SfxInput-root {
      background-color: ${n} !important;
      color: ${i} !important;
      border-color: ${e} !important;
    }
    .SfxInput-root:hover {
      background-color: ${n} !important;
      color: ${i} !important;
      border-color: ${e} !important;
    }
    .SfxInput-root:focus-within {
      background-color: ${o} !important;
      color: ${i} !important;
      border-color: ${s} !important;
    }
    .SfxInput-Base {
      color: ${i} !important;
    }
    .SfxInput-Base::placeholder {
      color: ${r} !important;
    }
  `,document.head.appendChild(c),c}function I(){const a=getComputedStyle(document.documentElement),t=u=>a.getPropertyValue(u).trim(),n=t("--color-surface"),o=t("--color-elevated"),i=t("--color-base"),r=t("--color-txt"),e=t("--color-muted"),s=t("--color-subtle"),c=t("--color-rim"),m=t("--color-rim-strong"),l=t("--color-accent"),b=t("--color-accent-fg");return{palette:{"txt-primary":r,"txt-secondary":e,"txt-secondary-invert":n,"txt-placeholder":s,"accent-primary":l,"accent-primary-hover":l,"accent-primary-active":l,"accent-primary-disabled":e,"accent-stateless":l,"bg-primary":n,"bg-primary-light":o,"bg-primary-hover":o,"bg-primary-active":o,"bg-primary-stateless":o,"bg-secondary":o,"bg-grey":o,"bg-base-light":i,"bg-base-medium":i,"bg-stateless":o,"bg-hover":o,"bg-active":o,"bg-tooltip":o,"icon-primary":r,"icons-secondary":e,"icons-placeholder":s,"icons-muted":s,"icons-invert":n,"icons-primary-hover":r,"icons-secondary-hover":e,"btn-primary-text":b,"btn-primary-text-0-6":b,"btn-primary-text-0-4":b,"btn-disabled-text":s,"btn-secondary-text":r,"link-primary":l,"link-stateless":l,"link-hover":l,"link-active":l,"link-muted":e,"borders-primary":c,"borders-primary-hover":m,"borders-secondary":c,"borders-strong":m,"borders-button":c,"borders-item":c,"borders-base-light":c,"borders-base-medium":m,"borders-disabled":c,"border-hover-bottom":l,"border-active-bottom":l}}}function E(a){let t,n=null,o="",i=null;return p(()=>{o=URL.createObjectURL(a.file),i=S();const r={autoResize:!1};if(a.aspect!==void 0){const e=a.aspect>1.5;r.ratio=a.aspect,r.noPresets=!1,r.presetsItems=[{titleKey:e?"cover":"square",descriptionKey:e?`${a.aspect.toFixed(2)}:1`:"1:1",ratio:a.aspect}]}n=new f(t,{source:o,theme:I(),defaultSavedImageName:"",onSave:async e=>{let s=null;if(e.imageCanvas?s=await new Promise(m=>{e.imageCanvas.toBlob(m,"image/jpeg",.92)}):e.imageBase64&&(s=await fetch(e.imageBase64).then(m=>m.blob()).catch(()=>null)),!s)return;const c=n;n=null,c?.terminate(),a.onConfirm(s)},onClose:()=>{a.onCancel()},Crop:r,tabsIds:[d.ADJUST,d.FILTERS,d.FINETUNE,d.ANNOTATE,d.RESIZE],defaultTabId:d.ADJUST,defaultToolId:x.CROP,savingPixelRatio:4,previewPixelRatio:2}),n.render()}),g(()=>{n?.terminate(),n=null,o&&URL.revokeObjectURL(o),i?.remove(),i=null}),(()=>{var r=h(),e=t;return typeof e=="function"?y(e,r):t=r,r})()}export{E as default};
