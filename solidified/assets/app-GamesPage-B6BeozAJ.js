import{C as e,M as t,N as n,O as r,h as i,m as a,p as o,s,u as c}from"./app-web-9kNHfgxH.js";import{d as l}from"./app-routing-BV7Kukwf.js";import{D as u,_ as d,dr as f,v as p}from"./app-52EqR0qd.js";import{t as m}from"./app-SubPageLayout-BMZLKab_.js";var h=a(`<iframe style=position:absolute;inset:0;width:100%;height:100%;border:none;display:block>`),g=[`--color-base`,`--color-surface`,`--color-elevated`,`--color-overlay`,`--color-txt`,`--color-muted`,`--color-subtle`,`--color-rim`,`--color-rim-strong`,`--color-accent`,`--color-accent-muted`,`--color-accent-txt`,`--color-accent-fg`];function _(e,t){let n=getComputedStyle(document.documentElement);return`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<style>
:root{${g.map(e=>`${e}:${n.getPropertyValue(e).trim()||`inherit`}`).join(`;`)}}
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
</html>`}function v(e){return(()=>{var t=h();return n(n=>{var r=_(e.gameId,`/view/theme/solidified/assets/`),i=`Puzzle: ${e.gameId}`;return r!==n.e&&c(t,`srcdoc`,n.e=r),i!==n.t&&c(t,`title`,n.t=i),n},{e:void 0,t:void 0}),t})()}var y=a(`<p class="text-xs text-muted leading-relaxed"><a href=https://www.chiark.greenend.org.uk/~sgtatham/puzzles/ target=_blank rel="noopener noreferrer"class="underline hover:text-txt transition-colors">Simon Tatham's Portable Puzzle Collection</a> — MIT License`),b=a(`<div class="relative flex-1 min-h-0"><div class="absolute inset-0 z-10 cursor-default">`);function x(){let{t:a}=f(),c=l(),{helpMode:h}=p(),g=u.map(e=>({path:e.id,label:()=>String(a(e.labelKey)),icon:(()=>{let t=e.icon;return r(t,{class:`w-5 h-5 shrink-0`})})()})),_=t(()=>{let e=c.pathname.replace(/^\/games\/?/,``).split(`/`)[0];return u.some(t=>t.id===e)?e:u[0].id}),x=t(()=>u.find(e=>e.id===_())??u[0]);return r(m,{base:`/games`,items:g,get activeKey(){return _()},sidebarFooter:y(),contentClass:`flex-1 min-h-0 overflow-hidden flex flex-col`,get children(){return r(e,{get when(){return x()},keyed:!0,children:e=>(()=>{var t=b(),a=t.firstChild;return i(d,a,()=>`games.${e.id}`),s(t,r(v,{get gameId(){return e.id}}),null),n(e=>o(a,`pointer-events:${h()?`auto`:`none`}`,e)),t})()})}})}export{x as default};