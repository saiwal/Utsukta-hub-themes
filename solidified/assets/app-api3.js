async function o(){const t=await fetch("/api/pubsites");if(!t.ok)throw new Error("Failed to fetch pubsites");const s=await t.json();return(s.data??s).sites??[]}export{o as f};
