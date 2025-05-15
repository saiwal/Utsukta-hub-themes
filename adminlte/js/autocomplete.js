/**
 * general autocomplete support
 *
 * require jQuery, jquery.textcomplete
 */
function contact_search(term, callback, backend_url, type, extra_channels, spinelement) {
	if(spinelement) {
		$(spinelement).show();
	}

	var postdata = {
		start:0,
		count:100,
		search:term,
		type:type,
	};

	if(typeof extra_channels !== 'undefined' && extra_channels)
		postdata['extra_channels[]'] = extra_channels;

	$.ajax({
		type:'POST',
		url: backend_url,
		data: postdata,
		dataType: 'json',
		success: function(data) {
			var items = data.items.slice(0);
			items.unshift({taggable:false, text: term, replace: term});
			callback(items);
			$(spinelement).hide();
		},
	}).fail(function () {callback([]); }); // Callback must be invoked even if something went wrong.
}

function contact_format(item) {
	// Show contact information if not explicitly told to show something else
	if(typeof item.text === 'undefined') {
		var desc = ((item.label) ? item.nick + ' ' + item.label : item.nick);
		if(typeof desc === 'undefined') desc = '';
		if(desc) desc = ' ('+desc+')';
		return "<div class='dropdown-item dropdown-notification lh-sm text-truncate' title='{4}'><img class='menu-img-2' src='{1}' loading='lazy'><strong>{2}</strong><br><small class='opacity-75'>{4}</small></div>".format(item.taggable, item.photo, item.name, desc, typeof(item.link) !== 'undefined' ? item.link : desc.replace('(','').replace(')',''));
	}
	else
		return "";
}

function smiley_format(item) {
	return "<div class='dropdown-item'><img class='emoji' src='" + item.filepath + "'> " + item.shortname.replaceAll(':', '') + "</div>";
}

function bbco_format(item) {
	return "<div class='dropdown-item'>" + item + "</div>";
}

function tag_format(item) {
	return "<div class='dropdown-item'>" + '#' + item.text + "</div>";
}

function editor_replace(item) {
	if(typeof item.replace !== 'undefined') {
		return '$1$2' + item.replace;
	}

	// $2 ensures that prefix (@,@!) is preserved

	return '$1$2{' + item.link + '} ';
}

function basic_replace(item) {
	if(typeof item.replace !== 'undefined')
		return '$1'+item.replace;

	return '$1'+item.name+' ';
}

function trim_replace(item) {
	if(typeof item.replace !== 'undefined')
		return '$1'+item.replace;

	return '$1'+item.name;
}

function getWord(text, caretPos) {
	var index = text.indexOf(caretPos);
	var postText = text.substring(caretPos, caretPos+13);
	if (postText.indexOf('[/list]') > 0 || postText.indexOf('[/checklist]') > 0 || postText.indexOf('[/ul]') > 0 || postText.indexOf('[/ol]') > 0 || postText.indexOf('[/dl]') > 0) {
		return postText;
	}
}

function getCaretPosition(ctrl) {
	var CaretPos = 0;   // IE Support
	if (document.selection) {
		ctrl.focus();
		var Sel = document.selection.createRange();
		Sel.moveStart('character', -ctrl.value.length);
		CaretPos = Sel.text.length;
	}
	// Firefox support
	else if (ctrl.selectionStart || ctrl.selectionStart == '0')
		CaretPos = ctrl.selectionStart;
	return (CaretPos);
}

function setCaretPosition(ctrl, pos){
	if(ctrl.setSelectionRange) {
		ctrl.focus();
		ctrl.setSelectionRange(pos,pos);
	}
	else if (ctrl.createTextRange) {
		var range = ctrl.createTextRange();
		range.collapse(true);
		range.moveEnd('character', pos);
		range.moveStart('character', pos);
		range.select();
	}
}

