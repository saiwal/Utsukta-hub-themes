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
		return "<div class='mb-2 align-middle ps-1 pe-1'><img src='{1}' loading='lazy' class='img-thumbnail float-start me-2 menu-img-2 shadow img-size-50'><div class='text-nowrap'><div class='d-flex justify-content-between align-items-center lh-sm'><div class='text-truncate pe-1'><strong title='{2}'>{2}</strong></div></div><div class='text-truncate'><small class='opacity-75' title='{4}'>{4}</small></div></div></div>".format(item.taggable, item.photo, item.name, desc, typeof(item.link) !== 'undefined' ? item.link : desc.replace('(','').replace(')',''));
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
 * ModalAutocomplete - Matching Textcomplete's search behavior
 */
class ModalAutocomplete {
  constructor(editor, options = {}) {
    this.editor = editor;
    this.$modal = $('#searchModal');
    this.$modalBody = $('#search-autocomplete-results');
    this.$resultsContainer = $('<div class="autocomplete-results mt-3"></div>');
    this.$spinner = $('#nav-search-spinner');
    this.results = [];
    
    // Insert results container after the search form
    this.$modalBody.find('form').after(this.$resultsContainer);
  }

  showResults(results) {
    this.clearResults();
    this.results = results;

    if (results.length === 0) return;

    results.forEach(result => {
      const $item = $('<div class="autocomplete-item p-2 border-bottom"></div>');
      $item.html(result.template(result.data));
      $item.on('click', () => {
        this.applyResult(result);
        this.$modal.modal('hide');
      });
      this.$resultsContainer.append($item);
    });
  }
  applyResult(result) {
    // Get the current input value
    const currentValue = this.editor.el.value;
    
    if (result.strategy.name === 'contacts') {
      // Handle contact mentions
      const username = result.data.link || result.data.name;
      this.editor.el.value = '@' + username + ' ';
    } else if (result.strategy.name === 'tags') {
      // Handle tags
      const tagText = result.data.text || result.data;
      this.editor.el.value = '#' + tagText + ' ';
    } else {
      // Fallback to original behavior
      const beforeCursor = this.editor.getBeforeCursor();
      const afterCursor = this.editor.getAfterCursor();
      const replacement = result.strategy.replace(result.data);
      
      if (Array.isArray(replacement)) {
        this.editor.el.value = replacement[0] + replacement[1];
      } else {
        const match = result.strategy.matchText(beforeCursor);
        if (match) {
          const replaced = replacement.replace(/\$&/g, match[0])
                                    .replace(/\$(\d)/g, (_, p1) => match[parseInt(p1, 10)]);
          this.editor.el.value = [
            beforeCursor.slice(0, match.index),
            replaced,
            beforeCursor.slice(match.index + match[0].length)
          ].join("") + afterCursor;
        }
      }
    }
   
    // Submit the form
    $(this.editor.el.form).submit();
  }

  clearResults() {
    this.$resultsContainer.empty();
  }
}

/**
 * Updated search_autocomplete plugin with Textcomplete behavior
 */
(function($) {
  $.fn.search_autocomplete = function(backend_url) {
    if (!this.length) return;

    const Textarea = Textcomplete.editors.Textarea;

    return this.each(function() {
      const editor = new Textarea(this);
      const modalComplete = new ModalAutocomplete(editor);

      // Replicate original Textcomplete strategies
      const strategies = [
        {
          name: 'contacts',
          match: /(^@)([^\n]{2,})$/,
          index: 2,
          search: function(term, callback) {
            $('#nav-search-spinner').removeClass('d-none');
            contact_search(term, callback, backend_url, 'x', [], '#nav-search-spinner');
          },
          replace: function(item) {
            // Return the actual replacement string, not a function
            return '@' + (item.link || item.name) + ' ';
          },
          template: contact_format,
          matchText: function(text) { return text.match(/(^@)([^\n]{2,})$/); }
        },
        {
          name: 'tags',
          match: /(^\#)([^ \n]{2,})$/,
          index: 2,
          search: function(term, callback) {
            $('#nav-search-spinner').removeClass('d-none');
            $.getJSON('/hashtags/' + '$f=&t=' + term)
              .done(function(data) { 
                callback(data.map(function(entry) {
                  // Ensure we have proper text property
                  return typeof entry === 'string' ? { text: entry } : entry;
                }));
              })
              .always(function() {
                $('#nav-search-spinner').addClass('d-none');
              });
          },
          replace: function(item) {
            // Simple tag replacement
            return '#' + (item.text || item) + ' ';
          },
          template: function(item) {
            // Handle both object and string tag items
            const tagText = item.text || item;
            return "<div class='dropdown-item'>#" + tagText + "</div>";
          },
          matchText: function(text) { return text.match(/(^\#)([^ \n]{2,})$/); }
        }
      ];

      editor.on('change', (e) => {
        const text = e.detail.beforeCursor;
        if (!text) return modalComplete.clearResults();

        for (const strategy of strategies) {
          const match = text.match(strategy.match);
          if (match) {
            const term = match[strategy.index];
            strategy.search(term, (items) => {
              modalComplete.showResults(items.map(item => ({
                data: item,
                strategy: strategy,
                template: strategy.template,
                replace: strategy.replace
              })));
            }, match);
            break;
          }
        }
      });
    });
  };
})(jQuery);

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

