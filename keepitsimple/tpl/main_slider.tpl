<div id="main-slider" class="slider mb-5" >
	<input id="main-range" type="text" name="cminmax" value="{{$val}}" />
</div>
<script>
$(document).ready(function() {
	let old_cmin = 0;
	let old_cmax = 99;

	$("#main-range").jRange({
		isRange: true,
		from: 0,
		to: 99,
		step: 1,
		scale: [{{$labels}}],
		width:'100%',
		showLabels: false,
		onstatechange: function(v) {
			let carr = v.split(",");

			if(carr[0] != bParam_cmin) {
				old_cmin = bParam_cmin;
				bParam_cmin = carr[0];
			}

			if(carr[1] != bParam_cmax) {
				old_cmax = bParam_cmax;
				bParam_cmax = carr[1];
			}

		},
		onbarclicked: function() {
			affinity_network_refresh();
		},
		ondragend: function() {
			affinity_network_refresh();
		}
	});

	function affinity_network_refresh() {
		page_load = true;
		next_page = 1;
		liveUpdate();
	}
});
</script>
