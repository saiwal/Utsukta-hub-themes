import{t as e}from"./app-vendor-dompurify-CYRiAeUx.js";import{t}from"./app-bbcode-BTIiKzDl.js";import{i as n,t as r}from"./app-postCrypto-CKr1lBru.js";function i(i){let a=i.target.closest(`[data-crypt-payload]`);if(!a)return!1;i.stopPropagation();let o=a.dataset.cryptPayload??``,s=n(o),c=document.createElement(`form`);return c.className=`hz-decrypt-form flex flex-col gap-2 my-1`,c.innerHTML=`
    <span class="text-muted text-xs">🔒 ${e.sanitize(s||`Enter passphrase`)}</span>
    <div class="flex items-center gap-2">
      <input type="password" placeholder="Passphrase" autofocus
        class="hz-decrypt-input flex-1 bg-surface border border-rim rounded px-2 py-1 text-sm text-txt outline-none focus:border-rim-strong" />
      <button type="submit"
        class="px-3 py-1 rounded bg-accent text-accent-fg text-xs font-semibold hover:opacity-90 whitespace-nowrap">
        Decrypt
      </button>
      <button type="button" class="hz-decrypt-cancel px-2 py-1 rounded text-muted hover:text-txt text-xs">
        Cancel
      </button>
    </div>
    <span class="hz-decrypt-error text-xs text-red-400 hidden"></span>
  `,a.replaceWith(c),c.querySelector(`.hz-decrypt-input`)?.focus(),c.querySelector(`.hz-decrypt-cancel`)?.addEventListener(`click`,()=>{c.replaceWith(a)}),c.addEventListener(`submit`,async n=>{n.preventDefault();let i=c.querySelector(`.hz-decrypt-input`)?.value??``,a=c.querySelector(`button[type=submit]`),s=c.querySelector(`.hz-decrypt-error`);if(i){a&&(a.textContent=`Decrypting…`,a.disabled=!0);try{let n=await r(o,i),a=e.sanitize(t(n)),s=document.createElement(`div`);s.innerHTML=a,c.replaceWith(s)}catch(e){s&&(s.textContent=e instanceof Error?e.message:`Decryption failed`,s.classList.remove(`hidden`)),a&&(a.textContent=`Decrypt`,a.disabled=!1)}}}),!0}export{i as t};