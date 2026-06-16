import{r as p,v as g,L as y,t as v}from"./app.js";import{F as f,T as x,a as d}from"./app-vendor-image-editor.js";var h=v("<div style=position:fixed;inset:0;z-index:9999>");function S(){const c=getComputedStyle(document.documentElement),t=m=>c.getPropertyValue(m).trim(),r=t("--color-elevated"),o=t("--color-surface"),s=t("--color-txt"),n=t("--color-subtle"),e=t("--color-rim"),l=t("--color-accent"),a=document.createElement("style");return a.textContent=`
    .SfxInput-root {
      background-color: ${r} !important;
      color: ${s} !important;
      border-color: ${e} !important;
    }
    .SfxInput-root:hover {
      background-color: ${r} !important;
      color: ${s} !important;
      border-color: ${e} !important;
    }
    .SfxInput-root:focus-within {
      background-color: ${o} !important;
      color: ${s} !important;
      border-color: ${l} !important;
    }
    .SfxInput-Base {
      color: ${s} !important;
    }
    .SfxInput-Base::placeholder {
      color: ${n} !important;
    }
  `,document.head.appendChild(a),a}function I(){const c=getComputedStyle(document.documentElement),t=u=>c.getPropertyValue(u).trim(),r=t("--color-surface"),o=t("--color-elevated"),s=t("--color-base"),n=t("--color-txt"),e=t("--color-muted"),l=t("--color-subtle"),a=t("--color-rim"),m=t("--color-rim-strong"),i=t("--color-accent"),b=t("--color-accent-fg");return{palette:{"txt-primary":n,"txt-secondary":e,"txt-secondary-invert":r,"txt-placeholder":l,"accent-primary":i,"accent-primary-hover":i,"accent-primary-active":i,"accent-primary-disabled":e,"accent-stateless":i,"bg-primary":r,"bg-primary-light":o,"bg-primary-hover":o,"bg-primary-active":o,"bg-primary-stateless":o,"bg-secondary":o,"bg-grey":o,"bg-base-light":s,"bg-base-medium":s,"bg-stateless":o,"bg-hover":o,"bg-active":o,"bg-tooltip":o,"icon-primary":n,"icons-secondary":e,"icons-placeholder":l,"icons-muted":l,"icons-invert":r,"icons-primary-hover":n,"icons-secondary-hover":e,"btn-primary-text":b,"btn-primary-text-0-6":b,"btn-primary-text-0-4":b,"btn-disabled-text":l,"btn-secondary-text":n,"link-primary":i,"link-stateless":i,"link-hover":i,"link-active":i,"link-muted":e,"borders-primary":a,"borders-primary-hover":m,"borders-secondary":a,"borders-strong":m,"borders-button":a,"borders-item":a,"borders-base-light":a,"borders-base-medium":m,"borders-disabled":a,"border-hover-bottom":i,"border-active-bottom":i}}}function T(c){let t,r=null,o="",s=null;return p(()=>{o=URL.createObjectURL(c.file),s=S();const n={autoResize:!1};if(c.aspect!==void 0){const e=c.aspect>1.5;n.ratio=c.aspect,n.noPresets=!1,n.presetsItems=[{titleKey:e?"cover":"square",descriptionKey:e?`${c.aspect.toFixed(2)}:1`:"1:1",ratio:c.aspect}]}r=new f(t,{source:o,theme:I(),defaultSavedImageName:"",onSave:async e=>{const l=e.imageCanvas;if(l)l.toBlob(a=>{if(!a)return;const m=r;r=null,m?.terminate(),c.onConfirm(a)},"image/jpeg",.92);else if(e.imageBase64){const a=await fetch(e.imageBase64).then(i=>i.blob()),m=r;r=null,m?.terminate(),c.onConfirm(a)}},onClose:()=>{c.onCancel()},Crop:n,tabsIds:[d.ADJUST,d.FILTERS,d.FINETUNE,d.ANNOTATE,d.RESIZE],defaultTabId:d.ADJUST,defaultToolId:x.CROP,savingPixelRatio:4,previewPixelRatio:2}),r.render()}),g(()=>{r?.terminate(),r=null,o&&URL.revokeObjectURL(o),s?.remove(),s=null}),(()=>{var n=h(),e=t;return typeof e=="function"?y(e,n):t=n,n})()}export{T as default};