function listNewLineAutocomplete(id) {
	var text = document.getElementById(id);
	var caretPos = getCaretPosition(text)
	var word = getWord(text.value, caretPos);

	if (word != null) {
		var textBefore = text.value.substring(0, caretPos);
		var textAfter  = text.value.substring(caretPos, text.length);
		var textInsert = (word.indexOf('[/dl]') > 0) ? '\r\n[*=] ' : (word.indexOf('[/checklist]') > 0) ? '\r\n[] ' : '\r\n[*] ';
		var caretPositionDiff = (word.indexOf('[/dl]') > 0) ? 3 : 1;

		$('#' + id).val(textBefore + textInsert + textAfter);
		setCaretPosition(text, caretPos + (textInsert.length - caretPositionDiff));
		return true;
	}
	else {
		return false;
	}
}

function string2bb(element) {
	if(element == 'bold') return 'b';
	else if(element == 'italic') return 'i';
	else if(element == 'underline') return 'u';
	else if(element == 'overline') return 'o';
	else if(element == 'strike') return 's';
	else if(element == 'superscript') return 'sup';
	else if(element == 'subscript') return 'sub';
	else if(element == 'highlight') return 'hl';
	else return element;
}

/**
 * jQuery plugin 'editor_autocomplete'
 */
(function( $ ) {
	$.fn.editor_autocomplete = function(backend_url, extra_channels) {

		if(! this.length)
			return;

		if (typeof extra_channels === 'undefined') extra_channels = false;

		// Autocomplete contacts
		contacts = {
			match: /(^|\s)(@\!)([^ \n]{3,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'm', extra_channels, spinelement=false); },
			replace: editor_replace,
			template: contact_format
		};

		channels = {
			match: /(^(?=[^\!]{2})|\s)(@)([^ \n]{3,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'c', extra_channels, spinelement=false); },
			replace: editor_replace,
			template: contact_format
		};

		// Autocomplete hashtags
		tags = {
			match: /(^|\s)(\#)([^ \n]{2,})$/,
			index: 3,
			cache: true,
			search: function(term, callback) { $.getJSON('/hashtags/' + '$f=&t=' + term).done(function(data) { callback($.map(data, function(entry) { return entry.text.toLowerCase().indexOf(term.toLowerCase()) === 0 ? entry : null; })); }); },
			replace: function(item) { return "$1$2" + item.text + ' '; },
			context: function(text) { return text.toLowerCase(); },
			template: tag_format
		};

		smilies = {
			match: /(^|\s)(:[a-z0-9_:]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { $.getJSON('/smilies/json').done(function(data) { callback($.map(data, function(entry) { return entry.shortname.indexOf(term.substr(1)) !== -1 ? entry : null; })); }); },
			replace: function(item) { return "$1" + item.shortname + ' '; },
			context: function(text) { return text.toLowerCase(); },
			template: smiley_format
		};
		//this.attr('autocomplete','off');

		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			var textcomplete = new Textcomplete(editor, {
				dropdown: {
					maxCount: 100
				}
			});
			// it seems important that contacts are before channels here. Otherwise we run into regex issues.
			textcomplete.register([contacts,channels,smilies,tags]);
		});
	};
})( jQuery );

/**
 * jQuery plugin 'modal_search_autocomplete'
 */
