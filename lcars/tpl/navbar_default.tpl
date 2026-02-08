<div class="wrap">
	<div class="left-frame-top">
		{{if $navbar_apps.0}}
		<button onclick="playSound('audio2', '#')" class="panel-1-button" data-bs-toggle="collapse"
			data-bs-target="#collapsepinnedapps" aria-controls="collapsepinnedapps">
			<span class="hop">{{$pinned_apps}}</span>
			<i class="bi bi-pin-angle-fill ps-1"></i>
		</button>
		{{/if}}

		{{if $channel_apps.0}}
		<button onclick="playSound('audio2', '#')" class="panel-1-button" data-bs-toggle="collapse"
			data-bs-target="#collapsechannelapps" aria-controls="collapsechannelapps">
			<span class="hop">{{$channelapps}}</span>
			<i class="bi bi-grid-3x3-gap-fill ps-1"></i>
		</button>
		{{/if}}

		{{if $is_owner}}
		<div onclick="playSound('audio2', '#')" class="panel-3" type="button" data-bs-toggle="collapse"
			data-bs-target="#collapsefeatapps" aria-controls="collapsefeatapps">
			<span class="hop">
				{{$featured_apps}}
			</span><i class="bi bi-star-fill ps-1"></i>
		</div>
		<div onclick="playSoundAndRedirect('audio2', '/apps')" class="panel-2" >
			<span class="hop">
				{{$addapps}}
			</span><i class="bi bi-plus-lg ps-1"></i>
		</div>
		{{else}}
		<div onclick="playSound('audio2')" class="panel-2" type="button" data-bs-toggle="collapse"
			data-bs-target="#collapsesysapps" aria-controls="collapsesysapps">
			<span class="hop">{{$sysapps}}</span>
			<i class="bi bi-tools ps-1"></i>
		</div>
		{{/if}}
	</div>
	<div class="right-frame-top">
		<div class="banner"><a href="/">{{$banner}}</a>
			{{if $userinfo}}
			{{if $sel.name}}
			{{if $sitelocation}}
			• {{$sel.name}}<br>
			<span class="go-big">{{$sitelocation}}</span>
			{{else}}
			• <a href="{{$url}}">{{$sel.name}}</a>
			{{if $settings_url}}
			<a href="{{$settings_url}}/?f=&rpath={{$url}}" class="go-big"><i class="bi bi-gear"></i></a>
			{{/if}}
			{{/if}}
			{{/if}}
			{{/if}}
		</div>
		<div class="data-cascade-button-group">
			<div class="data-cascade-wrapper" id="default">
				<div class="data-column">
					<div class="dc-row-1">93</div>
					<div class="dc-row-1">1853</div>
					<div class="dc-row-2">24109</div>
					<div class="dc-row-3">7</div>
					<div class="dc-row-3">7024</div>
					<div class="dc-row-4">322</div>
					<div class="dc-row-5">4149</div>
					<div class="dc-row-6">86</div>
					<div class="dc-row-7">05</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">21509</div>
					<div class="dc-row-1">68417</div>
					<div class="dc-row-2">80</div>
					<div class="dc-row-3">2048</div>
					<div class="dc-row-3">319825</div>
					<div class="dc-row-4">46233</div>
					<div class="dc-row-5">05</div>
					<div class="dc-row-6">2014</div>
					<div class="dc-row-7">30986</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">585101</div>
					<div class="dc-row-1">25403</div>
					<div class="dc-row-2">31219</div>
					<div class="dc-row-3">752</div>
					<div class="dc-row-3">0604</div>
					<div class="dc-row-4">21048</div>
					<div class="dc-row-5">293612</div>
					<div class="dc-row-6">534082</div>
					<div class="dc-row-7">206</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">2107853</div>
					<div class="dc-row-1">12201972</div>
					<div class="dc-row-2">24487255</div>
					<div class="dc-row-3">30412</div>
					<div class="dc-row-3">98</div>
					<div class="dc-row-4">4024161</div>
					<div class="dc-row-5">888</div>
					<div class="dc-row-6">35045462</div>
					<div class="dc-row-7">41520257</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">33</div>
					<div class="dc-row-1">56</div>
					<div class="dc-row-2">04</div>
					<div class="dc-row-3">69</div>
					<div class="dc-row-3">41</div>
					<div class="dc-row-4">15</div>
					<div class="dc-row-5">25</div>
					<div class="dc-row-6">65</div>
					<div class="dc-row-7">21</div>
				</div>

				<div class="data-column">
					<div class="dc-row-1">0223</div>
					<div class="dc-row-1">688</div>
					<div class="dc-row-2">28471</div>
					<div class="dc-row-3">21366</div>
					<div class="dc-row-3">8654</div>
					<div class="dc-row-4">31</div>
					<div class="dc-row-5">1984</div>
					<div class="dc-row-6">272</div>
					<div class="dc-row-7">21854</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">633</div>
					<div class="dc-row-1">51166</div>
					<div class="dc-row-2">41699</div>
					<div class="dc-row-3">6188</div>
					<div class="dc-row-3">15033</div>
					<div class="dc-row-4">21094</div>
					<div class="dc-row-5">32881</div>
					<div class="dc-row-6">26083</div>
					<div class="dc-row-7">2143</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">406822</div>
					<div class="dc-row-1">81205</div>
					<div class="dc-row-2">91007</div>
					<div class="dc-row-3">38357</div>
					<div class="dc-row-3">110</div>
					<div class="dc-row-4">2041</div>
					<div class="dc-row-5">312</div>
					<div class="dc-row-6">57104</div>
					<div class="dc-row-7">00708</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">12073</div>
					<div class="dc-row-1">688</div>
					<div class="dc-row-2">21982</div>
					<div class="dc-row-3">20254</div>
					<div class="dc-row-3">55</div>
					<div class="dc-row-4">38447</div>
					<div class="dc-row-5">26921</div>
					<div class="dc-row-6">285</div>
					<div class="dc-row-7">30102</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">21604</div>
					<div class="dc-row-1">15421</div>
					<div class="dc-row-2">25</div>
					<div class="dc-row-3">3808</div>
					<div class="dc-row-3">582031</div>
					<div class="dc-row-4">62311</div>
					<div class="dc-row-5">85799</div>
					<div class="dc-row-6">87</div>
					<div class="dc-row-7">6895</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">72112</div>
					<div class="dc-row-1">101088</div>
					<div class="dc-row-2">604122</div>
					<div class="dc-row-3">126523</div>
					<div class="dc-row-3">86801</div>
					<div class="dc-row-4">8447</div>
					<div class="dc-row-5">210486</div>
					<div class="dc-row-6">LV426</div>
					<div class="dc-row-7">220655</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">272448</div>
					<div class="dc-row-1">29620</div>
					<div class="dc-row-2">339048</div>
					<div class="dc-row-3">31802</div>
					<div class="dc-row-3">9859</div>
					<div class="dc-row-4">672304</div>
					<div class="dc-row-5">581131</div>
					<div class="dc-row-6">338</div>
					<div class="dc-row-7">70104</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">16182</div>
					<div class="dc-row-1">711632</div>
					<div class="dc-row-2">102955</div>
					<div class="dc-row-3">2061</div>
					<div class="dc-row-3">5804</div>
					<div class="dc-row-4">850233</div>
					<div class="dc-row-5">833441</div>
					<div class="dc-row-6">465</div>
					<div class="dc-row-7">210047</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">75222</div>
					<div class="dc-row-1">98824</div>
					<div class="dc-row-2">63</div>
					<div class="dc-row-3">858552</div>
					<div class="dc-row-3">696730</div>
					<div class="dc-row-4">307124</div>
					<div class="dc-row-5">58414</div>
					<div class="dc-row-6">209</div>
					<div class="dc-row-7">808044</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">331025</div>
					<div class="dc-row-1">62118</div>
					<div class="dc-row-2">2700</div>
					<div class="dc-row-3">395852</div>
					<div class="dc-row-3">604206</div>
					<div class="dc-row-4">26</div>
					<div class="dc-row-5">309150</div>
					<div class="dc-row-6">885</div>
					<div class="dc-row-7">210411</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">817660</div>
					<div class="dc-row-1">121979</div>
					<div class="dc-row-2">20019</div>
					<div class="dc-row-3">462869</div>
					<div class="dc-row-3">25002</div>
					<div class="dc-row-4">308</div>
					<div class="dc-row-5">52074</div>
					<div class="dc-row-6">33</div>
					<div class="dc-row-7">80544</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">1070</div>
					<div class="dc-row-1">020478</div>
					<div class="dc-row-2">26419</div>
					<div class="dc-row-3">372122</div>
					<div class="dc-row-3">2623</div>
					<div class="dc-row-4">79</div>
					<div class="dc-row-5">90008</div>
					<div class="dc-row-6">8049</div>
					<div class="dc-row-7">251664</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">900007</div>
					<div class="dc-row-1">704044</div>
					<div class="dc-row-2">982365</div>
					<div class="dc-row-3">25819</div>
					<div class="dc-row-3">385</div>
					<div class="dc-row-4">656214</div>
					<div class="dc-row-5">409</div>
					<div class="dc-row-6">218563</div>
					<div class="dc-row-7">527222</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">80106</div>
					<div class="dc-row-1">1314577</div>
					<div class="dc-row-2">39001</div>
					<div class="dc-row-3">7162893</div>
					<div class="dc-row-3">12855</div>
					<div class="dc-row-4">57</div>
					<div class="dc-row-5">23966</div>
					<div class="dc-row-6">4</div>
					<div class="dc-row-7">6244009</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">2352</div>
					<div class="dc-row-1">308</div>
					<div class="dc-row-2">928</div>
					<div class="dc-row-3">2721</div>
					<div class="dc-row-3">8890</div>
					<div class="dc-row-4">402</div>
					<div class="dc-row-5">540</div>
					<div class="dc-row-6">795</div>
					<div class="dc-row-7">23</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">66880</div>
					<div class="dc-row-1">8675309</div>
					<div class="dc-row-2">821533</div>
					<div class="dc-row-3">249009</div>
					<div class="dc-row-3">51922</div>
					<div class="dc-row-4">600454</div>
					<div class="dc-row-5">9035768</div>
					<div class="dc-row-6">453571</div>
					<div class="dc-row-7">825064</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">131488</div>
					<div class="dc-row-1">641212</div>
					<div class="dc-row-2">218035</div>
					<div class="dc-row-3">37</div>
					<div class="dc-row-3">6022</div>
					<div class="dc-row-4">82</div>
					<div class="dc-row-5">572104</div>
					<div class="dc-row-6">799324</div>
					<div class="dc-row-7">4404</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">8807</div>
					<div class="dc-row-1">4481</div>
					<div class="dc-row-2">8915</div>
					<div class="dc-row-3">2104</div>
					<div class="dc-row-3">1681</div>
					<div class="dc-row-4">326</div>
					<div class="dc-row-5">446</div>
					<div class="dc-row-6">8337</div>
					<div class="dc-row-7">526</div>
				</div>
				<div class="data-column">
					<div class="dc-row-1">593</div>
					<div class="dc-row-1">8057</div>
					<div class="dc-row-2">22</div>
					<div class="dc-row-3">23</div>
					<div class="dc-row-3">6722</div>
					<div class="dc-row-4">890</div>
					<div class="dc-row-5">2608</div>
					<div class="dc-row-6">7274</div>
					<div class="dc-row-7">2103</div>
				</div>
			</div> <!-- /data-cascade-wrapper -->
			<div id="appsAccordion">
				{{if $navbar_apps.0}}
				<nav class="collapse" id="collapsepinnedapps" data-bs-parent="#appsAccordion">
					{{foreach $navbar_apps as $navbar_app}}
					{{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
					{{/foreach}}
				</nav>
				{{/if}}

				{{if $channel_apps.0}}
				<nav class="collapse" id="collapsechannelapps" data-bs-parent="#appsAccordion">
					{{foreach $channel_apps as $channel_app}}
					{{$channel_app}}
					{{/foreach}}
				</nav>
				{{/if}}

				{{if $is_owner}}
				<nav class="collapse" id="collapsefeatapps" data-bs-parent="#appsAccordion">
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</nav>
				{{else}}
				<nav class="collapse" id="collapsesysapps" data-bs-parent="#appsAccordion">
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</nav>
				{{/if}}
			</div>
		</div>
		<div class="bar-panel first-bar-panel">
			<div class="bar-1"></div>
			<div class="bar-2"></div>
			<div class="bar-3"></div>
			<div class="bar-4"></div>
			<div class="bar-5"></div>
		</div>
	</div>
</div>
<div class="wrap" id="gap">
	<div class="left-frame">
		<button onclick="topFunction(); playSound('audio4')" id="topBtn"><span class="hop">screen</span>
			top</button>
		<div>
			<div class="panel-3 d-lg-none"><a class="nav-link " type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive" aria-controls="offcanvasResponsive"><span class="hop">SIDEBAR</span><i class="bi bi-layout-text-sidebar ps-1"></i></a></div>
			{{if $userinfo}}
			{{if $is_owner}}
			<div onclick="playSound('audio1')" class="panel-3" role="button" data-bs-toggle="collapse" data-bs-target="#userMenuCollapse" aria-expanded="false" aria-controls="userMenuCollapse"><span class="hop">{{$userinfo.name}}</span><i class="bi bi-person-fill-gear ps-1"></i>
			</div>
			<div class="collapse" id="userMenuCollapse">
				{{if $is_owner}}
				{{foreach $nav.usermenu as $usermenu}}
				<a href="{{$usermenu.0}}" class="panel-5">
					{{$usermenu.1}}
				</a>
				{{/foreach}}
				{{if $nav.group}}
				<a href="{{$nav.group.0}}" class="panel-5">{{$nav.group.1}}</a>
				{{/if}}

				{{if $nav.manage}}
				<a href="{{$nav.manage.0}}" class="panel-5">{{$nav.manage.1}}</a>
				{{/if}}

				{{if $nav.channels}}
				{{foreach $nav.channels as $chan}}
				<a href="manage/{{$chan.channel_id}}" class="panel-5">
					{{$chan.channel_name}}<i class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}} ps-2 "></i>
				</a>
				{{/foreach}}
				{{/if}}

				{{if $nav.settings}}
				<a href="{{$nav.settings.0}}" class="panel-5">{{$nav.settings.1}}</a>
				{{if $nav.admin}}
				<a href="{{$nav.admin.0}}" class="panel-5">{{$nav.admin.1}}</a>
				{{/if}}
				{{/if}}

				{{if $nav.logout}}
				<a onclick="playSoundAndRedirect('audio4', '{{$nav.logout.0}}')" class="panel-4">{{$nav.logout.1}}</a>
				{{/if}}
				{{/if}}
			</div>
			{{/if}}
			{{if ! $is_owner}}
			<div role="button" class="panel-button panel-3" onclick="playSoundAndRedirect('audio2', '{{$nav.rusermenu.0}}')">
				{{$nav.rusermenu.1}}</div>
			<button class="panel-button panel-5"
				onclick="playSoundAndRedirect('audio2','{{$nav.rusermenu.2}}')">{{$nav.rusermenu.3}}</button>
			{{/if}}
			{{/if}}
			{{if $nav.login && !$userinfo}}
			{{if $nav.loginmenu.1.4}}
			<div role="button" class="panel-butto panel-3" onclick="playSound('audio2')" title="{{$nav.loginmenu.1.3}}"
				data-bs-toggle="modal" data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</div>
			{{else}}
			<div role="button" class="panel-button panel-3" onclick="playSoundAndRedirect('audio3','login')"
				title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</div>
			{{/if}}
			{{if $nav.register}}
			<button class="panel-button panel-5" onclick="playSoundAndRedirect('audio4','{{$nav.register.0}}')"
				title="{{$nav.register.3}}">{{$nav.register.1}}</button>
			{{/if}}
			{{/if}}
		</div>
		<div>
			<button onclick="playSoundAndRedirect('audio3','/siteinfo')" class="panel-button panel-10"><span
					class="hop">SITEINFO</span><i class="bi bi-info-circle-fill ps-1"></i></button>
		</div>
	</div>
	<div class="right-frame">
		<div class="bar-panel">
			<div class="bar-6"></div>
			<div class="bar-7"></div>
			<div class="bar-8"></div>
			<div class="bar-9"></div>
			<div class="bar-10"></div>
		</div>
