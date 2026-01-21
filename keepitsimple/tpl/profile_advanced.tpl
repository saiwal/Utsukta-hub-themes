<div id="profile-content-wrapper" class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			{{if $profile.like_count}}
			<div class="btn-group">
				<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
					id="profile-like">{{$profile.like_count}} {{$profile.like_button_label}}</button>
				{{if $profile.likers}}
				<ul class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="profile-like">{{foreach $profile.likers
					as $liker}}<li role="presentation"><a href="{{$liker.url}}"><img class="menu-img-1" src="{{$liker.photo}}"
								alt="{{$liker.name}}" /> {{$liker.name}}</a></li>{{/foreach}}</ul>
				{{/if}}
			</div>
			{{/if}}
			{{if $profile.canlike}}
			<div class="btn-group">
				<button type="button" class="btn btn-success btn-sm"
					onclick="doprofilelike('profile/' + '{{$profile.profile_guid}}','like'); return false;"
					title="{{$profile.likethis}}">
					<i class="bi fa-thumbs-o-up" title="{{$profile.likethis}}"></i>
				</button>
			</div>
			{{/if}}
			{{if $editmenu.multi}}
			<div class="btn-group">
				<a class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" href="#"><i
						class="bi bi-pencil"></i>&nbsp;{{$editmenu.edit.3}}</a>
				<div class="dropdown-menu dropdown-menu-end">
					{{foreach $editmenu.menu.entries as $e}}
					<a class="dropdown-item" href="profiles/{{$e.id}}"><img class="menu-img-1 img-thumbnail p-1 img-size-32"
							src='{{$e.photo}}'> {{$e.profile_name}}</a>
					{{/foreach}}
					{{if $editmenu.menu.cr_new}}
					<a class="dropdown-item" href="profiles/new" id="profile-listing-new-link">{{$editmenu.menu.cr_new}}</a>
					{{/if}}
				</div>
			</div>
			{{elseif $editmenu}}
			<div class="btn-group">
				<a class="btn btn-primary btn-sm" href="{{$editmenu.edit.0}}"><i
						class="bi bi-pencil"></i>&nbsp;{{$editmenu.edit.3}}</a>
			</div>
			{{/if}}
			{{** if $exportlink}}
			<div class="btn-group">
				<a class="btn btn-secondary btn-sm" href="{{$exportlink}}"><i class="bi fa-vcard"></i>&nbsp;{{$export}}</a>
			</div>
			{{/if **}}
		</div>
		<h3>{{$title}}</h3>
		<div class="clearfix"></div>
	</div>
		<ul class="list-group list-group-flush">
			{{foreach $fields as $f}}

			{{if $f == 'name'}}
			<li id="aprofile-fullname" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.fullname.0}}</div>
					<div class="col-10">{{$profile.fullname.1}}</div>
			</li>
			{{/if}}

			{{if $f == 'fullname'}}
			<li id="aprofile-fullname" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.fullname.0}}</div>
					<div class="col-10">{{$profile.fullname.1}}</div>
			</li>
			{{/if}}

			{{if $f == 'gender'}}
			{{if $profile.gender}}
			<li id="aprofile-gender" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.gender.0}}</div>
					<div class="col-10">{{$profile.gender.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'birthday'}}
			{{if $profile.birthday}}
			<li id="aprofile-birthday" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.birthday.0}}</div>
					<div class="col-10">{{$profile.birthday.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'age'}}
			{{if $profile.age}}
			<li id="aprofile-age" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.age.0}}</div>
					<div class="col-10">{{$profile.age.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'marital'}}
			{{if $profile.marital}}
			<li id="aprofile-marital" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2"><span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$profile.marital.0}}</div>
					<div class="col-10">{{$profile.marital.1}}{{if in_array('partner',$fields)}}{{if $profile.marital.partner}}
						({{$profile.marital.partner}}){{/if}}{{/if}}{{if in_array('howlong',$fields)}}{{if $profile.howlong}}
						{{$profile.howlong}}{{/if}}{{/if}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'sexual'}}
			{{if $profile.sexual}}
			<li id="aprofile-sexual" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.sexual.0}}</div>
					<div class="col-10">{{$profile.sexual.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'keywords'}}
			{{if $profile.keywords}}
			<li id="aprofile-tags" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.keywords.0}}</div>
					<div class="col-10">{{$profile.keywords.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'homepage'}}
			{{if $profile.homepage}}
			<li id="aprofile-homepage" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.homepage.0}}</div>
					<div class="col-10">{{$profile.homepage.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'hometown'}}
			{{if $profile.hometown}}
			<li id="aprofile-hometown" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.hometown.0}}</div>
					<div class="col-10">{{$profile.hometown.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'politic'}}
			{{if $profile.politic}}
			<li id="aprofile-politic" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.politic.0}}</div>
					<div class="col-10">{{$profile.politic.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'religion'}}
			{{if $profile.religion}}
			<li id="aprofile-religion" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.religion.0}}</div>
					<div class="col-10">{{$profile.religion.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'about'}}
			{{if $profile.about}}
			<li id="aprofile-about" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.about.0}}</div>
					<div class="col-10">{{$profile.about.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'interest'}}
			{{if $profile.interest}}
			<li id="aprofile-interest" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.interest.0}}</div>
					<div class="col-10">{{$profile.interest.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'likes'}}
			{{if $profile.likes}}
			<li id="aprofile-likes" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.likes.0}}</div>
					<div class="col-10">{{$profile.likes.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'dislikes'}}
			{{if $profile.dislikes}}
			<li id="aprofile-dislikes" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.dislikes.0}}</div>
					<div class="col-10">{{$profile.dislikes.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'contact'}}
			{{if $profile.contact}}
			<li id="aprofile-contact" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.contact.0}}</div>
					<div class="col-10">{{$profile.contact.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'channels'}}
			{{if $profile.channels}}
			<li id="aprofile-channels" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.channels.0}}</div>
					<div class="col-10">{{$profile.channels.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'music'}}
			{{if $profile.music}}
			<li id="aprofile-music" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.music.0}}</div>
					<div class="col-10">{{$profile.music.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'book'}}
			{{if $profile.book}}
			<li id="aprofile-book" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.book.0}}</div>
					<div class="col-10">{{$profile.book.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'tv'}}
			{{if $profile.tv}}
			<li id="aprofile-tv" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.tv.0}}</div>
					<div class="col-10">{{$profile.tv.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'film'}}
			{{if $profile.film}}
			<li id="aprofile-film" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.film.0}}</div>
					<div class="col-10">{{$profile.film.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'romance'}}
			{{if $profile.romance}}
			<li id="aprofile-romance" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.romance.0}}</div>
					<div class="col-10">{{$profile.romance.1}}</div>
			</li>
			{{/if}}
			{{/if}}


			{{if $f == 'employment'}}
			{{if $profile.employment}}
			<li id="aprofile-work" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.employment.0}}</div>
					<div class="col-10">{{$profile.employment.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{if $f == 'education'}}
			{{if $profile.education}}
			<li id="aprofile-education" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.education.0}}</div>
					<div class="col-10">{{$profile.education.1}}</div>
			</li>
			{{/if}}
			{{/if}}

			{{foreach $profile.extra_fields as $fld}}
			{{if $f == $fld}}
			{{if $profile.$fld}}
			<li id="aprofile-{{$fld}}" class="list-group-item">
				<div class="d-flex py-2 px-1">
					<div class="col-2">{{$profile.$fld.0}}</div>
					<div class="col-10">{{$profile.$fld.1}}</div>
			</li>
			{{/if}}
			{{/if}}
			{{/foreach}}
			{{/foreach}}
		</ul>

	{{if $things}}
	{{foreach $things as $key => $items}}
	<b>{{$profile.fullname.1}} {{$key}}</b>
	<ul class="profile-thing-list">
		{{foreach $items as $item}}
		<li>{{if $item.img}}<a href="{{$item.url}}"><img src="{{$item.img}}" class="profile-thing-img" width="100"
					height="100" alt="{{$item.term}}" /></a>{{/if}}
			<a href="{{$item.editurl}}">{{$item.term}}</a>
			{{if $profile.canlike}}<br />
			<button type="button" class="btn btn-secondary btn-sm"
				onclick="doprofilelike('thing/' + '{{$item.term_hash}}','like'); return false;" title="{{$likethis}}">
				<i class="bi fa-thumbs-o-up" title="{{$likethis}}"></i>
			</button>
			{{/if}}
			{{if $item.like_count}}
			<div class="btn-group">
				<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
					id="thing-like-{{$item.term_hash}}">{{$item.like_count}} {{$item.like_label}}</button>
				{{if $item.likes}}
				<ul class="dropdown-menu" role="menu" aria-labelledby="thing-like-{{$item.term_hash}}">{{foreach $item.likes as
					$liker}}<li role="presentation"><a href="{{$liker.xchan_url}}"><img class="dropdown-menu-img-xs"
								src="{{$liker.xchan_photo_s}}" alt="{{$liker.name}}" /> {{$liker.xchan_name}}</a></li>{{/foreach}}</ul>
				{{/if}}
			</div>
			{{/if}}
		</li>
		{{/foreach}}
	</ul>
	<div class="clear"></div>
	{{/foreach}}
	{{/if}}
</div>