(function( $ ) {
  $.fn.modal_search_autocomplete = function(backend_url, modal_id) {
    if(! this.length) return;

    // Store references to modal elements
    var $modal = $(modal_id);
    var $modalBody = $modal.find('.modal-body');
    var $input = $(this);
    
    // Clear previous results when starting new search
    $input.on('input', function() {
      $modalBody.empty();
      $modal.modal('hide');
    });

    // Function to handle search results
    function showResults(results) {
      $modalBody.empty();
      
      if (results.length) {
        results.forEach(function(result) {
          // Use the existing format functions
          var html;
          if (result.data.taggable !== undefined) {
            html = contact_format(result.data);
          } else {
            html = tag_format(result.data);
          }
          $modalBody.append(html);
        });
        $modal.modal('show');
      } else {
        $modal.modal('hide');
      }
    }

    // Function to perform search
    function performSearch(term) {
      // Check if it's a contact search (@) or tag search (#)
      if (term.startsWith('@')) {
        contact_search(
          term.substring(1), 
          function(results) { showResults(results); },
          backend_url, 
          'x', 
          [], 
          '#nav-search-spinner'
        );
      } else if (term.startsWith('#')) {
        $.getJSON('/hashtags/' + '$f=&t=' + term.substring(1))
          .done(function(data) { 
            var filtered = $.map(data, function(entry) { 
              return entry.text.toLowerCase().indexOf(term.substring(1).toLowerCase()) === 0 ? entry : null; 
            });
            showResults(filtered.map(function(item) {
              return { data: item };
            }));
          });
      }
    }

    // Handle keyup events to trigger search
    $input.on('keyup', function(e) {
      var val = $(this).val();
      
      // Only trigger search when we have @ or # with at least 2 characters
      if ((val.startsWith('@') || val.startsWith('#')) && val.length >= 3) {
        performSearch(val);
      } else {
        $modal.modal('hide');
      }
    });

    // Handle item selection
    $modalBody.on('click', '.dropdown-item', function() {
      var text = $(this).find('strong').text() || $(this).text();
      var currentVal = $input.val();
      var prefix = currentVal.startsWith('@') ? '@' : '#';
      
      // Update input value
      $input.val(prefix + text.trim() + ' ');
      $modal.modal('hide');
      
      // Submit form if needed
      $input.closest('form').submit();
    });
  };
})( jQuery );
/**
 * jQuery plugin 'search_autocomplete'
 */
(function( $ ) {
	$.fn.search_autocomplete = function(backend_url) {

		if(! this.length)
			return;

		// Autocomplete contacts
		contacts = {
			match: /(^@)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, 'x', [], spinelement='#nav-search-spinner'); },
			replace: basic_replace,
			template: contact_format,
		};

		// Autocomplete hashtags
		tags = {
			match: /(^\#)([^ \n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { $.getJSON('/hashtags/' + '$f=&t=' + term).done(function(data) { callback($.map(data, function(entry) { return entry.text.toLowerCase().indexOf(term.toLowerCase()) === 0 ? entry : null; })); }); },
			replace: function(item) { return "$1" + item.text + ' '; },
			context: function(text) { return text.toLowerCase(); },
			template: tag_format
		};

		//this.attr('autocomplete', 'off');

		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor, {
				dropdown: {
					maxCount: 100
				}
			});
			textcomplete.register([contacts,tags]);
		});

		textcomplete.on('selected', function() { this.editor.el.form.submit(); });

	};
})( jQuery );

(function( $ ) {
	$.fn.contact_autocomplete = function(backend_url, typ, autosubmit, onselect) {

		if(! this.length)
			return;

		if(typeof typ === 'undefined') typ = '';
		if(typeof autosubmit === 'undefined') autosubmit = false;

		// Autocomplete contacts
		contacts = {
			match: /(^)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, typ,[], spinelement=false); },
			replace: basic_replace,
			template: contact_format,
		};

		//this.attr('autocomplete','off');

		var textcomplete;
		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			textcomplete = new Textcomplete(editor, {
				dropdown: {
					maxCount: 100

				}
			});
			textcomplete.register([contacts]);
		});

		if(autosubmit)
			textcomplete.on('selected', function() { this.editor.el.form.submit(); });

		if(typeof onselect !== 'undefined')
			textcomplete.on('select', function() { var item = this.dropdown.getActiveItem(); onselect(item.searchResult.data);});

	};
})( jQuery );


