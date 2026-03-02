<div class="card mb-4">
	<div class="card-header">
		<i class="bi bi-{{$icon|escape}} generic-icons-nav"></i>{{$label|escape}}
		<div class="card-tools">
			<button type="button" class="btn btn-sm btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand"
					class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button>
		</div> <!-- /.card-tools -->
	</div>
	<div class="card-body clearfix">
		<div class="row">
			{{foreach $items as $id => $item}}
			{{if $id != 'ts'}}
			<div class="col-12 col-sm-6 col-md-4">
				<div class="info-box shadow">
					<div class="info-box-content">
						<span id="perfstat-{{$id}}-label" class="info-box-text">{{$labels.$id|escape}}</span>
						<span id="perfstat-{{$id}}-value" class="info-box-number">…</span>
					</div>
					<!-- /.info-box-content -->
				</div>
				<!-- /.info-box -->
			</div>
			{{/if}}
			{{/foreach}}
		</div>
	</div>
</div>
<script>
	status_update_monitor = {
		last_ts: 0,
		last_q: 0,

		updateStatus: function () {
			fetch('/perfstats', {
				headers: {
					"Accept": "application/json",
				},
				credentials: "include",
			})
				.then((response) => response.json())
				.then((json) => {
					for (const item in json) {
						element = document.getElementById(`perfstat-${item}-value`);
						if (element) {
							if (item === "loadavg") {
								element.innerText = json['loadavg']
									.map((v) => v.toPrecision(3))
									.join(" / ");
							} else if (item === "dbqueries") {
								if (this.last_ts !== 0) {
									let dt = json['ts'] - this.last_ts;
									let dq = json['dbqueries'] - this.last_q;

									element.innerText = dq / dt;
								}

								this.last_ts = json['ts'];
								this.last_q = json['dbqueries'];
							} else if (item !== 'ts') {
								element.innerText = json[item];
							}
						}
					}
				});
		},

		start: function () {
			this.updateStatus();
			setInterval(() => this.updateStatus(), 5000);
		}
	}

	status_update_monitor.start();
</script>
