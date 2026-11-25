import{C as e,E as t,N as n,d as r,j as i,o as a,v as o}from"./app-web-Ba9KZOhP.js";import{a as s,c,d as l}from"./app-solid-CrEvjDmY.js";import{g as u}from"./app-routing-D1iFzXRQ.js";import{s as d}from"./app-i18n-D-xYx1wr.js";import{t as f}from"./app-SubPageLayout-BpP-0H89.js";import{n as p,t as m}from"./app-helpable-DR9Hhir7.js";import{Ln as h}from"./app-znEsQV0a.js";var g=n(`<iframe style=width:100%;height:100vh;border:none;display:block>`),_=[`--color-base`,`--color-surface`,`--color-elevated`,`--color-overlay`,`--color-txt`,`--color-muted`,`--color-subtle`,`--color-rim`,`--color-rim-strong`,`--color-accent`,`--color-accent-muted`,`--color-accent-txt`,`--color-accent-fg`];function v(e,t){let n=getComputedStyle(document.documentElement);return`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<style>
:root{${_.map(e=>`${e}:${n.getPropertyValue(e).trim()||`inherit`}`).join(`;`)}}
html,body{height:100%;margin:0;padding:0;background:var(--color-surface);color:var(--color-txt);font-family:sans-serif;overflow:hidden}
body{display:flex;flex-direction:column;position:relative}
#puzzle{flex:1;min-height:0;display:flex;flex-direction:column;text-align:center}
#puzzlecanvascontain{flex:1;min-height:0;position:relative;overflow:hidden}
#apology{position:absolute;inset:0;z-index:10;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--color-surface);padding:1em;text-align:center}
#resizable{position:relative;margin:0 auto}
#puzzlecanvas{display:block;font-family:sans-serif;max-width:100%;height:auto}
#gamemenu{margin:0 0 .375em;font-weight:bold;font-size:.8em;text-align:center;color:var(--color-txt)}
#gamemenu ul{list-style:none;display:flex;flex-wrap:wrap;justify-content:center;margin:0;padding:.5px}
#gamemenu li{cursor:default;border:1px solid var(--color-rim);margin:-.5px;position:relative}
#gamemenu li[role=separator]{width:1.5em;border:0}
#gamemenu li>*{padding:.2em .75em;margin:0;display:block}
#gamemenu :disabled{color:var(--color-subtle)}
#gamemenu li>:hover:not(:disabled),#gamemenu li>.focus-within{background:var(--color-elevated)}
@media(max-width:18em){.verbiage{display:none}}
#gamemenu ul ul{display:none;position:absolute;top:100%;left:0;flex-direction:column;background:var(--color-surface);border:1px solid var(--color-rim);z-index:50}
#gamemenu ul ul.left{left:inherit;right:0}
#gamemenu li li{white-space:nowrap;text-align:left}
#gamemenu ul ul ul{top:0;left:100%}
#gamemenu ul ul ul.left{left:inherit;right:100%}
#gamemenu :hover>ul,#gamemenu .focus-within>ul{display:flex}
#gamemenu button{-webkit-appearance:none;appearance:none;font:inherit;color:inherit;background:initial;border:initial;text-align:inherit;width:100%}
#gamemenu .tick{-webkit-appearance:none;appearance:none;margin:initial;font:inherit}
#gamemenu .tick::before{content:"\\2713"}
#gamemenu .tick:not(:checked){color:transparent}
#gamemenu li>div:after{content:url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='10'%20height='10'%3E%3Cpolygon%20points='0,5,10,5,5,10'/%3E%3C/svg%3E");margin-left:.5em}
#gamemenu li li>div:after{content:url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='10'%20height='10'%3E%3Cpolygon%20points='0,0,10,5,0,10'/%3E%3C/svg%3E");float:right}
#statusbar{overflow:hidden;text-align:left;white-space:nowrap;text-overflow:ellipsis;background:var(--color-elevated);border-left:2px solid var(--color-rim-strong);border-top:2px solid var(--color-rim-strong);border-right:2px solid var(--color-rim);border-bottom:2px solid var(--color-rim);color:var(--color-muted);height:1.2em;font-size:.85em}
#resizehandle{position:absolute;z-index:1;bottom:0;right:0;cursor:se-resize;color:var(--color-muted)}
#apology{padding:0 1em;margin:1em;border:2px solid #ef4444;border-radius:.25rem;color:var(--color-txt);background:var(--color-surface)}
#apology p:first-child{text-align:center;font-weight:bold}
.permalink{font-size:.8em;margin:.25em 0;color:var(--color-muted)}
.permalink a{color:var(--color-accent);text-decoration:underline;cursor:pointer}
#dlgdimmer{width:100%;height:100%;background:#000;position:fixed;opacity:.4;left:0;top:0;z-index:99}
#dlgform{width:66.6667vw;background:var(--color-surface);color:var(--color-txt);position:fixed;border:1px solid var(--color-rim-strong);border-radius:.5rem;padding:20px;top:10vh;left:16.6667vw;z-index:100;box-sizing:border-box}
#dlgform h2{margin-top:0;color:var(--color-txt)}
#dlgform label,#dlgform input,#dlgform select,#dlgform button{color:var(--color-txt)}
#dlgform input,#dlgform select{background:var(--color-elevated);border:1px solid var(--color-rim);border-radius:.25rem;padding:.2em .4em}
#dlgform button{background:var(--color-accent);color:var(--color-accent-fg);border:none;border-radius:.25rem;padding:.3em .8em;cursor:pointer}
#dlgform button:hover{opacity:.85}
@media print{#gamemenu,#resizehandle{display:none}}
</style>
</head>
<body>
<main id="puzzle">
  <form id="gamemenu"><ul>
    <li><div tabindex="0">Game<ul class="left">
      <li><button type="button" id="specific">Enter game ID…</button></li>
      <li><button type="button" id="random">Enter random seed…</button></li>
      <li><button type="button" id="save">Download save file…</button></li>
      <li><button type="button" id="load">Upload save file…</button></li>
      <li><button type="button" id="prefs">Preferences…</button></li>
    </ul></div></li>
    <li><div tabindex="0">Type<ul role="menu" id="gametype" class="left"></ul></div></li>
    <li role="separator"></li>
    <li><button type="button" id="new">New<span class="verbiage"> game</span></button></li>
    <li><button type="button" id="restart">Restart<span class="verbiage"> game</span></button></li>
    <li><button type="button" id="undo">Undo<span class="verbiage"> move</span></button></li>
    <li><button type="button" id="redo">Redo<span class="verbiage"> move</span></button></li>
    <li><button type="button" id="solve">Solve<span class="verbiage"> game</span></button></li>
  </ul></form>
  <div id="puzzlecanvascontain">
    <div id="resizable">
      <canvas id="puzzlecanvas" tabindex="0"></canvas>
      <div id="statusbar"></div>
      <svg id="resizehandle" aria-label="resize" width="10" height="10">
        <title>Drag to resize</title>
        <path d="M8.5,1.5l-7,7m7,-4l-4,4m4,-1l-1,1" stroke="currentColor" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
  <p class="permalink">
    Game ID: <a id="permalink-desc">—</a>
    &nbsp;&middot;&nbsp;
    Seed: <a id="permalink-seed" style="display:none">—</a>
  </p>
</main>
<div id="apology">
  <p style="font-weight:bold;margin:0 0 .5em">Puzzle not loading…</p>
  <p style="margin:0;font-size:.9em">This game requires WebAssembly. Make sure puzzle files are downloaded (<code>npm run games:download</code>).</p>
</div>
<script>
(function(){
  ['permalink-desc','permalink-seed'].forEach(function(id){
    var el=document.getElementById(id);
    if(!el)return;
    new MutationObserver(function(){
      var h=el.getAttribute('href');
      if(h&&h.length>1)el.textContent=decodeURIComponent(h.slice(1));
    }).observe(el,{attributes:true,attributeFilter:['href']});
  });
})();
<\/script>
<script src="${t}puzzles/${e}.js"><\/script>
</body>
</html>`}function y(e){var n=g();return r(()=>({e:v(e.gameId,`/view/theme/stardate/assets/`),t:`Puzzle: ${e.gameId}`}),({e,t:r},i)=>{e!==i?.e&&t(n,`srcdoc`,e),r!==i?.t&&t(n,`title`,r)}),n}var b=n(`<p class="text-xs text-muted leading-relaxed"><a href=https://www.chiark.greenend.org.uk/~sgtatham/puzzles/ target=_blank rel="noopener noreferrer"class="underline hover:text-txt transition-colors">Simon Tatham's Portable Puzzle Collection</a> — MIT License`),x=n(`<div class="relative flex-1 min-h-0"><div class="absolute inset-0 z-10 cursor-default">`);function S(){let{t}=d(),n=u(),{helpMode:g}=p(),_=h.map(e=>({path:e.id,label:()=>String(t(e.labelKey)),icon:(()=>{let t=e.icon;return c(t,{class:`w-5 h-5 shrink-0`})})()})),v=l(()=>{let e=n.pathname.replace(/^\/games\/?/,``).split(`/`)[0];return h.some(t=>t.id===e)?e:h[0].id}),S=l(()=>h.find(e=>e.id===v())??h[0]);var C=b(),w=C.firstChild;return a(w),c(f,{base:`/games`,items:_,get activeKey(){return v()},sidebarFooter:C,contentClass:`flex-1 min-h-0 overflow-hidden flex flex-col`,get children(){return c(s,{get when(){return S()},keyed:!0,children:t=>(()=>{var n=x(),a=n.firstChild,s=m(()=>`games.${t.id}`);return(typeof s==`function`||Array.isArray(s))&&e(()=>s,a),o(n,c(y,{get gameId(){return t.id}}),null),r(()=>`pointer-events:${g()?`auto`:`none`}`,(e,t)=>{i(a,e,t)}),n})()})}})}export{S as default};