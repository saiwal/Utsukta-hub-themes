<div id="side-bar-photos-albums" class="mb-3">
	<div class="h5">{{$title}}</div>
	<ul class="flex-column" style="list-style: none;">
		<li class=""><a class="" href="{{$baseurl}}/photos/{{$nick}}" title="{{$title}}" >{{$recent}}</a></li>
		{{if $albums}}
		{{foreach $albums as $al}}
		{{if $al.shorttext}}
		<li class="m-0"><a class="" href="{{$baseurl}}/photos/{{$nick}}/album/{{$al.bin2hex}}"><span class="badge bg--primary float-end">{{$al.total}}</span>{{$al.shorttext}}</a></li>
		{{/if}}
		{{/foreach}}
		{{/if}}
	</ul>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const list = document.querySelector("#side-bar-photos-albums ul");
    if (!list) return;

    // Skip the first entry: "Recent Photos"
    const items = Array.from(list.querySelectorAll("li")).slice(1);

    function getAlbumName(li) {
        const a = li.querySelector("a");
        // album name = last text node inside <a> (after the badge)
        return a.childNodes[a.childNodes.length - 1].textContent.trim();
    }

    items.sort((a, b) => {
        const A = getAlbumName(a);
        const B = getAlbumName(b);

        const AstartsNum = /^[0-9]/.test(A);
        const BstartsNum = /^[0-9]/.test(B);

        // Alphabetical group first, numeric group last
        if (!AstartsNum && BstartsNum) return -1;
        if (AstartsNum && !BstartsNum) return 1;

        // Within each group: normal alphabetical sort
        return A.localeCompare(B, undefined, { sensitivity: "base" });
    });

    items.forEach(li => list.appendChild(li));
});
</script>