(function( $ ) {
	$.fn.name_autocomplete = function(backend_url, typ, autosubmit, onselect) {

		if(! this.length)
			return;

		if(typeof typ === 'undefined') typ = '';
		if(typeof autosubmit === 'undefined') autosubmit = false;

		// Autocomplete contacts
		names = {
			match: /(^)([^\n]{2,})$/,
			index: 2,
			cache: true,
			search: function(term, callback) { contact_search(term, callback, backend_url, typ,[], spinelement=false); },
			replace: trim_replace,
			template: contact_format,
		};

		//this.attr('autocomplete','off');

		let textcomplete;
		let Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			let editor = new Textarea(this);
			textcomplete = new Textcomplete(editor, {
				dropdown: {
					maxCount: 100
				}
			});
			textcomplete.register([names]);
		});


		if(autosubmit)
			textcomplete.on('selected', function() { this.editor.el.form.submit(); });

		if(typeof onselect !== 'undefined')
			textcomplete.on('select', function() { let item = this.dropdown.getActiveItem(); onselect(item.searchResult.data); });

	};
})( jQuery );

(function( $ ) {
	$.fn.bbco_autocomplete = function(type) {

		if(! this.length)
			return;

		if(type=='bbcode') {
			var open_close_elements = ['bold', 'italic', 'underline', 'overline', 'strike', 'superscript', 'subscript', 'quote', 'code', 'open', 'spoiler', 'map', 'nobb', 'list', 'checklist', 'question', 'answer', 'ul', 'ol', 'dl', 'li', 'table', 'tr', 'th', 'td', 'center', 'color', 'font', 'size', 'zrl', 'zmg', 'rpost', 'qr', 'observer', 'observer.language','embed', 'mark', 'url', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
			var open_elements = ['observer.baseurl', 'observer.address', 'observer.photo', 'observer.name', 'observer.webname', 'observer.url', '*', 'hr' ];

			var elements = open_close_elements.concat(open_elements);
		}

		if(type=='comanche') {
			var open_close_elements = ['region', 'layout', 'template', 'theme', 'widget', 'block', 'menu', 'var', 'css', 'js', 'authored', 'comment', 'webpage'];
			var open_elements = [];

			var elements = open_close_elements.concat(open_elements);
		}

		if(type=='comanche-block') {
			var open_close_elements = ['menu', 'var'];
			var open_elements = [];

			var elements = open_close_elements.concat(open_elements);
		}

		bbco = {
			match: /\[(\w*\**)$/,
			search: function (term, callback) {
				callback($.map(elements, function (element) {
					return element.indexOf(term) === 0 ? element : null;
				}));
			},
			index: 1,
			replace: function (element) {
				element = string2bb(element);
				if(open_elements.indexOf(element) < 0) {
					if(element === 'list' || element === 'ol' || element === 'ul') {
						return ['\[' + element + '\]' + '\n\[*\] ', '\n\[/' + element + '\]'];
					} else if(element === 'checklist') {
						return ['\[' + element + '\]' + '\n\[\] ', '\n\[/' + element + '\]'];
					} else if (element === 'dl') {
						return ['\[' + element + '\]' + '\n\[*=Item name\] ', '\n\[/' + element + '\]'];
					} else if(element === 'table') {
						return ['\[' + element + '\]' + '\n\[tr\]', '\[/tr\]\n\[/' + element + '\]'];
					} else if(element === 'observer') {
						return ['\[' + element + '=1\]', '\[/observer\]'];
					} else if(element === 'observer.language') {
						return ['\[' + element + '=en\]', '\[/observer\]'];
					}
					else {
						return ['\[' + element + '\]', '\[/' + element + '\]'];
					}
				}
				else {
					return '\[' + element + '\] ';
				}
			},
			template: bbco_format
		};

		//this.attr('autocomplete','off');

		var Textarea = Textcomplete.editors.Textarea;

		$(this).each(function() {
			var editor = new Textarea(this);
			var textcomplete = new Textcomplete(editor);
			textcomplete.register([bbco]);
		});

		this.keypress(function(e){
			if (e.keyCode == 13) {
				var x = listNewLineAutocomplete(this.id);
				if(x) {
					e.stopImmediatePropagation();
					e.preventDefault();
				}
			}
		});
	};
})( jQuery );

