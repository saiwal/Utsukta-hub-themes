import{I as e}from"./app-web-9kNHfgxH.js";import{t}from"./app-fetch-CmSrOrFS.js";var n=`hz-theme`,r=`hz-custom-theme`,i=new Set([`dark`,`nord`,`dracula`,`monokai`,`gruvbox-dark`,`catppuccin-mocha`,`solarized-dark`,`tokyo-night`,`one-dark`,`cyberpunk`,`matrix`,`rose-pine`,`high-contrast`]),a={base:`#1e1e2e`,txt:`#cdd6f4`,accent:`#cba6f7`,isDark:!0};function o(){try{let e=localStorage.getItem(r);if(e)return{...a,...JSON.parse(e)}}catch{}return a}var[s,c]=e(localStorage.getItem(n)??`light`),[l,u]=e(o());function d(e){let{base:t,txt:n,accent:r,isDark:i}=e,a=i?`white`:`black`;return`[data-theme="custom"] {
  --color-base: ${t};
  --color-surface: color-mix(in srgb, ${t}, ${a} 8%);
  --color-elevated: color-mix(in srgb, ${t}, ${a} 18%);
  --color-overlay: color-mix(in srgb, ${t}, ${i?`black`:`white`} 8%);
  --color-txt: ${n};
  --color-muted: color-mix(in srgb, ${n}, ${t} 50%);
  --color-subtle: color-mix(in srgb, ${n}, ${t} 70%);
  --color-rim: color-mix(in srgb, ${n}, ${t} 78%);
  --color-rim-strong: color-mix(in srgb, ${n}, ${t} 68%);
  --color-accent: ${r};
  --color-accent-muted: color-mix(in srgb, ${r}, ${t} 82%);
  --color-accent-txt: color-mix(in srgb, ${r}, ${a} 15%);
  --color-accent-fg: #ffffff;
}`}function f(e){let t=document.getElementById(`hz-custom-theme`);t||(t=document.createElement(`style`),t.id=`hz-custom-theme`,document.head.appendChild(t)),t.textContent=d(e)}function p(e){f(e),document.documentElement.setAttribute(`data-theme`,`custom`),document.documentElement.classList.toggle(`dark`,e.isDark)}function m(e){if(e===`custom`){p(o());return}document.documentElement.setAttribute(`data-theme`,e),document.documentElement.classList.toggle(`dark`,i.has(e))}function h(e,t){if(c(e),e===`custom`){let e=o();if(t)try{let n=JSON.parse(t);e={...a,...n},u(e),localStorage.setItem(r,t)}catch{}p(e)}else m(e);localStorage.setItem(n,e)}function g(){return{theme:s,switchTheme:e=>{c(e),e===`custom`?p(l()):m(e),localStorage.setItem(n,e),t(`/spa/settings/display`,{method:`POST`,body:JSON.stringify({color_scheme:e})}).catch(()=>{})},customColors:l,updateCustomColors:e=>{u(e);let n=JSON.stringify(e);localStorage.setItem(r,n),s()===`custom`&&p(e),t(`/spa/settings/display`,{method:`POST`,body:JSON.stringify({color_scheme:`custom`,custom_theme_colors:n})}).catch(()=>{})}}}export{g as a,h as i,p as n,m as r,i as t};