import{q as n,s as c,t as m}from"./app.js";var g=m('<div class="border border-rim rounded-lg overflow-hidden"><div class="px-3 py-1.5 text-xs text-muted bg-elevated border-b border-rim font-medium">Preview</div><iframe sandbox=allow-same-origin class="w-full min-h-[120px] border-0 bg-white">');function f(e){const a=()=>e.mimetype==="text/html"?e.body:e.body.replace(/\[b\]([\s\S]*?)\[\/b\]/gi,"<strong>$1</strong>").replace(/\[i\]([\s\S]*?)\[\/i\]/gi,"<em>$1</em>").replace(/\[u\]([\s\S]*?)\[\/u\]/gi,"<u>$1</u>").replace(/\[url=(.*?)\]([\s\S]*?)\[\/url\]/gi,'<a href="$1">$2</a>').replace(/\[img\]([\s\S]*?)\[\/img\]/gi,'<img src="$1" style="max-width:100%" />').replace(/\n/g,"<br />"),l=()=>`
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8" />
      <style>
        body { font-family: system-ui, sans-serif; font-size: 14px;
               padding: 12px; margin: 0; line-height: 1.6; color: #1a1a1a; }
        img { max-width: 100%; }
        pre { background: #f0f0f0; padding: 8px; border-radius: 4px; overflow-x:auto; }
        blockquote { border-left: 3px solid #ccc; margin: 0; padding-left: 12px; color: #555; }
        a { color: #2563eb; }
      </style>
    </head>
    <body>${a()}</body>
    </html>
  `;return(()=>{var r=g(),d=r.firstChild,t=d.nextSibling;return t.addEventListener("load",s=>{const i=s.currentTarget;try{const o=i.contentDocument?.body?.scrollHeight;o&&(i.style.height=`${o+24}px`)}catch{}}),n(()=>c(t,"srcdoc",l())),r})()}export{f as E};
