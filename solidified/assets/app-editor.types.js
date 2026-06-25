import{g as ie,n as $,a as s,b as A,p as _,E as H,L as J,aA as Se,at as Ne,t as m,w as ae,c as _e,bv as le,r as Pe,m as Z,O as ee,V as te,o as Le,q as Oe}from"./app.js";import{b as se}from"./app-bbcode.js";var Me=m('<span class="font-bold text-xs">B'),He=m('<span class="italic text-xs">I'),Be=m('<span class="underline text-xs">U'),De=m('<span class="line-through text-xs">S'),Ie=m('<span class="text-xs bg-yellow-300 text-yellow-900 px-0.5 rounded-sm leading-tight">H'),ze=m('<span class="text-xs font-bold"style=color:#e74c3c>A'),Fe=m("<span class=text-xs style=font-family:serif>F"),qe=m("<span class=text-xs>Sz"),Ue=m('<select class="text-xs rounded px-1 py-0.5 bg-surface border border-rim text-txt hover:bg-elevated transition-colors cursor-pointer"><option value=p></option><option value=h1>H1</option><option value=h2>H2</option><option value=h3>H3</option><option value=h4>H4</option><option value=h5>H5</option><option value=h6>H6'),je=m("<span class=text-xs>❝"),Ve=m("<span class=text-xs>❝A"),We=m('<span class="text-xs font-mono">&lt;/>'),Xe=m('<span class="text-xs font-bold">—'),Ke=m("<span class=text-xs>• –"),Ge=m("<span class=text-xs>1."),Ye=m("<span class=text-xs>▶"),Qe=m("<span class=text-xs>♪"),Je=m("<span class=text-xs>⊞"),Ze=m("<span class=text-xs>◢"),et=m("<span class=flex-1>"),tt=m("<span class=text-xs>✕"),rt=m('<div class="flex flex-wrap items-center gap-0.5 px-2 py-1 bg-surface border-b border-rim">'),nt=m('<span class="w-px h-4 bg-rim mx-0.5 self-center">'),ot=m('<button type=button class="px-1.5 py-0.5 rounded text-txt hover:bg-elevated transition-colors">');function it(e){const{t}=ie(),r=()=>e.tab==="source",n=()=>e.tab==="wysiwyg",o=()=>e.level==="comment",i=()=>e.level==="full",l=(a,u)=>{const f=e.editorRef();f&&(f.focus(),document.execCommand(a,!1,u))},g=(a,u)=>{const f=e.editorRef();if(!f)return;f.focus();const h=window.getSelection();if(!h||h.rangeCount===0||h.isCollapsed){document.execCommand("insertHTML",!1,`${a}${u}`);return}const x=h.getRangeAt(0).cloneContents(),p=document.createElement("div");p.appendChild(x),document.execCommand("insertHTML",!1,`${a}${p.innerHTML}${u}`)},c=(a,u)=>{const f=e.textareaRef();if(!f)return;const{selectionStart:h,selectionEnd:x,value:p}=f,E=p.slice(h,x);e.onSourceChange(p.slice(0,h)+a+E+u+p.slice(x)),requestAnimationFrame(()=>{f.focus(),f.setSelectionRange(h+a.length,h+a.length+E.length)})},v=a=>{const u=e.textareaRef();if(!u)return;const{selectionStart:f,value:h}=u;e.onSourceChange(h.slice(0,f)+a+h.slice(f)),requestAnimationFrame(()=>{u.focus(),u.setSelectionRange(f+a.length,f+a.length)})},T=()=>r()?c("[b]","[/b]"):l("bold"),O=()=>r()?c("[i]","[/i]"):l("italic"),I=()=>r()?c("[u]","[/u]"):l("underline"),z=()=>r()?c("[hl]","[/hl]"):l("hiliteColor","yellow"),y=()=>{if(r()){c("[s]","[/s]");return}const a=e.editorRef();if(!a)return;a.focus();const u=window.getSelection();if(!u||u.rangeCount===0)return;const f=u.getRangeAt(0).commonAncestorContainer,x=(f.nodeType===Node.TEXT_NODE?f.parentElement:f)?.closest?.("s, strike");if(x&&a.contains(x)){const p=document.createRange();p.selectNode(x),u.removeAllRanges(),u.addRange(p),document.execCommand("insertHTML",!1,x.innerHTML)}else l("strikeThrough")},d=()=>{const a=prompt("Color (name or #hex):","red");a&&(r()?c(`[color=${a}]`,"[/color]"):l("foreColor",a))},C=()=>{const a=prompt("Font name:","courier");a&&(r()?c(`[font=${a}]`,"[/font]"):l("fontName",a))},k=()=>{const a=prompt("Size (small, medium, large, xx-large):","large");a&&(r()?c(`[size=${a}]`,"[/size]"):l("fontSize",{"xx-small":"1","x-small":"1",small:"2",medium:"3",large:"4","x-large":"5","xx-large":"6"}[a]??"4"))},R=()=>r()?c("[quote]","[/quote]"):l("formatBlock","blockquote"),S=()=>{const a=prompt("Author name:");a&&(r()?c(`[quote=${a}]`,"[/quote]"):g(`<span class="bb-quote">${a} wrote:</span><blockquote>`,"</blockquote>"))},ye=()=>r()?c("[code]","[/code]"):l("formatBlock","pre"),$e=()=>r()?v(`[hr]
`):l("insertHorizontalRule"),we=()=>{if(r()){const p=prompt("URL:");if(!p)return;c(`[url=${p}]`,"[/url]");return}const a=e.editorRef();if(!a)return;const u=window.getSelection();let f=null;u&&u.rangeCount>0&&(f=u.getRangeAt(0).cloneRange());const h=f&&!f.collapsed,x=prompt("URL:");if(x)if(a.focus(),f&&(u.removeAllRanges(),u.addRange(f)),h)document.execCommand("createLink",!1,x);else{const p=document.createElement("a");p.href=x,p.textContent=x;const E=document.createElement("div");E.appendChild(p),document.execCommand("insertHTML",!1,E.innerHTML)}},ke=()=>{const a=prompt("Image URL:");a&&(r()?v(`[img]${a}[/img]`):l("insertImage",a))},Ae=()=>{const a=prompt("Video URL:");a&&(r()?v(`[video]${a}[/video]`):l("insertHTML",`<video src="${a}" controls preload="none" style="max-width:100%"></video>`))},Ce=()=>{const a=prompt("Audio URL:");a&&(r()?v(`[audio]${a}[/audio]`):l("insertHTML",`<audio src="${a}" controls preload="none"></audio>`))},Te=()=>{const a=prompt("Number of columns:","2");if(!a)return;const u=prompt("Number of rows (excluding header):","2");if(!u)return;const f=Math.max(1,parseInt(a,10)||2),h=Math.max(0,parseInt(u,10)||2);if(r()){const x="[tr]"+Array.from({length:f},(E,N)=>`[th]Header ${N+1}[/th]`).join("")+"[/tr]",p=Array.from({length:h},(E,N)=>"[tr]"+Array.from({length:f},(Re,F)=>`[td]Cell ${N+1}-${F+1}[/td]`).join("")+"[/tr]").join(`
`);v(`[table border=1]
${x}
${p}
[/table]
`)}else{const x="<tr>"+Array.from({length:f},(E,N)=>`<th>Header ${N+1}</th>`).join("")+"</tr>",p=Array.from({length:h},(E,N)=>"<tr>"+Array.from({length:f},(Re,F)=>`<td>Cell ${N+1}-${F+1}</td>`).join("")+"</tr>").join("");l("insertHTML",`<table border="1">${x}${p}</table>`)}},Ee=()=>{const a=prompt("Spoiler label (optional):","")??"",u=a?`[spoiler=${a}]`:"[spoiler]";r()?c(u,"[/spoiler]"):g(`<details><summary>${a||"Spoiler"}</summary><div>`,"</div></details>")};return(()=>{var a=rt();return $(a,s(b,{get title(){return t("editor.bold")},onPress:T,get children(){return Me()}}),null),$(a,s(b,{get title(){return t("editor.italic")},onPress:O,get children(){return He()}}),null),$(a,s(b,{get title(){return t("editor.underline")},onPress:I,get children(){return Be()}}),null),$(a,s(b,{get title(){return t("editor.strikethrough")},onPress:y,get children(){return De()}}),null),$(a,s(b,{get title(){return t("editor.highlight")},onPress:z,get children(){return Ie()}}),null),$(a,s(A,{get when(){return!o()},get children(){return[s(M,{}),s(b,{title:"Text color [color=X]",onPress:d,get children(){return ze()}}),s(b,{title:"Font family [font=X]",onPress:C,get children(){return Fe()}}),s(b,{title:"Font size [size=X]",onPress:k,get children(){return qe()}}),s(M,{}),s(A,{get when(){return J(()=>!!i())()&&n()},get children(){var u=Ue(),f=u.firstChild;return u.addEventListener("change",h=>{const x=h.currentTarget.value,p=e.editorRef();p&&(p.focus(),document.execCommand("formatBlock",!1,x),h.currentTarget.value="p")}),u.$$mousedown=h=>h.stopPropagation(),$(f,()=>t("editor.heading_label")),_(()=>H(u,"title",t("editor.heading"))),u}}),s(b,{get title(){return t("editor.blockquote")},onPress:R,get children(){return je()}}),s(A,{get when(){return i()},get children(){return[s(b,{title:"Quote with author [quote=Author]",onPress:S,get children(){return Ve()}}),s(b,{get title(){return t("editor.code_block")},onPress:ye,get children(){return We()}})]}}),s(b,{title:"Horizontal rule [hr]",onPress:$e,get children(){return Xe()}}),s(A,{get when(){return n()},get children(){return[s(M,{}),s(b,{get title(){return t("editor.bullet_list")},onPress:()=>l("insertUnorderedList"),get children(){return Ke()}}),s(b,{get title(){return t("editor.numbered_list")},onPress:()=>l("insertOrderedList"),get children(){return Ge()}})]}}),s(M,{}),s(b,{get title(){return t("editor.link")},onPress:we,get children(){return s(Se,{class:"w-4 h-4"})}}),s(b,{title:"Image [img]",onPress:ke,get children(){return s(Ne,{class:"w-4 h-4"})}}),s(b,{title:"Video [video]",onPress:Ae,get children(){return Ye()}}),s(b,{title:"Audio [audio]",onPress:Ce,get children(){return Qe()}}),s(A,{get when(){return i()},get children(){return[s(M,{}),s(b,{title:"Insert table [table]",onPress:Te,get children(){return Je()}}),s(b,{title:"Spoiler [spoiler]",onPress:Ee,get children(){return Ze()}})]}}),s(A,{get when(){return J(()=>!!i())()&&n()},get children(){return[et(),s(b,{get title(){return t("editor.clear_formatting")},onPress:()=>{l("formatBlock","p"),l("removeFormat")},get children(){return tt()}})]}})]}}),null),a})()}function M(){return nt()}function b(e){return(()=>{var t=ot();return t.$$mousedown=r=>{r.preventDefault(),e.onPress()},$(t,()=>e.children),_(()=>H(t,"title",e.title)),t})()}ae(["mousedown"]);var at=m('<iframe sandbox=allow-same-origin class="w-full h-full border-0">');function lt(e){const t=_e(()=>{const o=e.body;return e.mimetype==="text/html"?o:e.mimetype==="text/markdown"?le.parse(o):se(o)}),r=()=>{const o=getComputedStyle(document.documentElement),i=l=>o.getPropertyValue(l).trim();return[`--color-base: ${i("--color-base")}`,`--color-surface: ${i("--color-surface")}`,`--color-elevated: ${i("--color-elevated")}`,`--color-overlay: ${i("--color-overlay")}`,`--color-txt: ${i("--color-txt")}`,`--color-muted: ${i("--color-muted")}`,`--color-subtle: ${i("--color-subtle")}`,`--color-rim: ${i("--color-rim")}`,`--color-accent: ${i("--color-accent")}`,`--color-accent-txt: ${i("--color-accent-txt")}`].join("; ")},n=()=>`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
    :root { ${r()} }
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      font-size: 14px;
      line-height: 1.65;
      padding: 16px;
      margin: 0;
      color: var(--color-txt);
      background: var(--color-surface);
    }
    p { margin: 0.5em 0; }
    p:first-child { margin-top: 0; }
    p:last-child  { margin-bottom: 0; }
    h1, h2, h3, h4, h5, h6 {
      color: var(--color-txt);
      line-height: 1.3;
      margin: 0.75em 0 0.25em;
      font-weight: 700;
    }
    h1 { font-size: 1.5rem; }
    h2 { font-size: 1.25rem; }
    h3 { font-size: 1.125rem; font-weight: 600; }
    h4 { font-size: 1rem; font-weight: 600; }
    h5, h6 { font-size: 0.9rem; font-weight: 600; }
    a { color: var(--color-accent); }
    strong { font-weight: 700; }
    em { font-style: italic; }
    s, del { text-decoration: line-through; }
    mark { background: #facc15; color: #1a1a1a; padding: 0 2px; border-radius: 2px; }
    blockquote {
      border-left: 3px solid var(--color-rim);
      margin: 0.75em 0;
      padding: 0.25em 0 0.25em 12px;
      color: var(--color-muted);
    }
    hr { border: none; border-top: 1px solid var(--color-rim); margin: 1em 0; }
    pre {
      background: var(--color-overlay);
      color: var(--color-txt);
      padding: 10px 12px;
      border-radius: 6px;
      overflow-x: auto;
      font-size: 0.875rem;
      margin: 0.75em 0;
    }
    code {
      background: var(--color-overlay);
      color: var(--color-txt);
      padding: 1px 5px;
      border-radius: 4px;
      font-size: 0.85em;
    }
    pre code { background: transparent; padding: 0; border-radius: 0; }
    ul { list-style: disc;    padding-left: 1.25rem; margin: 0.5em 0; }
    ol { list-style: decimal; padding-left: 1.25rem; margin: 0.5em 0; }
    li { margin: 0.125rem 0; }
    img { max-width: 100%; height: auto; }
    video, audio { max-width: 100%; display: block; margin: 0.5em 0; }
    table { border-collapse: collapse; width: 100%; margin: 0.75em 0; }
    td, th { border: 1px solid var(--color-rim); padding: 6px 10px; text-align: left; }
    th { background: var(--color-elevated); font-weight: 600; }
    details { border: 1px solid var(--color-rim); border-radius: 6px; padding: 8px 12px; margin: 0.5em 0; }
    summary { cursor: pointer; color: var(--color-muted); font-size: 0.875rem; }
  </style>
</head>
<body>${t()}</body>
</html>`;return(()=>{var o=at();return _(()=>H(o,"srcdoc",n())),o})()}function re(e,t){return t==="text/html"?e:t==="text/markdown"?le.parse(e):se(e)}function st(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e}function W(e,t){return Array(t+1).join(e)}function ce(e){return e.replace(/^\n*/,"")}function ue(e){for(var t=e.length;t>0&&e[t-1]===`
`;)t--;return e.substring(0,t)}function de(e){return ue(ce(e))}var ct=["ADDRESS","ARTICLE","ASIDE","AUDIO","BLOCKQUOTE","BODY","CANVAS","CENTER","DD","DIR","DIV","DL","DT","FIELDSET","FIGCAPTION","FIGURE","FOOTER","FORM","FRAMESET","H1","H2","H3","H4","H5","H6","HEADER","HGROUP","HR","HTML","ISINDEX","LI","MAIN","MENU","NAV","NOFRAMES","NOSCRIPT","OL","OUTPUT","P","PRE","SECTION","TABLE","TBODY","TD","TFOOT","TH","THEAD","TR","UL"];function X(e){return K(e,ct)}var fe=["AREA","BASE","BR","COL","COMMAND","EMBED","HR","IMG","INPUT","KEYGEN","LINK","META","PARAM","SOURCE","TRACK","WBR"];function me(e){return K(e,fe)}function ut(e){return he(e,fe)}var ge=["A","TABLE","THEAD","TBODY","TFOOT","TH","TD","IFRAME","SCRIPT","AUDIO","VIDEO"];function dt(e){return K(e,ge)}function ft(e){return he(e,ge)}function K(e,t){return t.indexOf(e.nodeName)>=0}function he(e,t){return e.getElementsByTagName&&t.some(function(r){return e.getElementsByTagName(r).length})}var mt=[[/\\/g,"\\\\"],[/\*/g,"\\*"],[/^-/g,"\\-"],[/^\+ /g,"\\+ "],[/^(=+)/g,"\\$1"],[/^(#{1,6}) /g,"\\$1 "],[/`/g,"\\`"],[/^~~~/g,"\\~~~"],[/\[/g,"\\["],[/\]/g,"\\]"],[/^>/g,"\\>"],[/_/g,"\\_"],[/^(\d+)\. /g,"$1\\. "]];function pe(e){return mt.reduce(function(t,r){return t.replace(r[0],r[1])},e)}var w={};w.paragraph={filter:"p",replacement:function(e){return`

`+e+`

`}};w.lineBreak={filter:"br",replacement:function(e,t,r){return r.br+`
`}};w.heading={filter:["h1","h2","h3","h4","h5","h6"],replacement:function(e,t,r){var n=Number(t.nodeName.charAt(1));if(r.headingStyle==="setext"&&n<3){var o=W(n===1?"=":"-",e.length);return`

`+e+`
`+o+`

`}else return`

`+W("#",n)+" "+e+`

`}};w.blockquote={filter:"blockquote",replacement:function(e){return e=de(e).replace(/^/gm,"> "),`

`+e+`

`}};w.list={filter:["ul","ol"],replacement:function(e,t){var r=t.parentNode;return r.nodeName==="LI"&&r.lastElementChild===t?`
`+e:`

`+e+`

`}};w.listItem={filter:"li",replacement:function(e,t,r){var n=r.bulletListMarker+"   ",o=t.parentNode;if(o.nodeName==="OL"){var i=o.getAttribute("start"),l=Array.prototype.indexOf.call(o.children,t);n=(i?Number(i)+l:l+1)+".  "}var g=/\n$/.test(e);return e=de(e)+(g?`
`:""),e=e.replace(/\n/gm,`
`+" ".repeat(n.length)),n+e+(t.nextSibling?`
`:"")}};w.indentedCodeBlock={filter:function(e,t){return t.codeBlockStyle==="indented"&&e.nodeName==="PRE"&&e.firstChild&&e.firstChild.nodeName==="CODE"},replacement:function(e,t,r){return`

    `+t.firstChild.textContent.replace(/\n/g,`
    `)+`

`}};w.fencedCodeBlock={filter:function(e,t){return t.codeBlockStyle==="fenced"&&e.nodeName==="PRE"&&e.firstChild&&e.firstChild.nodeName==="CODE"},replacement:function(e,t,r){for(var n=t.firstChild.getAttribute("class")||"",o=(n.match(/language-(\S+)/)||[null,""])[1],i=t.firstChild.textContent,l=r.fence.charAt(0),g=3,c=new RegExp("^"+l+"{3,}","gm"),v;v=c.exec(i);)v[0].length>=g&&(g=v[0].length+1);var T=W(l,g);return`

`+T+o+`
`+i.replace(/\n$/,"")+`
`+T+`

`}};w.horizontalRule={filter:"hr",replacement:function(e,t,r){return`

`+r.hr+`

`}};w.inlineLink={filter:function(e,t){return t.linkStyle==="inlined"&&e.nodeName==="A"&&e.getAttribute("href")},replacement:function(e,t){var r=G(t.getAttribute("href")),n=Y(B(t.getAttribute("title"))),o=n?' "'+n+'"':"";return"["+e+"]("+r+o+")"}};w.referenceLink={filter:function(e,t){return t.linkStyle==="referenced"&&e.nodeName==="A"&&e.getAttribute("href")},replacement:function(e,t,r){var n=G(t.getAttribute("href")),o=B(t.getAttribute("title"));o&&(o=' "'+Y(o)+'"');var i,l;switch(r.linkReferenceStyle){case"collapsed":i="["+e+"][]",l="["+e+"]: "+n+o;break;case"shortcut":i="["+e+"]",l="["+e+"]: "+n+o;break;default:var g=this.references.length+1;i="["+e+"]["+g+"]",l="["+g+"]: "+n+o}return this.references.push(l),i},references:[],append:function(e){var t="";return this.references.length&&(t=`

`+this.references.join(`
`)+`

`,this.references=[]),t}};w.emphasis={filter:["em","i"],replacement:function(e,t,r){return e.trim()?r.emDelimiter+e+r.emDelimiter:""}};w.strong={filter:["strong","b"],replacement:function(e,t,r){return e.trim()?r.strongDelimiter+e+r.strongDelimiter:""}};w.code={filter:function(e){var t=e.previousSibling||e.nextSibling,r=e.parentNode.nodeName==="PRE"&&!t;return e.nodeName==="CODE"&&!r},replacement:function(e){if(!e)return"";e=e.replace(/\r?\n|\r/g," ");for(var t=/^`|^ .*?[^ ].* $|`$/.test(e)?" ":"",r="`",n=e.match(/`+/gm)||[];n.indexOf(r)!==-1;)r=r+"`";return r+t+e+t+r}};w.image={filter:"img",replacement:function(e,t){var r=pe(B(t.getAttribute("alt"))),n=G(t.getAttribute("src")||""),o=B(t.getAttribute("title")),i=o?' "'+Y(o)+'"':"";return n?"!["+r+"]("+n+i+")":""}};function B(e){return e?e.replace(/(\n+\s*)+/g,`
`):""}function G(e){var t=e.replace(/([<>()])/g,"\\$1");return t.indexOf(" ")>=0?"<"+t+">":t}function Y(e){return e.replace(/"/g,'\\"')}function ve(e){this.options=e,this._keep=[],this._remove=[],this.blankRule={replacement:e.blankReplacement},this.keepReplacement=e.keepReplacement,this.defaultRule={replacement:e.defaultReplacement},this.array=[];for(var t in e.rules)this.array.push(e.rules[t])}ve.prototype={add:function(e,t){this.array.unshift(t)},keep:function(e){this._keep.unshift({filter:e,replacement:this.keepReplacement})},remove:function(e){this._remove.unshift({filter:e,replacement:function(){return""}})},forNode:function(e){if(e.isBlank)return this.blankRule;var t;return(t=q(this.array,e,this.options))||(t=q(this._keep,e,this.options))||(t=q(this._remove,e,this.options))?t:this.defaultRule},forEach:function(e){for(var t=0;t<this.array.length;t++)e(this.array[t],t)}};function q(e,t,r){for(var n=0;n<e.length;n++){var o=e[n];if(gt(o,t,r))return o}}function gt(e,t,r){var n=e.filter;if(typeof n=="string"){if(n===t.nodeName.toLowerCase())return!0}else if(Array.isArray(n)){if(n.indexOf(t.nodeName.toLowerCase())>-1)return!0}else if(typeof n=="function"){if(n.call(e,t,r))return!0}else throw new TypeError("`filter` needs to be a string, array, or function")}function ht(e){var t=e.element,r=e.isBlock,n=e.isVoid,o=e.isPre||function(O){return O.nodeName==="PRE"};if(!(!t.firstChild||o(t))){for(var i=null,l=!1,g=null,c=ne(g,t,o);c!==t;){if(c.nodeType===3||c.nodeType===4){var v=c.data.replace(/[ \r\n\t]+/g," ");if((!i||/ $/.test(i.data))&&!l&&v[0]===" "&&(v=v.substr(1)),!v){c=U(c);continue}c.data=v,i=c}else if(c.nodeType===1)r(c)||c.nodeName==="BR"?(i&&(i.data=i.data.replace(/ $/,"")),i=null,l=!1):n(c)||o(c)?(i=null,l=!0):i&&(l=!1);else{c=U(c);continue}var T=ne(g,c,o);g=c,c=T}i&&(i.data=i.data.replace(/ $/,""),i.data||U(i))}}function U(e){var t=e.nextSibling||e.parentNode;return e.parentNode.removeChild(e),t}function ne(e,t,r){return e&&e.parentNode===t||r(t)?t.nextSibling||t.parentNode:t.firstChild||t.nextSibling||t.parentNode}var Q=typeof window<"u"?window:{};function pt(){var e=Q.DOMParser,t=!1;try{new e().parseFromString("","text/html")&&(t=!0)}catch{}return t}function vt(){var e=function(){};return bt()?e.prototype.parseFromString=function(t){var r=new window.ActiveXObject("htmlfile");return r.designMode="on",r.open(),r.write(t),r.close(),r}:e.prototype.parseFromString=function(t){var r=document.implementation.createHTMLDocument("");return r.open(),r.write(t),r.close(),r},e}function bt(){var e=!1;try{document.implementation.createHTMLDocument("").open()}catch{Q.ActiveXObject&&(e=!0)}return e}var xt=pt()?Q.DOMParser:vt();function yt(e,t){var r;if(typeof e=="string"){var n=$t().parseFromString('<x-turndown id="turndown-root">'+e+"</x-turndown>","text/html");r=n.getElementById("turndown-root")}else r=e.cloneNode(!0);return ht({element:r,isBlock:X,isVoid:me,isPre:t.preformattedCode?wt:null}),r}var j;function $t(){return j=j||new xt,j}function wt(e){return e.nodeName==="PRE"||e.nodeName==="CODE"}function kt(e,t){return e.isBlock=X(e),e.isCode=e.nodeName==="CODE"||e.parentNode.isCode,e.isBlank=At(e),e.flankingWhitespace=Ct(e,t),e}function At(e){return!me(e)&&!dt(e)&&/^\s*$/i.test(e.textContent)&&!ut(e)&&!ft(e)}function Ct(e,t){if(e.isBlock||t.preformattedCode&&e.isCode)return{leading:"",trailing:""};var r=Tt(e.textContent);return r.leadingAscii&&oe("left",e,t)&&(r.leading=r.leadingNonAscii),r.trailingAscii&&oe("right",e,t)&&(r.trailing=r.trailingNonAscii),{leading:r.leading,trailing:r.trailing}}function Tt(e){var t=e.match(/^(([ \t\r\n]*)(\s*))(?:(?=\S)[\s\S]*\S)?((\s*?)([ \t\r\n]*))$/);return{leading:t[1],leadingAscii:t[2],leadingNonAscii:t[3],trailing:t[4],trailingNonAscii:t[5],trailingAscii:t[6]}}function oe(e,t,r){var n,o,i;return e==="left"?(n=t.previousSibling,o=/ $/):(n=t.nextSibling,o=/^ /),n&&(n.nodeType===3?i=o.test(n.nodeValue):r.preformattedCode&&n.nodeName==="CODE"?i=!1:n.nodeType===1&&!X(n)&&(i=o.test(n.textContent))),i}var Et=Array.prototype.reduce;function D(e){if(!(this instanceof D))return new D(e);var t={rules:w,headingStyle:"setext",hr:"* * *",bulletListMarker:"*",codeBlockStyle:"indented",fence:"```",emDelimiter:"_",strongDelimiter:"**",linkStyle:"inlined",linkReferenceStyle:"full",br:"  ",preformattedCode:!1,blankReplacement:function(r,n){return n.isBlock?`

`:""},keepReplacement:function(r,n){return n.isBlock?`

`+n.outerHTML+`

`:n.outerHTML},defaultReplacement:function(r,n){return n.isBlock?`

`+r+`

`:r}};this.options=st({},t,e),this.rules=new ve(this.options)}D.prototype={turndown:function(e){if(!Nt(e))throw new TypeError(e+" is not a string, or an element/document/fragment node.");if(e==="")return"";var t=be.call(this,new yt(e,this.options));return Rt.call(this,t)},use:function(e){if(Array.isArray(e))for(var t=0;t<e.length;t++)this.use(e[t]);else if(typeof e=="function")e(this);else throw new TypeError("plugin must be a Function or an Array of Functions");return this},addRule:function(e,t){return this.rules.add(e,t),this},keep:function(e){return this.rules.keep(e),this},remove:function(e){return this.rules.remove(e),this},escape:function(e){return pe(e)}};function be(e){var t=this;return Et.call(e.childNodes,function(r,n){n=new kt(n,t.options);var o="";return n.nodeType===3?o=n.isCode?n.nodeValue:t.escape(n.nodeValue):n.nodeType===1&&(o=St.call(t,n)),xe(r,o)},"")}function Rt(e){var t=this;return this.rules.forEach(function(r){typeof r.append=="function"&&(e=xe(e,r.append(t.options)))}),e.replace(/^[\t\r\n]+/,"").replace(/[\t\r\n\s]+$/,"")}function St(e){var t=this.rules.forNode(e),r=be.call(this,e),n=e.flankingWhitespace;return(n.leading||n.trailing)&&(r=r.trim()),n.leading+t.replacement(r,e,this.options)+n.trailing}function xe(e,t){var r=ue(e),n=ce(t),o=Math.max(e.length-r.length,t.length-n.length),i=`

`.substring(0,o);return r+i+n}function Nt(e){return e!=null&&(typeof e=="string"||e.nodeType&&(e.nodeType===1||e.nodeType===9||e.nodeType===11))}const _t=new D({headingStyle:"atx",bulletListMarker:"-"});function Pt(e,t){return t==="text/html"?e:t==="text/markdown"?_t.turndown(e):Lt(e)}function Lt(e){const t=new DOMParser().parseFromString(e,"text/html");return L(t.body).trim()}function P(e,t){const n=(e.getAttribute("style")??"").match(new RegExp(`(?:^|;)\\s*${t}\\s*:\\s*([^;]+)`,"i"));return n?n[1].trim():""}function L(e){if(e.nodeType===Node.TEXT_NODE)return e.textContent??"";const t=e,r=()=>Array.from(t.childNodes).map(L).join("");switch(t.tagName?.toLowerCase()){case"b":case"strong":return`[b]${r()}[/b]`;case"i":case"em":return`[i]${r()}[/i]`;case"u":return`[u]${r()}[/u]`;case"s":case"strike":case"del":return`[s]${r()}[/s]`;case"mark":return`[hl]${r()}[/hl]`;case"code":return`[code]${r()}[/code]`;case"pre":return`[code]${t.textContent??""}[/code]`;case"blockquote":return`[quote]${r()}[/quote]`;case"h1":return`[h1]${r()}[/h1]
`;case"h2":return`[h2]${r()}[/h2]
`;case"h3":return`[h3]${r()}[/h3]
`;case"h4":return`[h4]${r()}[/h4]
`;case"h5":return`[h5]${r()}[/h5]
`;case"h6":return`[h6]${r()}[/h6]
`;case"p":{const o=P(t,"text-align"),i=r();return o==="center"?`[center]${i}[/center]
`:`${i}
`}case"br":return`
`;case"hr":return`
[hr]
`;case"center":return`[center]${r()}[/center]`;case"a":return`[url=${t.getAttribute("href")??""}]${r()}[/url]`;case"img":{const o=t.getAttribute("src")??"",i=t.getAttribute("alt")??"";return i?`[img alt="${i}"]${o}[/img]`:`[img]${o}[/img]`}case"video":return`[video]${t.getAttribute("src")??""}[/video]`;case"audio":return`[audio]${t.getAttribute("src")??""}[/audio]`;case"ul":return`[list]
${Array.from(t.querySelectorAll(":scope > li")).map(i=>`[*]${L(i)}`).join(`
`)}
[/list]
`;case"ol":return`[list=1]
${Array.from(t.querySelectorAll(":scope > li")).map(i=>`[*]${L(i)}`).join(`
`)}
[/list]
`;case"li":return r();case"table":return`[table]
${Array.from(t.querySelectorAll("tr")).map(l=>`[tr]${Array.from(l.children).map(c=>{const v=c.tagName.toLowerCase()==="th"?"th":"td";return`[${v}]${L(c)}[/${v}]`}).join("")}[/tr]`).join(`
`)}
[/table]
`;case"tr":case"th":case"td":return r();case"details":{const o=t.querySelector("summary")?.textContent?.trim()??"",i=Array.from(t.childNodes).filter(l=>l.tagName?.toLowerCase()!=="summary").map(L).join("");return o?`[spoiler=${o}]${i}[/spoiler]`:`[spoiler]${i}[/spoiler]`}case"summary":return"";case"font":{let o=r();const i=t.getAttribute("size"),l=t.getAttribute("face"),g=t.getAttribute("color");return i&&(o=`[size=${{1:"xx-small",2:"small",3:"medium",4:"large",5:"x-large",6:"xx-large",7:"xx-large"}[i]??"medium"}]${o}[/size]`),l&&(o=`[font=${l}]${o}[/font]`),g&&(o=`[color=${g}]${o}[/color]`),o}case"span":{let o=r();const i=P(t,"background-color"),l=P(t,"font-size"),g=P(t,"font-family"),c=P(t,"color");return i&&i!=="transparent"&&(o=`[hl=${i}]${o}[/hl]`),l&&(o=`[size=${l}]${o}[/size]`),g&&(o=`[font=${g}]${o}[/font]`),c&&(o=`[color=${c}]${o}[/color]`),o}case"div":{const o=P(t,"text-align"),i=r();return o==="center"?`[center]${i}[/center]
`:`${i}
`}case"body":return r();default:return r()}}var Ot=m('<div class="flex bg-elevated border-b border-rim">'),Mt=m('<div contenteditable dir=ltr class="grow overflow-y-auto p-3 outline-none text-sm text-txt [&amp;_img]:max-w-full [&amp;_img]:h-auto empty:before:content-[attr(data-placeholder)] empty:before:text-muted empty:before:pointer-events-none">'),Ht=m('<textarea class="grow overflow-y-auto w-full p-3 text-sm font-mono bg-surface text-txt outline-none resize-none">'),Bt=m('<div class="grow min-h-0 overflow-y-auto">'),Dt=m('<div class="rich-editor rounded-lg border border-rim overflow-hidden bg-surface flex flex-col flex-1 min-h-0">'),It=m("<button type=button>");function qt(e){const{t}=ie();let r,n,o=!1;const i=()=>e.mimetype??"text/bbcode",l=()=>e.minHeight??(e.capabilities.toolbar==="comment"?"60px":"140px");Pe(()=>{r&&(r.innerHTML=re(e.body,i()))}),Z(()=>{e.body===""&&r&&r.innerHTML!==""&&(r.innerHTML="")}),Z(()=>{if(e.tab==="wysiwyg"&&r&&!o){const y=re(e.body,i());if(r.innerHTML!==y){r.innerHTML=y;const d=document.createRange(),C=window.getSelection();d.selectNodeContents(r),d.collapse(!1),C?.removeAllRanges(),C?.addRange(d)}}});const g=()=>{o=!0,r&&e.onInput(Pt(r.innerHTML,i())),queueMicrotask(()=>{o=!1})},c=y=>{e.onInput(y.target.value)},v=y=>{e.capabilities.submitOnCtrlEnter&&y.key==="Enter"&&(y.ctrlKey||y.metaKey)&&(y.preventDefault(),e.onCtrlEnter?.())},T=()=>e.capabilities.toolbar==="comment",O=()=>!T()||e.capabilities.preview,I=()=>!T(),z=()=>e.capabilities.preview;return(()=>{var y=Dt();return $(y,s(A,{get when(){return O()},get children(){var d=Ot();return $(d,s(V,{get active(){return e.tab==="wysiwyg"},onClick:()=>e.onTabChange("wysiwyg"),get children(){return t("editor.write_tab")}}),null),$(d,s(A,{get when(){return I()},get children(){return s(V,{get active(){return e.tab==="source"},onClick:()=>e.onTabChange("source"),get children(){return t("editor.source_tab")}})}}),null),$(d,s(A,{get when(){return z()},get children(){return s(V,{get active(){return e.tab==="preview"},onClick:()=>e.onTabChange("preview"),get children(){return t("editor.preview_tab")}})}}),null),d}}),null),$(y,s(A,{get when(){return e.tab!=="preview"},get children(){return s(it,{get level(){return e.capabilities.toolbar},get tab(){return e.tab},editorRef:()=>r,textareaRef:()=>n,onSourceChange:d=>{e.onInput(d)}})}}),null),$(y,s(A,{get when(){return e.tab==="wysiwyg"},get children(){var d=Mt();d.$$keydown=v,d.$$input=g;var C=r;return typeof C=="function"?ee(C,d):r=d,_(k=>{var R=e.placeholder??t("editor.write_placeholder"),S=l();return R!==k.e&&H(d,"data-placeholder",k.e=R),S!==k.t&&te(d,"min-height",k.t=S),k},{e:void 0,t:void 0}),d}}),null),$(y,s(A,{get when(){return e.tab==="source"},get children(){var d=Ht();d.$$keydown=v,d.$$input=c;var C=n;return typeof C=="function"?ee(C,d):n=d,_(k=>{var R=l(),S=i()==="text/markdown"?t("editor.markdown_source_placeholder"):i()==="text/html"?t("editor.html_source_placeholder"):t("editor.bbcode_source_placeholder");return R!==k.e&&te(d,"min-height",k.e=R),S!==k.t&&H(d,"placeholder",k.t=S),k},{e:void 0,t:void 0}),_(()=>d.value=e.body),d}}),null),$(y,s(A,{get when(){return e.tab==="preview"},get children(){var d=Bt();return $(d,s(lt,{get body(){return e.body},get mimetype(){return i()}})),d}}),null),y})()}function V(e){return(()=>{var t=It();return Le(t,"click",e.onClick,!0),$(t,()=>e.children),_(()=>Oe(t,`px-3 py-1.5 text-xs font-medium transition-colors border-b-2 ${e.active?"border-accent text-accent bg-surface":"border-transparent text-muted hover:text-txt"}`)),t})()}ae(["input","keydown","click"]);const Ut={post:{toolbar:"full",preview:!0,title:!0,summary:!0,slug:!1,category:!0,attachments:"both",aclPicker:!0,submitOnCtrlEnter:!0},comment:{toolbar:"comment",preview:!0,title:!1,summary:!1,slug:!1,category:!1,attachments:"none",aclPicker:!1,submitOnCtrlEnter:!0},article:{toolbar:"full",preview:!0,title:!0,summary:!0,slug:!0,category:!0,attachments:"both",aclPicker:!0,submitOnCtrlEnter:!1},webpage:{toolbar:"full",preview:!0,title:!0,summary:!1,slug:!0,category:!1,attachments:"files",aclPicker:!1,submitOnCtrlEnter:!1},wiki:{toolbar:"minimal",preview:!0,title:!1,summary:!1,slug:!1,category:!1,attachments:"none",aclPicker:!1,submitOnCtrlEnter:!1},note:{toolbar:"minimal",preview:!0,title:!1,summary:!1,slug:!1,category:!1,attachments:"photos",aclPicker:!1,submitOnCtrlEnter:!0}};export{Ut as C,qt as R};
