// include: shell.js
"use strict";

// The Module object: Our interface to the outside world. We import
// and export values on it. There are various ways Module can be used:
// 1. Not defined. We create it here
// 2. A function parameter, function(moduleArg) => Promise<Module>
// 3. pre-run appended it, var Module = {}; ..generated code..
// 4. External script tag defines var Module.
// We need to check if Module already exists (e.g. case 3 above).
// Substitution will be replaced with actual code on later stage of the build,
// this way Closure Compiler will not mangle it (e.g. case 4. above).
// Note that if you want to run closure, and also to use Module
// after the generated code, you will need to define   var Module = {};
// before the code. Then that object will be used in the code, and you
// can continue to use Module afterwards as well.
var Module = typeof Module != 'undefined' ? Module : {};

// Determine the runtime environment we are in. You can customize this by
// setting the ENVIRONMENT setting at compile time (see settings.js).

var ENVIRONMENT_IS_WEB = true;
var ENVIRONMENT_IS_WORKER = false;
var ENVIRONMENT_IS_NODE = false;
var ENVIRONMENT_IS_SHELL = false;
if (Module['ENVIRONMENT']) {
  throw new Error('Module.ENVIRONMENT has been deprecated. To force the environment, use the ENVIRONMENT compile-time option (for example, -sENVIRONMENT=web or -sENVIRONMENT=node)');
}

// --pre-jses are emitted after the Module integration code, so that they can
// refer to Module (if they choose; they can also define Module)
// include: /tmp/bob.1.0/puzzles/emccpre.js
/*
 * emccpre.js: one of the Javascript components of an Emscripten-based
 * web/Javascript front end for Puzzles.
 *
 * The other parts of this system live in emcc.c and emcclib.js. It
 * also depends on being run in the context of a web page containing
 * an appropriate collection of bits and pieces (a canvas, some
 * buttons and links etc), which is generated for each puzzle by the
 * script html/jspage.pl.
 *
 * This file contains the Javascript code which is prefixed unmodified
 * to Emscripten's output via the --pre-js option. It declares all our
 * global variables, and provides the puzzle init function and a
 * couple of other helper functions.
 */

// Because this script is run using <script defer>, we can guarantee
// that the DOM is complete, so it's OK to look up elements
// immediately.  On the other hand, the Emscripten runtime hasn't
// started yet, so Module.cwrap isn't safe.

// Error handler to make any failures from here on visible to the
// user, maybe.
window.addEventListener("error", function (e) {
  alert(e.message);
});

// To avoid flicker while doing complicated drawing, we use two
// canvases, the same size. One is actually on the web page, and the
// other is off-screen. We do all our drawing on the off-screen one
// first, and then copy rectangles of it to the on-screen canvas in
// response to draw_update() calls by the game backend.
var onscreen_canvas, offscreen_canvas;

// A persistent drawing context for the offscreen canvas, to save
// requesting it for each individual graphics operation.
var ctx;

// Bounding rectangle for the copy to the onscreen canvas that will be
// done at drawing end time. Updated by js_canvas_draw_update and used
// by js_canvas_end_draw.
var update_xmin, update_xmax, update_ymin, update_ymax;

// Colour strings to use when drawing, to save converting them from C
// every time.
var colours = [];

// Module object for Emscripten. We fill in these parameters to ensure
// that when main() returns nothing will get cleaned up so we remain
// able to call the puzzle's various callbacks.
//
// Page loading order:
//
// 1. The browser starts reading *.html (which comes from jspage.pl)
// 2. It finds the <script> tag.  This is marked defer, so the
//    browser will start fetching and parsing it, but not execute it
//    until the page has loaded.
//
// Now the browser is loading *.html and *.js in parallel.  The
// html is rendered as we go, and the js is deferred.
//
// 3. The HTML finishes loading.  The browser is about to fire the
//    `DOMContentLoaded` event (ie `onload`) but before that, it
//    actually runs the deferred JS.  This consists of
//
//    (i) emccpre.js (this file).  This sets up various JS variables
//      including the emscripten Module object, which includes the
//      environment variables and argv seen by main().
//
//    (ii) emscripten's JS.  This starts the WASM loading.
//
// When this JS execution is complete, the browser fires the `onload`
// event.  This is ignored.  It continues loading the WASM.
//
// 4. The WASM loading and initialisation completes.  Emscripten's
//    runtime calls the C `main` to actually start the puzzle.  It
//    then calls initPuzzle, which:
//
//      (a) finds various DOM elements and bind them to variables,
//      which depends on the HTML having loaded (it has).
//
//      (b) makes various `cwrap` calls into the emscripten module to
//      set up hooks; this depends on the emscripten JS having been
//      loaded (it has).

var Module = {
  'preRun': function () {
    // Merge environment variables from HTML script elements.
    // This means you can add something like this to the HTML:
    // <script id="environment" type="application/json">
    //   { "LOOPY_DEFAULT": "20x10t11dh" }
    // </script>
    var envscript, k, v;
    for (envscript of document.querySelectorAll("script#environment, script.environment")) for ([k, v] of Object.entries(JSON.parse(envscript.textContent))) ENV[k] = v;
  },
  // Pass argv[1] as the fragment identifier (so that permalinks of
  // the form puzzle.html#game-id can launch the specified id).
  'arguments': [decodeURIComponent(location.hash)],
  'noExitRuntime': true
};

// Variables used by js_canvas_find_font_midpoint().
var midpoint_test_str = "ABCDEFGHIKLMNOPRSTUVWXYZ0123456789";
var midpoint_cache = [];

// Variables used by js_activate_timer() and js_deactivate_timer().
var timer;
var timer_reference;

// void timer_callback(double tplus);
//
// Called every frame while timing is active.
var timer_callback;

// The status bar object, if we have one.
var statusbar = document.getElementById("statusbar");

// Currently live blitters. We keep an integer id for each one on the
// JS side; the C side, which expects a blitter to look like a struct,
// simply defines the struct to contain that integer id.
var blittercount = 0;
var blitters = new Map();

// State for the dialog-box mechanism. dlg_dimmer and dlg_form are the
// page-darkening overlay and the actual dialog box respectively;
// dlg_return_funcs is a list of JS functions to be called when the OK
// button is pressed, to pass the results back to C.
var dlg_dimmer = null,
  dlg_form = null;
var dlg_return_funcs = null;

// void dlg_return_sval(int index, const char *val);
// void dlg_return_ival(int index, int val);
//
// C-side entry points called by functions in dlg_return_funcs, to
// pass back the final value in each dialog control.
var dlg_return_sval, dlg_return_ival;

// Callback for reading from a savefile.  This will be filled in with
// a suitable closure by the JS loading code and called by
// js_savefile_read().  This assumes that only one file can be in the
// process of loading at a time.
var savefile_read_callback;

// void prefs_load_callback(midend *me, const char *prefs);
//
// Callback for passing in preferences data retrieved from localStorage.
var prefs_load_callback;

// The <ul> object implementing the game-type drop-down, and a list of
// the sub-lists inside it. Used by js_add_preset().
var gametypelist = document.getElementById("gametype");
var gametypesubmenus = [gametypelist];

// C entry point for miscellaneous events.
var command;

// The <form> encapsulating the menus.  Used by
// js_get_selected_preset() and js_select_preset().
var menuform = document.getElementById("gamemenu");

// The two anchors used to give permalinks to the current puzzle. Used
// by js_update_permalinks().
var permalink_seed = document.getElementById("permalink-seed");
var permalink_desc = document.getElementById("permalink-desc");

// The various buttons. Undo and redo are used by js_enable_undo_redo().
var specific_button = document.getElementById("specific");
var random_button = document.getElementById("random");
var prefs_button = document.getElementById("prefs");
var new_button = document.getElementById("new");
var restart_button = document.getElementById("restart");
var undo_button = document.getElementById("undo");
var redo_button = document.getElementById("redo");
var solve_button = document.getElementById("solve");
var save_button = document.getElementById("save");
var load_button = document.getElementById("load");

// A div element enclosing both the puzzle and its status bar, used
// for positioning the resize handle.
var resizable_div = document.getElementById("resizable");

// Alternatively, an extrinsically sized div that we will size the
// puzzle to fit.
var containing_div = document.getElementById("puzzlecanvascontain");

// The current front-end scale factor.  This is how many logical
// pixels (as seen by the mid end and back end) there are to one
// physical pixel in the canvas.  It's applied as a scaling to the
// graphics context and also used to adjust co-ordinates for things
// that don't pass through that scaling.
//
// In order to keep pixel boundaries where the back end expects them
// to be, this is always an integer and at least 1, so it's not
// necessarily the same as window.devicePixelRatio.
var fe_scale = 1;

// Helper function to find the absolute position of a given DOM
// element on a page, by iterating upwards through the DOM finding
// each element's offset from its parent, and thus calculating the
// page-relative position of the target element.
function element_coords(element) {
  var ex = 0,
    ey = 0;
  while (element.offsetParent) {
    ex += element.offsetLeft;
    ey += element.offsetTop;
    element = element.offsetParent;
  }
  return {
    x: ex,
    y: ey
  };
}

// Helper function which is passed a mouse event object and a DOM
// element, and returns the coordinates of the mouse event relative to
// the top left corner of the element by subtracting element_coords
// from event.page{X,Y}.
function relative_mouse_coords(event, element) {
  var ecoords = element_coords(element);
  return {
    x: event.pageX - ecoords.x,
    y: event.pageY - ecoords.y
  };
}

// Higher-level mouse helper function to specifically map mouse
// coordinates into the coordinates on a canvas that appear under it.
// This depends on the details of how a canvas gets scaled by CSS.
function canvas_mouse_coords(event, element) {
  var rcoords = relative_mouse_coords(event, element);
  // Assume that the CSS object-fit property is "fill" (the default).
  var xscale = element.width / element.offsetWidth / fe_scale;
  var yscale = element.height / element.offsetHeight / fe_scale;
  return {
    x: rcoords.x * xscale,
    y: rcoords.y * yscale
  };
}

// Set the font on a CanvasRenderingContext2d based on the CSS font
// for the canvas, the requested size, and whether we want something
// monospaced.
function canvas_set_font(ctx, size, monospaced) {
  var s = window.getComputedStyle(onscreen_canvas);
  // First set something that we're certain will work.  Constructing
  // the font string from the computed style is a bit fragile, so
  // this acts as a fallback.
  ctx.font = `${size}px ` + (monospaced ? "monospace" : "sans-serif");
  // In CSS Fonts Module Level 4, "font-stretch" gets serialised as
  // a percentage, which can't be used in
  // CanvasRenderingContext2d.font, so we omit it.
  ctx.font = `${s.fontStyle} ${s.fontWeight} ${size}px ` + (monospaced ? "monospace" : s.fontFamily);
}

// Enable and disable items in the CSS menus.
function disable_menu_item(item, disabledFlag) {
  item.disabled = disabledFlag;
}

// Dialog-box functions called from both C and JS.
function dialog_init(titletext) {
  // Forward compatibility: Delete form and dimmer if they already
  // exist.
  dlg_dimmer = document.getElementById("dlgdimmer");
  if (dlg_dimmer) dlg_dimmer.parentElement.removeChild(dlg_dimmer);
  dlg_form = document.getElementById("dlgform");
  if (dlg_form) dlg_form.parentElement.removeChild(dlg_form);

  // Create an overlay on the page which darkens everything
  // beneath it.
  dlg_dimmer = document.createElement("div");
  dlg_dimmer.id = "dlgdimmer";

  // Now create a form which sits on top of that in turn.
  dlg_form = document.createElement("form");
  dlg_form.id = "dlgform";
  var title = document.createElement("h2");
  title.appendChild(document.createTextNode(titletext));
  dlg_form.appendChild(title);
  dlg_return_funcs = [];
}
function dialog_launch(ok_function, cancel_function) {
  // Put in the OK and Cancel buttons at the bottom.
  var button;
  if (ok_function) {
    button = document.createElement("input");
    button.type = "button";
    button.value = "OK";
    button.onclick = ok_function;
    dlg_form.appendChild(button);
  }
  if (cancel_function) {
    button = document.createElement("input");
    button.type = "button";
    button.value = "Cancel";
    button.onclick = cancel_function;
    dlg_form.appendChild(button);
  }
  document.body.appendChild(dlg_dimmer);
  document.body.appendChild(dlg_form);
  dlg_form.querySelector("input,select,a").focus();
}
function dialog_cleanup() {
  document.body.removeChild(dlg_dimmer);
  document.body.removeChild(dlg_form);
  dlg_dimmer = dlg_form = null;
  onscreen_canvas.focus();
}
function set_capture(element, event) {
  // This is only needed if we don't have Pointer Events available.
  if (element.setCapture !== undefined && element.setPointerCapture === undefined) {
    element.setCapture(true);
    return;
  }
}

// Init function called early in main().
function initPuzzle() {
  // Construct the off-screen canvas used for double buffering.
  onscreen_canvas = document.getElementById("puzzlecanvas");
  offscreen_canvas = document.createElement("canvas");
  ctx = offscreen_canvas.getContext('2d', {
    alpha: false
  });

  // Stop right-clicks on the puzzle from popping up a context menu.
  // We need those right-clicks!
  onscreen_canvas.oncontextmenu = function (event) {
    return false;
  };

  // Set up mouse handlers. We do a bit of tracking of the currently
  // pressed mouse buttons, to avoid sending mousemoves with no
  // button down (our puzzles don't want those events).
  var mousedown = Module.cwrap('mousedown', 'boolean', ['number', 'number', 'number']);
  var button_phys2log = [null, null, null];
  var buttons_down = function () {
    var i,
      toret = 0;
    for (i = 0; i < 3; i++) if (button_phys2log[i] !== null) toret |= 1 << button_phys2log[i];
    return toret;
  };
  onscreen_canvas.onpointerdown = function (event) {
    // Arrange that all mouse (and pointer) events are sent to
    // this element until all buttons are released.  We can assume
    // that if we managed to receive a pointerdown event,
    // Element.setPointerCapture() is available.
    onscreen_canvas.setPointerCapture(event.pointerId);
  };
  onscreen_canvas.onmousedown = function (event) {
    if (event.button >= 3) return;
    var xy = canvas_mouse_coords(event, onscreen_canvas);
    var logbutton = event.button;
    if (event.shiftKey) logbutton = 1; // Shift-click overrides to middle button
    else if (event.ctrlKey) logbutton = 2; // Ctrl-click overrides to right button

    if (mousedown(xy.x, xy.y, logbutton)) event.preventDefault();
    button_phys2log[event.button] = logbutton;
    set_capture(onscreen_canvas, event);
  };
  var mousemove = Module.cwrap('mousemove', 'boolean', ['number', 'number', 'number']);
  onscreen_canvas.onmousemove = function (event) {
    var down = buttons_down();
    if (down) {
      var xy = canvas_mouse_coords(event, onscreen_canvas);
      if (mousemove(xy.x, xy.y, down)) event.preventDefault();
    }
  };
  var mouseup = Module.cwrap('mouseup', 'boolean', ['number', 'number', 'number']);
  onscreen_canvas.onmouseup = function (event) {
    if (event.button >= 3) return;
    if (button_phys2log[event.button] !== null) {
      var xy = canvas_mouse_coords(event, onscreen_canvas);
      if (mouseup(xy.x, xy.y, button_phys2log[event.button])) event.preventDefault();
      button_phys2log[event.button] = null;
    }
  };

  // Set up keyboard handlers. We call event.preventDefault()
  // in the keydown handler if it looks like we might have
  // done something with the key.  This means that users
  // of this puzzle collection in other media
  // can indulge their instinct to press ^R for redo, for example,
  // without accidentally reloading the page.
  var key = Module.cwrap('key', 'boolean', ['number', 'string', 'string', 'number', 'number', 'number']);
  onscreen_canvas.onkeydown = function (event) {
    if (key(event.keyCode, event.key, event.char, event.location, event.shiftKey ? 1 : 0, event.ctrlKey ? 1 : 0)) event.preventDefault();
  };

  // command() is a C function called to pass back events which
  // don't fall into other categories like mouse and key events.
  // Mostly those are button presses, but there's also one for the
  // game-type dropdown having been changed.
  command = Module.cwrap('command', 'void', ['number']);

  // Event handlers for buttons and things, which call command().
  if (specific_button) specific_button.onclick = function (event) {
    // Ensure we don't accidentally process these events when a
    // dialog is actually active, e.g. because the button still
    // has keyboard focus
    if (dlg_dimmer === null) command(0);
  };
  if (random_button) random_button.onclick = function (event) {
    if (dlg_dimmer === null) command(1);
  };
  if (new_button) new_button.onclick = function (event) {
    if (dlg_dimmer === null) command(5);
  };
  if (restart_button) restart_button.onclick = function (event) {
    if (dlg_dimmer === null) command(6);
  };
  if (undo_button) undo_button.onclick = function (event) {
    if (dlg_dimmer === null) command(7);
  };
  if (redo_button) redo_button.onclick = function (event) {
    if (dlg_dimmer === null) command(8);
  };
  if (solve_button) solve_button.onclick = function (event) {
    if (dlg_dimmer === null) command(9);
  };
  if (prefs_button) prefs_button.onclick = function (event) {
    if (dlg_dimmer === null) command(10);
  };

  // 'number' is used for C pointers
  var get_save_file = Module.cwrap('get_save_file', 'number', []);
  var free_save_file = Module.cwrap('free_save_file', 'void', ['number']);
  var load_game = Module.cwrap('load_game', 'void', []);
  if (save_button) save_button.onclick = function (event) {
    if (dlg_dimmer === null) {
      var savefile_ptr = get_save_file();
      var savefile_text = UTF8ToString(savefile_ptr);
      free_save_file(savefile_ptr);
      dialog_init("Download saved-game file");
      dlg_form.appendChild(document.createTextNode("Click to download the "));
      var a = document.createElement("a");
      a.download = "puzzle.sav";
      a.href = "data:application/octet-stream," + encodeURIComponent(savefile_text);
      a.appendChild(document.createTextNode("saved-game file"));
      dlg_form.appendChild(a);
      dlg_form.appendChild(document.createTextNode("."));
      dlg_form.appendChild(document.createElement("br"));
      dialog_launch(function (event) {
        dialog_cleanup();
      });
    }
  };
  if (load_button) load_button.onclick = function (event) {
    if (dlg_dimmer === null) {
      var input = document.createElement("input");
      input.type = "file";
      input.multiple = false;
      input.addEventListener("change", function (event) {
        if (input.files.length == 1) {
          var file = input.files.item(0);
          var reader = new FileReader();
          reader.addEventListener("load", function () {
            var pos = 0;
            savefile_read_callback = function (buf, len) {
              if (pos + len > reader.result.byteLength) return false;
              writeArrayToMemory(new Int8Array(reader.result, pos, len), buf);
              pos += len;
              return true;
            };
            load_game();
            savefile_read_callback = null;
          });
          reader.addEventListener("error", function () {
            alert("An error occured while loading the file");
          });
          reader.readAsArrayBuffer(file);
        }
      });
      input.click();
      onscreen_canvas.focus();
    }
  };

  // Find the next or previous item in a menu, or null if there
  // isn't one.  Skip list items that don't have a child (i.e.
  // separators) or whose child is disabled.
  function isuseful(item) {
    return item.querySelector(":scope > :not(:disabled)");
  }
  function nextmenuitem(item) {
    do item = item.nextElementSibling; while (item !== null && !isuseful(item));
    return item;
  }
  function prevmenuitem(item) {
    do item = item.previousElementSibling; while (item !== null && !isuseful(item));
    return item;
  }
  function firstmenuitem(menu) {
    var item = menu && menu.firstElementChild;
    while (item !== null && !isuseful(item)) item = item.nextElementSibling;
    return item;
  }
  function lastmenuitem(menu) {
    var item = menu && menu.lastElementChild;
    while (item !== null && !isuseful(item)) item = item.previousElementSibling;
    return item;
  }
  // Keyboard handlers for the menus.
  function menukey(event) {
    var target = event.target;
    var key = event.key;
    var thisitem = target.closest("li");
    var thismenu = thisitem.closest("ul");
    var targetitem = null;
    var parentitem;
    var parentitem_up = null;
    var parentitem_sideways = null;
    var submenu;
    function ishorizontal(menu) {
      // Which direction does this menu go in?
      var cs = window.getComputedStyle(menu);
      return cs.display == "flex" && cs.flexDirection == "row";
    }
    if (dlg_dimmer !== null) return;
    if (["1", "2", "3", "4", "5", "6", "7", "8", "9", "0"].includes(key)) {
      var shortcutitem = thismenu.querySelectorAll(":scope > li:not([role='separator']")[(Number(key) + 9) % 10];
      if (shortcutitem) {
        target = shortcutitem.firstElementChild;
        target.focus();
        thisitem = target.closest("li");
        key = "Enter";
      }
    }
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Enter", "Escape", "Backspace", "SoftRight", "F10"].includes(key)) return;
    if (ishorizontal(thismenu)) {
      // Top-level menu bar.
      if (key == "ArrowLeft") targetitem = prevmenuitem(thisitem) || lastmenuitem(thismenu);else if (key == "ArrowRight") targetitem = nextmenuitem(thisitem) || firstmenuitem(thismenu);else if (key == "ArrowUp") targetitem = lastmenuitem(thisitem.querySelector("ul"));else if (key == "ArrowDown" || key == "Enter") targetitem = firstmenuitem(thisitem.querySelector("ul"));
    } else {
      // Ordinary vertical menu.
      parentitem = thismenu.closest("li");
      if (parentitem) {
        if (ishorizontal(parentitem.closest("ul"))) parentitem_up = parentitem;else parentitem_sideways = parentitem;
      }
      if (key == "ArrowUp") targetitem = prevmenuitem(thisitem) || parentitem_up || lastmenuitem(thismenu);else if (key == "ArrowDown") targetitem = nextmenuitem(thisitem) || parentitem_up || firstmenuitem(thismenu);else if (key == "ArrowRight") targetitem = thisitem.querySelector("li") || parentitem_up && nextmenuitem(parentitem_up);else if (key == "Enter") targetitem = thisitem.querySelector("li");else if (key == "ArrowLeft") targetitem = parentitem_sideways || parentitem_up && prevmenuitem(parentitem_up);else if (key == "Backspace") targetitem = parentitem;
    }
    if (targetitem) targetitem.firstElementChild.focus();else if (key == "Enter") target.click();else if (key == "Escape" || key == "SoftRight" || key == "F10" || key == "Backspace")
      // Leave the menu entirely.
      onscreen_canvas.focus();
    // Prevent default even if we didn't do anything, as long as this
    // was an interesting key.
    event.preventDefault();
    event.stopPropagation();
  }
  menuform.addEventListener("keydown", menukey);

  // Open documentation links within the application in KaiOS.
  for (var elem of document.querySelectorAll("#gamemenu a[href]")) {
    elem.addEventListener("click", function (event) {
      window.open(event.target.href);
      event.preventDefault();
    });
  }

  // In IE, the canvas doesn't automatically gain focus on a mouse
  // click, so make sure it does
  onscreen_canvas.addEventListener("mousedown", function (event) {
    onscreen_canvas.focus();
  });

  // In our dialog boxes, Return and Escape should be like pressing
  // OK and Cancel respectively
  document.addEventListener("keydown", function (event) {
    if (dlg_dimmer !== null && event.keyCode == 13) {
      for (var i in dlg_return_funcs) dlg_return_funcs[i]();
      command(3);
      event.preventDefault();
      event.stopPropagation();
    }
    if (dlg_dimmer !== null && event.keyCode == 27) {
      command(4);
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);

  // Arrange that the softkey labels are clickable.  This logically
  // belongs as a click handler, but by the time the click event
  // fires, the input focus is in the wrong place.
  function button_to_key(key) {
    return function (mevent) {
      mevent.stopPropagation();
      mevent.preventDefault();
      var kevent = new KeyboardEvent("keydown", {
        key: key,
        view: window,
        bubbles: true
      });
      document.activeElement.dispatchEvent(kevent);
    };
  }
  for (var elem of document.querySelectorAll(".lsk")) elem.addEventListener("mousedown", button_to_key("SoftLeft"));
  for (var elem of document.querySelectorAll(".csk")) elem.addEventListener("mousedown", button_to_key("Enter"));
  for (var elem of document.querySelectorAll(".rsk")) elem.addEventListener("mousedown", button_to_key("SoftRight"));
  document.addEventListener("keydown", function (event) {
    // Key to open the menu on KaiOS.
    if ((event.key == "SoftRight" || event.key == "F10") && !menuform.contains(document.activeElement)) {
      menuform.querySelector("li div, li button").focus();
      event.preventDefault();
      event.stopPropagation();
    }
  });

  // Handle "copy" actions.  Browsers don't reliably target the
  // "copy" event at the canvas when it's focused.  Firefox 102
  // targets the containing <div> while Chromium 114 targets the
  // <body>.  So we catch the event at the document level and work
  // out if it's relevant ourselves.
  var get_text_format = Module.cwrap('get_text_format', 'number', []);
  var free_text_format = Module.cwrap('free_text_format', 'void', ['number']);
  document.addEventListener("copy", function (event) {
    // Make sure the target is an ancestor of the canvas.  And if
    // there's a selection assume the user wants to copy that and
    // not the puzzle.
    if (event.target.contains(onscreen_canvas) && window.getSelection().isCollapsed) {
      var ptr = get_text_format();
      event.clipboardData.setData('text/plain', UTF8ToString(ptr));
      event.preventDefault();
      free_text_format(ptr);
    }
  });

  // Event handler to fake :focus-within on browsers too old for
  // it (like KaiOS 2.5).  Browsers without :focus-within are also
  // too old for focusin/out events, so we have to use focus events
  // which don't bubble but can be captured.
  //
  // A button losing focus because it was disabled doesn't generate
  // a blur event, so we do this entirely in the focus handler.
  document.documentElement.addEventListener("focus", function (event) {
    for (var elem = event.target; elem; elem = elem.parentElement) elem.classList.add("focus-within");
    for (elem of Array.from(document.getElementsByClassName("focus-within"))) if (!elem.contains(event.target)) elem.classList.remove("focus-within");
  }, true);

  // Set up the function pointers we haven't already grabbed. 
  dlg_return_sval = Module.cwrap('dlg_return_sval', 'void', ['number', 'string']);
  dlg_return_ival = Module.cwrap('dlg_return_ival', 'void', ['number', 'number']);
  timer_callback = Module.cwrap('timer_callback', 'void', ['number']);
  prefs_load_callback = Module.cwrap('prefs_load_callback', 'void', ['number', 'number']);
  if (resizable_div !== null) {
    var resize_handle = document.getElementById("resizehandle");
    var resize_xbase = null,
      resize_ybase = null,
      restore_pending = false;
    var resize_xoffset = null,
      resize_yoffset = null;
    var resize_puzzle = Module.cwrap('resize_puzzle', 'void', ['number', 'number']);
    var restore_puzzle_size = Module.cwrap('restore_puzzle_size', 'void', []);
    resize_handle.oncontextmenu = function (event) {
      return false;
    };
    resize_handle.onpointerdown = function (event) {
      resize_handle.setPointerCapture(event.pointerId);
    };
    resize_handle.onmousedown = function (event) {
      if (event.button == 0) {
        var xy = element_coords(onscreen_canvas);
        resize_xbase = xy.x + onscreen_canvas.offsetWidth / 2;
        resize_ybase = xy.y;
        resize_xoffset = xy.x + onscreen_canvas.offsetWidth - event.pageX;
        resize_yoffset = xy.y + onscreen_canvas.offsetHeight - event.pageY;
      } else {
        restore_pending = true;
      }
      set_capture(resize_handle, event);
      event.preventDefault();
    };
    window.addEventListener("mousemove", function (event) {
      if (resize_xbase !== null && resize_ybase !== null) {
        var dpr = window.devicePixelRatio || 1;
        resize_puzzle((event.pageX + resize_xoffset - resize_xbase) * dpr * 2, (event.pageY + resize_yoffset - resize_ybase) * dpr);
        event.preventDefault();
        // Chrome insists on selecting text during a resize drag
        // no matter what I do
        if (window.getSelection) window.getSelection().removeAllRanges();else document.selection.empty();
      }
    });
    window.addEventListener("mouseup", function (event) {
      if (resize_xbase !== null && resize_ybase !== null) {
        resize_xbase = null;
        resize_ybase = null;
        onscreen_canvas.focus(); // return focus to the puzzle
        event.preventDefault();
      } else if (restore_pending) {
        // If you have the puzzle at larger than normal size and
        // then right-click to restore, I haven't found any way to
        // stop Chrome and IE popping up a context menu on the
        // revealed piece of document when you release the button
        // except by putting the actual restore into a setTimeout.
        // Gah.
        setTimeout(function () {
          restore_pending = false;
          restore_puzzle_size();
          onscreen_canvas.focus();
        }, 20);
        event.preventDefault();
      }
    });
  }
  var rescale_puzzle = Module.cwrap('rescale_puzzle', 'void', []);
  /*
   * If the puzzle is sized to fit the page, try to detect changes
   * of size of the containing element.  Ideally this would use a
   * ResizeObserver on the containing_div, but I want this to work
   * on KaiOS 2.5, which doesn't have ResizeObserver.  Instead we
   * watch events that might indicate that the div has changed size.
   */
  if (containing_div !== null) {
    var resize_handler = function (event) {
      rescale_puzzle();
    };
    window.addEventListener("resize", resize_handler);
    // Also catch the point when the document finishes loading,
    // since sometimes we seem to get the div's size too early.
    window.addEventListener("load", resize_handler);
  }
}
function post_init() {
  /*
   * Arrange to detect changes of device pixel ratio.  Adapted from
   * <https://developer.mozilla.org/en-US/docs/Web/API/Window/
   * devicePixelRatio> (CC0) to work on older browsers.
   */
  var rescale_puzzle = Module.cwrap('rescale_puzzle', 'void', []);
  var mql = null;
  var update_pixel_ratio = function () {
    var dpr = window.devicePixelRatio;
    if (mql !== null) mql.removeListener(update_pixel_ratio);
    mql = window.matchMedia(`(resolution: ${dpr}dppx)`);
    mql.addListener(update_pixel_ratio);
    rescale_puzzle();
  };
  update_pixel_ratio();
  // If we get here with everything having gone smoothly, i.e.
  // we haven't crashed for one reason or another during setup, then
  // it's probably safe to hide the 'sorry, no puzzle here' div and
  // show the div containing the actual puzzle.
  var apology = document.getElementById("apology");
  if (apology !== null) apology.style.display = "none";
  document.getElementById("puzzle").style.display = "";

  // Default to giving keyboard focus to the puzzle.
  onscreen_canvas.focus();
}
// end include: /tmp/bob.1.0/puzzles/emccpre.js

// Sometimes an existing Module object exists with properties
// meant to overwrite the default module functionality. Here
// we collect those properties and reapply _after_ we configure
// the current environment's defaults to avoid having to be so
// defensive during initialization.
var moduleOverrides = Object.assign({}, Module);
var arguments_ = [];
var thisProgram = './this.program';
var quit_ = (status, toThrow) => {
  throw toThrow;
};

// `/` should be present at the end if `scriptDirectory` is not empty
var scriptDirectory = '';
function locateFile(path) {
  if (Module['locateFile']) {
    return Module['locateFile'](path, scriptDirectory);
  }
  return scriptDirectory + path;
}

// Hooks that are implemented differently in different runtime environments.
var readAsync, readBinary;
if (ENVIRONMENT_IS_SHELL) {
  if (typeof process == 'object' && typeof require === 'function' || typeof window == 'object' || typeof importScripts == 'function') throw new Error('not compiled for this environment (did you build to HTML and try to run it not on the web, or set ENVIRONMENT to something - like node - and run it someplace else - like on the web?)');
} else
  // Note that this includes Node.js workers when relevant (pthreads is enabled).
  // Node.js workers are detected as a combination of ENVIRONMENT_IS_WORKER and
  // ENVIRONMENT_IS_NODE.
  if (ENVIRONMENT_IS_WEB || ENVIRONMENT_IS_WORKER) {
    if (ENVIRONMENT_IS_WORKER) {
      // Check worker, not web, since window could be polyfilled
      scriptDirectory = self.location.href;
    } else if (typeof document != 'undefined' && document.currentScript) {
      // web
      scriptDirectory = document.currentScript.src;
    }
    // blob urls look like blob:http://site.com/etc/etc and we cannot infer anything from them.
    // otherwise, slice off the final part of the url to find the script directory.
    // if scriptDirectory does not contain a slash, lastIndexOf will return -1,
    // and scriptDirectory will correctly be replaced with an empty string.
    // If scriptDirectory contains a query (starting with ?) or a fragment (starting with #),
    // they are removed because they could contain a slash.
    if (scriptDirectory.startsWith('blob:')) {
      scriptDirectory = '';
    } else {
      scriptDirectory = scriptDirectory.substr(0, scriptDirectory.replace(/[?#].*/, '').lastIndexOf('/') + 1);
    }
    if (!(typeof window == 'object' || typeof importScripts == 'function')) throw new Error('not compiled for this environment (did you build to HTML and try to run it not on the web, or set ENVIRONMENT to something - like node - and run it someplace else - like on the web?)');
    {
      // include: web_or_worker_shell_read.js
      readAsync = url => {
        assert(!isFileURI(url), "readAsync does not work with file:// URLs");
        return fetch(url, {
          credentials: 'same-origin'
        }).then(response => {
          if (response.ok) {
            return response.arrayBuffer();
          }
          return Promise.reject(new Error(response.status + ' : ' + response.url));
        });
      };
      // end include: web_or_worker_shell_read.js
    }
  } else {
    throw new Error('environment detection error');
  }
var out = Module['print'] || console.log.bind(console);
var err = Module['printErr'] || console.error.bind(console);

// Merge back in the overrides
Object.assign(Module, moduleOverrides);
// Free the object hierarchy contained in the overrides, this lets the GC
// reclaim data used.
moduleOverrides = null;
checkIncomingModuleAPI();

// Emit code to handle expected values on the Module object. This applies Module.x
// to the proper local x. This has two benefits: first, we only emit it if it is
// expected to arrive, and second, by using a local everywhere else that can be
// minified.

if (Module['arguments']) arguments_ = Module['arguments'];
legacyModuleProp('arguments', 'arguments_');
if (Module['thisProgram']) thisProgram = Module['thisProgram'];
legacyModuleProp('thisProgram', 'thisProgram');
if (Module['quit']) quit_ = Module['quit'];
legacyModuleProp('quit', 'quit_');

// perform assertions in shell.js after we set up out() and err(), as otherwise if an assertion fails it cannot print the message
// Assertions on removed incoming Module JS APIs.
assert(typeof Module['memoryInitializerPrefixURL'] == 'undefined', 'Module.memoryInitializerPrefixURL option was removed, use Module.locateFile instead');
assert(typeof Module['pthreadMainPrefixURL'] == 'undefined', 'Module.pthreadMainPrefixURL option was removed, use Module.locateFile instead');
assert(typeof Module['cdInitializerPrefixURL'] == 'undefined', 'Module.cdInitializerPrefixURL option was removed, use Module.locateFile instead');
assert(typeof Module['filePackagePrefixURL'] == 'undefined', 'Module.filePackagePrefixURL option was removed, use Module.locateFile instead');
assert(typeof Module['read'] == 'undefined', 'Module.read option was removed');
assert(typeof Module['readAsync'] == 'undefined', 'Module.readAsync option was removed (modify readAsync in JS)');
assert(typeof Module['readBinary'] == 'undefined', 'Module.readBinary option was removed (modify readBinary in JS)');
assert(typeof Module['setWindowTitle'] == 'undefined', 'Module.setWindowTitle option was removed (modify emscripten_set_window_title in JS)');
assert(typeof Module['TOTAL_MEMORY'] == 'undefined', 'Module.TOTAL_MEMORY has been renamed Module.INITIAL_MEMORY');
legacyModuleProp('asm', 'wasmExports');
legacyModuleProp('readAsync', 'readAsync');
legacyModuleProp('readBinary', 'readBinary');
legacyModuleProp('setWindowTitle', 'setWindowTitle');
var IDBFS = 'IDBFS is no longer included by default; build with -lidbfs.js';
var PROXYFS = 'PROXYFS is no longer included by default; build with -lproxyfs.js';
var WORKERFS = 'WORKERFS is no longer included by default; build with -lworkerfs.js';
var FETCHFS = 'FETCHFS is no longer included by default; build with -lfetchfs.js';
var ICASEFS = 'ICASEFS is no longer included by default; build with -licasefs.js';
var JSFILEFS = 'JSFILEFS is no longer included by default; build with -ljsfilefs.js';
var OPFS = 'OPFS is no longer included by default; build with -lopfs.js';
var NODEFS = 'NODEFS is no longer included by default; build with -lnodefs.js';
assert(!ENVIRONMENT_IS_WORKER, 'worker environment detected but not enabled at build time.  Add `worker` to `-sENVIRONMENT` to enable.');
assert(!ENVIRONMENT_IS_NODE, 'node environment detected but not enabled at build time.  Add `node` to `-sENVIRONMENT` to enable.');
assert(!ENVIRONMENT_IS_SHELL, 'shell environment detected but not enabled at build time.  Add `shell` to `-sENVIRONMENT` to enable.');

// end include: shell.js

// include: preamble.js
// === Preamble library stuff ===

// Documentation for the public APIs defined in this file must be updated in:
//    site/source/docs/api_reference/preamble.js.rst
// A prebuilt local version of the documentation is available at:
//    site/build/text/docs/api_reference/preamble.js.txt
// You can also build docs locally as HTML or other formats in site/
// An online HTML version (which may be of a different version of Emscripten)
//    is up at http://kripken.github.io/emscripten-site/docs/api_reference/preamble.js.html

var wasmBinary;
if (Module['wasmBinary']) wasmBinary = Module['wasmBinary'];
legacyModuleProp('wasmBinary', 'wasmBinary');
if (typeof WebAssembly != 'object') {
  err('no native wasm support detected');
}

// Wasm globals

var wasmMemory;

//========================================
// Runtime essentials
//========================================

// whether we are quitting the application. no code should run after this.
// set in exit() and abort()
var ABORT = false;

// set by exit() and abort().  Passed to 'onExit' handler.
// NOTE: This is also used as the process return code code in shell environments
// but only when noExitRuntime is false.
var EXITSTATUS;

// In STRICT mode, we only define assert() when ASSERTIONS is set.  i.e. we
// don't define it at all in release modes.  This matches the behaviour of
// MINIMAL_RUNTIME.
// TODO(sbc): Make this the default even without STRICT enabled.
/** @type {function(*, string=)} */
function assert(condition, text) {
  if (!condition) {
    abort('Assertion failed' + (text ? ': ' + text : ''));
  }
}

// We used to include malloc/free by default in the past. Show a helpful error in
// builds with assertions.

// Memory management

var HEAP, /** @type {!Int8Array} */
  HEAP8, /** @type {!Uint8Array} */
  HEAPU8, /** @type {!Int16Array} */
  HEAP16, /** @type {!Uint16Array} */
  HEAPU16, /** @type {!Int32Array} */
  HEAP32, /** @type {!Uint32Array} */
  HEAPU32, /** @type {!Float32Array} */
  HEAPF32,
  /* BigInt64Array type is not correctly defined in closure
  /** not-@type {!BigInt64Array} */
  HEAP64,
  /* BigUInt64Array type is not correctly defined in closure
  /** not-t@type {!BigUint64Array} */
  HEAPU64, /** @type {!Float64Array} */
  HEAPF64;

// include: runtime_shared.js
function updateMemoryViews() {
  var b = wasmMemory.buffer;
  Module['HEAP8'] = HEAP8 = new Int8Array(b);
  Module['HEAP16'] = HEAP16 = new Int16Array(b);
  Module['HEAPU8'] = HEAPU8 = new Uint8Array(b);
  Module['HEAPU16'] = HEAPU16 = new Uint16Array(b);
  Module['HEAP32'] = HEAP32 = new Int32Array(b);
  Module['HEAPU32'] = HEAPU32 = new Uint32Array(b);
  Module['HEAPF32'] = HEAPF32 = new Float32Array(b);
  Module['HEAPF64'] = HEAPF64 = new Float64Array(b);
  Module['HEAP64'] = HEAP64 = new BigInt64Array(b);
  Module['HEAPU64'] = HEAPU64 = new BigUint64Array(b);
}
// end include: runtime_shared.js
assert(!Module['STACK_SIZE'], 'STACK_SIZE can no longer be set at runtime.  Use -sSTACK_SIZE at link time');
assert(typeof Int32Array != 'undefined' && typeof Float64Array !== 'undefined' && Int32Array.prototype.subarray != undefined && Int32Array.prototype.set != undefined, 'JS engine does not provide full typed array support');

// If memory is defined in wasm, the user can't provide it, or set INITIAL_MEMORY
assert(!Module['wasmMemory'], 'Use of `wasmMemory` detected.  Use -sIMPORTED_MEMORY to define wasmMemory externally');
assert(!Module['INITIAL_MEMORY'], 'Detected runtime INITIAL_MEMORY setting.  Use -sIMPORTED_MEMORY to define wasmMemory dynamically');

// include: runtime_stack_check.js
// Initializes the stack cookie. Called at the startup of main and at the startup of each thread in pthreads mode.
function writeStackCookie() {
  var max = _emscripten_stack_get_end();
  assert((max & 3) == 0);
  // If the stack ends at address zero we write our cookies 4 bytes into the
  // stack.  This prevents interference with SAFE_HEAP and ASAN which also
  // monitor writes to address zero.
  if (max == 0) {
    max += 4;
  }
  // The stack grow downwards towards _emscripten_stack_get_end.
  // We write cookies to the final two words in the stack and detect if they are
  // ever overwritten.
  HEAPU32[max >> 2] = 0x02135467;
  HEAPU32[max + 4 >> 2] = 0x89BACDFE;
  // Also test the global address 0 for integrity.
  HEAPU32[0 >> 2] = 1668509029;
}
function checkStackCookie() {
  if (ABORT) return;
  var max = _emscripten_stack_get_end();
  // See writeStackCookie().
  if (max == 0) {
    max += 4;
  }
  var cookie1 = HEAPU32[max >> 2];
  var cookie2 = HEAPU32[max + 4 >> 2];
  if (cookie1 != 0x02135467 || cookie2 != 0x89BACDFE) {
    abort(`Stack overflow! Stack cookie has been overwritten at ${ptrToString(max)}, expected hex dwords 0x89BACDFE and 0x2135467, but received ${ptrToString(cookie2)} ${ptrToString(cookie1)}`);
  }
  // Also test the global address 0 for integrity.
  if (HEAPU32[0 >> 2] != 0x63736d65 /* 'emsc' */) {
    abort('Runtime error: The application has corrupted its heap memory area (address zero)!');
  }
}
// end include: runtime_stack_check.js
// include: runtime_assertions.js
// Endianness check
(function () {
  var h16 = new Int16Array(1);
  var h8 = new Int8Array(h16.buffer);
  h16[0] = 0x6373;
  if (h8[0] !== 0x73 || h8[1] !== 0x63) throw 'Runtime error: expected the system to be little-endian! (Run with -sSUPPORT_BIG_ENDIAN to bypass)';
})();

// end include: runtime_assertions.js
var __ATPRERUN__ = []; // functions called before the runtime is initialized
var __ATINIT__ = []; // functions called during startup
var __ATMAIN__ = []; // functions called when main() is to be run
var __ATEXIT__ = []; // functions called during shutdown
var __ATPOSTRUN__ = []; // functions called after the main() is called

var runtimeInitialized = false;
function preRun() {
  if (Module['preRun']) {
    if (typeof Module['preRun'] == 'function') Module['preRun'] = [Module['preRun']];
    while (Module['preRun'].length) {
      addOnPreRun(Module['preRun'].shift());
    }
  }
  callRuntimeCallbacks(__ATPRERUN__);
}
function initRuntime() {
  assert(!runtimeInitialized);
  runtimeInitialized = true;
  checkStackCookie();
  callRuntimeCallbacks(__ATINIT__);
}
function preMain() {
  checkStackCookie();
  callRuntimeCallbacks(__ATMAIN__);
}
function postRun() {
  checkStackCookie();
  if (Module['postRun']) {
    if (typeof Module['postRun'] == 'function') Module['postRun'] = [Module['postRun']];
    while (Module['postRun'].length) {
      addOnPostRun(Module['postRun'].shift());
    }
  }
  callRuntimeCallbacks(__ATPOSTRUN__);
}
function addOnPreRun(cb) {
  __ATPRERUN__.unshift(cb);
}
function addOnInit(cb) {
  __ATINIT__.unshift(cb);
}
function addOnPreMain(cb) {
  __ATMAIN__.unshift(cb);
}
function addOnExit(cb) {}
function addOnPostRun(cb) {
  __ATPOSTRUN__.unshift(cb);
}

// include: runtime_math.js
// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/imul

// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/fround

// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/clz32

// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/trunc

assert(Math.imul, 'This browser does not support Math.imul(), build with LEGACY_VM_SUPPORT or POLYFILL_OLD_MATH_FUNCTIONS to add in a polyfill');
assert(Math.fround, 'This browser does not support Math.fround(), build with LEGACY_VM_SUPPORT or POLYFILL_OLD_MATH_FUNCTIONS to add in a polyfill');
assert(Math.clz32, 'This browser does not support Math.clz32(), build with LEGACY_VM_SUPPORT or POLYFILL_OLD_MATH_FUNCTIONS to add in a polyfill');
assert(Math.trunc, 'This browser does not support Math.trunc(), build with LEGACY_VM_SUPPORT or POLYFILL_OLD_MATH_FUNCTIONS to add in a polyfill');
// end include: runtime_math.js
// A counter of dependencies for calling run(). If we need to
// do asynchronous work before running, increment this and
// decrement it. Incrementing must happen in a place like
// Module.preRun (used by emcc to add file preloading).
// Note that you can add dependencies in preRun, even though
// it happens right before run - run will be postponed until
// the dependencies are met.
var runDependencies = 0;
var runDependencyWatcher = null;
var dependenciesFulfilled = null; // overridden to take different actions when all run dependencies are fulfilled
var runDependencyTracking = {};
function getUniqueRunDependency(id) {
  var orig = id;
  while (1) {
    if (!runDependencyTracking[id]) return id;
    id = orig + Math.random();
  }
}
function addRunDependency(id) {
  var _Module$monitorRunDep, _Module;
  runDependencies++;
  (_Module$monitorRunDep = (_Module = Module)['monitorRunDependencies']) === null || _Module$monitorRunDep === void 0 || _Module$monitorRunDep.call(_Module, runDependencies);
  if (id) {
    assert(!runDependencyTracking[id]);
    runDependencyTracking[id] = 1;
    if (runDependencyWatcher === null && typeof setInterval != 'undefined') {
      // Check for missing dependencies every few seconds
      runDependencyWatcher = setInterval(() => {
        if (ABORT) {
          clearInterval(runDependencyWatcher);
          runDependencyWatcher = null;
          return;
        }
        var shown = false;
        for (var dep in runDependencyTracking) {
          if (!shown) {
            shown = true;
            err('still waiting on run dependencies:');
          }
          err(`dependency: ${dep}`);
        }
        if (shown) {
          err('(end of list)');
        }
      }, 10000);
    }
  } else {
    err('warning: run dependency added without ID');
  }
}
function removeRunDependency(id) {
  var _Module$monitorRunDep2, _Module2;
  runDependencies--;
  (_Module$monitorRunDep2 = (_Module2 = Module)['monitorRunDependencies']) === null || _Module$monitorRunDep2 === void 0 || _Module$monitorRunDep2.call(_Module2, runDependencies);
  if (id) {
    assert(runDependencyTracking[id]);
    delete runDependencyTracking[id];
  } else {
    err('warning: run dependency removed without ID');
  }
  if (runDependencies == 0) {
    if (runDependencyWatcher !== null) {
      clearInterval(runDependencyWatcher);
      runDependencyWatcher = null;
    }
    if (dependenciesFulfilled) {
      var callback = dependenciesFulfilled;
      dependenciesFulfilled = null;
      callback(); // can add another dependenciesFulfilled
    }
  }
}

/** @param {string|number=} what */
function abort(what) {
  var _Module$onAbort, _Module3;
  (_Module$onAbort = (_Module3 = Module)['onAbort']) === null || _Module$onAbort === void 0 || _Module$onAbort.call(_Module3, what);
  what = 'Aborted(' + what + ')';
  // TODO(sbc): Should we remove printing and leave it up to whoever
  // catches the exception?
  err(what);
  ABORT = true;
  EXITSTATUS = 1;

  // Use a wasm runtime error, because a JS error might be seen as a foreign
  // exception, which means we'd run destructors on it. We need the error to
  // simply make the program stop.
  // FIXME This approach does not work in Wasm EH because it currently does not assume
  // all RuntimeErrors are from traps; it decides whether a RuntimeError is from
  // a trap or not based on a hidden field within the object. So at the moment
  // we don't have a way of throwing a wasm trap from JS. TODO Make a JS API that
  // allows this in the wasm spec.

  // Suppress closure compiler warning here. Closure compiler's builtin extern
  // definition for WebAssembly.RuntimeError claims it takes no arguments even
  // though it can.
  // TODO(https://github.com/google/closure-compiler/pull/3913): Remove if/when upstream closure gets fixed.
  /** @suppress {checkTypes} */
  var e = new WebAssembly.RuntimeError(what);

  // Throw the error whether or not MODULARIZE is set because abort is used
  // in code paths apart from instantiation where an exception is expected
  // to be thrown when abort is called.
  throw e;
}

// include: memoryprofiler.js
// end include: memoryprofiler.js
// show errors on likely calls to FS when it was not included
var FS = {
  error() {
    abort('Filesystem support (FS) was not included. The problem is that you are using files from JS, but files were not used from C/C++, so filesystem support was not auto-included. You can force-include filesystem support with -sFORCE_FILESYSTEM');
  },
  init() {
    FS.error();
  },
  createDataFile() {
    FS.error();
  },
  createPreloadedFile() {
    FS.error();
  },
  createLazyFile() {
    FS.error();
  },
  open() {
    FS.error();
  },
  mkdev() {
    FS.error();
  },
  registerDevice() {
    FS.error();
  },
  analyzePath() {
    FS.error();
  },
  ErrnoError() {
    FS.error();
  }
};
Module['FS_createDataFile'] = FS.createDataFile;
Module['FS_createPreloadedFile'] = FS.createPreloadedFile;

// include: URIUtils.js
// Prefix of data URIs emitted by SINGLE_FILE and related options.
var dataURIPrefix = 'data:application/octet-stream;base64,';

/**
 * Indicates whether filename is a base64 data URI.
 * @noinline
 */
var isDataURI = filename => filename.startsWith(dataURIPrefix);

/**
 * Indicates whether filename is delivered via file protocol (as opposed to http/https)
 * @noinline
 */
var isFileURI = filename => filename.startsWith('file://');
// end include: URIUtils.js
function createExportWrapper(name, nargs) {
  return function () {
    assert(runtimeInitialized, `native function \`${name}\` called before runtime initialization`);
    var f = wasmExports[name];
    assert(f, `exported native function \`${name}\` not found`);
    // Only assert for too many arguments. Too few can be valid since the missing arguments will be zero filled.
    assert(arguments.length <= nargs, `native function \`${name}\` called with ${arguments.length} args but expects ${nargs}`);
    return f(...arguments);
  };
}

// include: runtime_exceptions.js
// end include: runtime_exceptions.js
function findWasmBinary() {
  var f = 'samegame.wasm';
  if (!isDataURI(f)) {
    return locateFile(f);
  }
  return f;
}
var wasmBinaryFile;
function getBinarySync(file) {
  if (file == wasmBinaryFile && wasmBinary) {
    return new Uint8Array(wasmBinary);
  }
  if (readBinary) {
    return readBinary(file);
  }
  throw 'both async and sync fetching of the wasm failed';
}
function getBinaryPromise(binaryFile) {
  // If we don't have the binary yet, load it asynchronously using readAsync.
  if (!wasmBinary) {
    // Fetch the binary using readAsync
    return readAsync(binaryFile).then(response => new Uint8Array( /** @type{!ArrayBuffer} */response),
    // Fall back to getBinarySync if readAsync fails
    () => getBinarySync(binaryFile));
  }

  // Otherwise, getBinarySync should be able to get it synchronously
  return Promise.resolve().then(() => getBinarySync(binaryFile));
}
function instantiateArrayBuffer(binaryFile, imports, receiver) {
  return getBinaryPromise(binaryFile).then(binary => {
    return WebAssembly.instantiate(binary, imports);
  }).then(receiver, reason => {
    err(`failed to asynchronously prepare wasm: ${reason}`);

    // Warn on some common problems.
    if (isFileURI(wasmBinaryFile)) {
      err(`warning: Loading from a file URI (${wasmBinaryFile}) is not supported in most browsers. See https://emscripten.org/docs/getting_started/FAQ.html#how-do-i-run-a-local-webserver-for-testing-why-does-my-program-stall-in-downloading-or-preparing`);
    }
    abort(reason);
  });
}
function instantiateAsync(binary, binaryFile, imports, callback) {
  if (!binary && typeof WebAssembly.instantiateStreaming == 'function' && !isDataURI(binaryFile) && typeof fetch == 'function') {
    return fetch(binaryFile, {
      credentials: 'same-origin'
    }).then(response => {
      // Suppress closure warning here since the upstream definition for
      // instantiateStreaming only allows Promise<Repsponse> rather than
      // an actual Response.
      // TODO(https://github.com/google/closure-compiler/pull/3913): Remove if/when upstream closure is fixed.
      /** @suppress {checkTypes} */
      var result = WebAssembly.instantiateStreaming(response, imports);
      return result.then(callback, function (reason) {
        // We expect the most common failure cause to be a bad MIME type for the binary,
        // in which case falling back to ArrayBuffer instantiation should work.
        err(`wasm streaming compile failed: ${reason}`);
        err('falling back to ArrayBuffer instantiation');
        return instantiateArrayBuffer(binaryFile, imports, callback);
      });
    });
  }
  return instantiateArrayBuffer(binaryFile, imports, callback);
}
function getWasmImports() {
  // prepare imports
  return {
    'env': wasmImports,
    'wasi_snapshot_preview1': wasmImports
  };
}

// Create the wasm instance.
// Receives the wasm imports, returns the exports.
function createWasm() {
  var info = getWasmImports();
  // Load the wasm module and create an instance of using native support in the JS engine.
  // handle a generated wasm instance, receiving its exports and
  // performing other necessary setup
  /** @param {WebAssembly.Module=} module*/
  function receiveInstance(instance, module) {
    wasmExports = instance.exports;
    wasmMemory = wasmExports['memory'];
    assert(wasmMemory, 'memory not found in wasm exports');
    updateMemoryViews();
    addOnInit(wasmExports['__wasm_call_ctors']);
    removeRunDependency('wasm-instantiate');
    return wasmExports;
  }
  // wait for the pthread pool (if any)
  addRunDependency('wasm-instantiate');

  // Prefer streaming instantiation if available.
  // Async compilation can be confusing when an error on the page overwrites Module
  // (for example, if the order of elements is wrong, and the one defining Module is
  // later), so we save Module and check it later.
  var trueModule = Module;
  function receiveInstantiationResult(result) {
    // 'result' is a ResultObject object which has both the module and instance.
    // receiveInstance() will swap in the exports (to Module.asm) so they can be called
    assert(Module === trueModule, 'the Module object should not be replaced during async compilation - perhaps the order of HTML elements is wrong?');
    trueModule = null;
    // TODO: Due to Closure regression https://github.com/google/closure-compiler/issues/3193, the above line no longer optimizes out down to the following line.
    // When the regression is fixed, can restore the above PTHREADS-enabled path.
    receiveInstance(result['instance']);
  }

  // User shell pages can write their own Module.instantiateWasm = function(imports, successCallback) callback
  // to manually instantiate the Wasm module themselves. This allows pages to
  // run the instantiation parallel to any other async startup actions they are
  // performing.
  // Also pthreads and wasm workers initialize the wasm instance through this
  // path.
  if (Module['instantiateWasm']) {
    try {
      return Module['instantiateWasm'](info, receiveInstance);
    } catch (e) {
      err(`Module.instantiateWasm callback failed with error: ${e}`);
      return false;
    }
  }
  if (!wasmBinaryFile) wasmBinaryFile = findWasmBinary();
  instantiateAsync(wasmBinary, wasmBinaryFile, info, receiveInstantiationResult);
  return {}; // no exports yet; we'll fill them in later
}

// include: runtime_debug.js
function legacyModuleProp(prop, newName) {
  let incoming = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : true;
  if (!Object.getOwnPropertyDescriptor(Module, prop)) {
    Object.defineProperty(Module, prop, {
      configurable: true,
      get() {
        let extra = incoming ? ' (the initial value can be provided on Module, but after startup the value is only looked for on a local variable of that name)' : '';
        abort(`\`Module.${prop}\` has been replaced by \`${newName}\`` + extra);
      }
    });
  }
}
function ignoredModuleProp(prop) {
  if (Object.getOwnPropertyDescriptor(Module, prop)) {
    abort(`\`Module.${prop}\` was supplied but \`${prop}\` not included in INCOMING_MODULE_JS_API`);
  }
}

// forcing the filesystem exports a few things by default
function isExportedByForceFilesystem(name) {
  return name === 'FS_createPath' || name === 'FS_createDataFile' || name === 'FS_createPreloadedFile' || name === 'FS_unlink' || name === 'addRunDependency' ||
  // The old FS has some functionality that WasmFS lacks.
  name === 'FS_createLazyFile' || name === 'FS_createDevice' || name === 'removeRunDependency';
}
function missingGlobal(sym, msg) {
  if (typeof globalThis != 'undefined') {
    Object.defineProperty(globalThis, sym, {
      configurable: true,
      get() {
        warnOnce(`\`${sym}\` is not longer defined by emscripten. ${msg}`);
        return undefined;
      }
    });
  }
}
missingGlobal('buffer', 'Please use HEAP8.buffer or wasmMemory.buffer');
missingGlobal('asm', 'Please use wasmExports instead');
function missingLibrarySymbol(sym) {
  if (typeof globalThis != 'undefined' && !Object.getOwnPropertyDescriptor(globalThis, sym)) {
    Object.defineProperty(globalThis, sym, {
      configurable: true,
      get() {
        // Can't `abort()` here because it would break code that does runtime
        // checks.  e.g. `if (typeof SDL === 'undefined')`.
        var msg = `\`${sym}\` is a library symbol and not included by default; add it to your library.js __deps or to DEFAULT_LIBRARY_FUNCS_TO_INCLUDE on the command line`;
        // DEFAULT_LIBRARY_FUNCS_TO_INCLUDE requires the name as it appears in
        // library.js, which means $name for a JS name with no prefix, or name
        // for a JS name like _name.
        var librarySymbol = sym;
        if (!librarySymbol.startsWith('_')) {
          librarySymbol = '$' + sym;
        }
        msg += ` (e.g. -sDEFAULT_LIBRARY_FUNCS_TO_INCLUDE='${librarySymbol}')`;
        if (isExportedByForceFilesystem(sym)) {
          msg += '. Alternatively, forcing filesystem support (-sFORCE_FILESYSTEM) can export this for you';
        }
        warnOnce(msg);
        return undefined;
      }
    });
  }
  // Any symbol that is not included from the JS library is also (by definition)
  // not exported on the Module object.
  unexportedRuntimeSymbol(sym);
}
function unexportedRuntimeSymbol(sym) {
  if (!Object.getOwnPropertyDescriptor(Module, sym)) {
    Object.defineProperty(Module, sym, {
      configurable: true,
      get() {
        var msg = `'${sym}' was not exported. add it to EXPORTED_RUNTIME_METHODS (see the Emscripten FAQ)`;
        if (isExportedByForceFilesystem(sym)) {
          msg += '. Alternatively, forcing filesystem support (-sFORCE_FILESYSTEM) can export this for you';
        }
        abort(msg);
      }
    });
  }
}

// Used by XXXXX_DEBUG settings to output debug messages.
function dbg() {
  // TODO(sbc): Make this configurable somehow.  Its not always convenient for
  // logging to show up as warnings.
  console.warn(...arguments);
}
// end include: runtime_debug.js
// === Body ===
// end include: preamble.js

/** @constructor */
function ExitStatus(status) {
  this.name = 'ExitStatus';
  this.message = `Program terminated with exit(${status})`;
  this.status = status;
}
var callRuntimeCallbacks = callbacks => {
  while (callbacks.length > 0) {
    // Pass the module as the first argument.
    callbacks.shift()(Module);
  }
};

/**
 * @param {number} ptr
 * @param {string} type
 */
function getValue(ptr) {
  let type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'i8';
  if (type.endsWith('*')) type = '*';
  switch (type) {
    case 'i1':
      return HEAP8[ptr];
    case 'i8':
      return HEAP8[ptr];
    case 'i16':
      return HEAP16[ptr >> 1];
    case 'i32':
      return HEAP32[ptr >> 2];
    case 'i64':
      return HEAP64[ptr >> 3];
    case 'float':
      return HEAPF32[ptr >> 2];
    case 'double':
      return HEAPF64[ptr >> 3];
    case '*':
      return HEAPU32[ptr >> 2];
    default:
      abort(`invalid type for getValue: ${type}`);
  }
}
var noExitRuntime = Module['noExitRuntime'] || true;
var ptrToString = ptr => {
  assert(typeof ptr === 'number');
  // With CAN_ADDRESS_2GB or MEMORY64, pointers are already unsigned.
  ptr >>>= 0;
  return '0x' + ptr.toString(16).padStart(8, '0');
};

/**
 * @param {number} ptr
 * @param {number} value
 * @param {string} type
 */
function setValue(ptr, value) {
  let type = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'i8';
  if (type.endsWith('*')) type = '*';
  switch (type) {
    case 'i1':
      HEAP8[ptr] = value;
      break;
    case 'i8':
      HEAP8[ptr] = value;
      break;
    case 'i16':
      HEAP16[ptr >> 1] = value;
      break;
    case 'i32':
      HEAP32[ptr >> 2] = value;
      break;
    case 'i64':
      HEAP64[ptr >> 3] = BigInt(value);
      break;
    case 'float':
      HEAPF32[ptr >> 2] = value;
      break;
    case 'double':
      HEAPF64[ptr >> 3] = value;
      break;
    case '*':
      HEAPU32[ptr >> 2] = value;
      break;
    default:
      abort(`invalid type for setValue: ${type}`);
  }
}
var stackRestore = val => __emscripten_stack_restore(val);
var stackSave = () => _emscripten_stack_get_current();
var warnOnce = text => {
  warnOnce.shown || (warnOnce.shown = {});
  if (!warnOnce.shown[text]) {
    warnOnce.shown[text] = 1;
    err(text);
  }
};
var UTF8Decoder = typeof TextDecoder != 'undefined' ? new TextDecoder() : undefined;

/**
 * Given a pointer 'idx' to a null-terminated UTF8-encoded string in the given
 * array that contains uint8 values, returns a copy of that string as a
 * Javascript String object.
 * heapOrArray is either a regular array, or a JavaScript typed array view.
 * @param {number} idx
 * @param {number=} maxBytesToRead
 * @return {string}
 */
var UTF8ArrayToString = (heapOrArray, idx, maxBytesToRead) => {
  var endIdx = idx + maxBytesToRead;
  var endPtr = idx;
  // TextDecoder needs to know the byte length in advance, it doesn't stop on
  // null terminator by itself.  Also, use the length info to avoid running tiny
  // strings through TextDecoder, since .subarray() allocates garbage.
  // (As a tiny code save trick, compare endPtr against endIdx using a negation,
  // so that undefined means Infinity)
  while (heapOrArray[endPtr] && !(endPtr >= endIdx)) ++endPtr;
  if (endPtr - idx > 16 && heapOrArray.buffer && UTF8Decoder) {
    return UTF8Decoder.decode(heapOrArray.subarray(idx, endPtr));
  }
  var str = '';
  // If building with TextDecoder, we have already computed the string length
  // above, so test loop end condition against that
  while (idx < endPtr) {
    // For UTF8 byte structure, see:
    // http://en.wikipedia.org/wiki/UTF-8#Description
    // https://www.ietf.org/rfc/rfc2279.txt
    // https://tools.ietf.org/html/rfc3629
    var u0 = heapOrArray[idx++];
    if (!(u0 & 0x80)) {
      str += String.fromCharCode(u0);
      continue;
    }
    var u1 = heapOrArray[idx++] & 63;
    if ((u0 & 0xE0) == 0xC0) {
      str += String.fromCharCode((u0 & 31) << 6 | u1);
      continue;
    }
    var u2 = heapOrArray[idx++] & 63;
    if ((u0 & 0xF0) == 0xE0) {
      u0 = (u0 & 15) << 12 | u1 << 6 | u2;
    } else {
      if ((u0 & 0xF8) != 0xF0) warnOnce('Invalid UTF-8 leading byte ' + ptrToString(u0) + ' encountered when deserializing a UTF-8 string in wasm memory to a JS string!');
      u0 = (u0 & 7) << 18 | u1 << 12 | u2 << 6 | heapOrArray[idx++] & 63;
    }
    if (u0 < 0x10000) {
      str += String.fromCharCode(u0);
    } else {
      var ch = u0 - 0x10000;
      str += String.fromCharCode(0xD800 | ch >> 10, 0xDC00 | ch & 0x3FF);
    }
  }
  return str;
};

/**
 * Given a pointer 'ptr' to a null-terminated UTF8-encoded string in the
 * emscripten HEAP, returns a copy of that string as a Javascript String object.
 *
 * @param {number} ptr
 * @param {number=} maxBytesToRead - An optional length that specifies the
 *   maximum number of bytes to read. You can omit this parameter to scan the
 *   string until the first 0 byte. If maxBytesToRead is passed, and the string
 *   at [ptr, ptr+maxBytesToReadr[ contains a null byte in the middle, then the
 *   string will cut short at that byte index (i.e. maxBytesToRead will not
 *   produce a string of exact length [ptr, ptr+maxBytesToRead[) N.B. mixing
 *   frequent uses of UTF8ToString() with and without maxBytesToRead may throw
 *   JS JIT optimizations off, so it is worth to consider consistently using one
 * @return {string}
 */
var UTF8ToString = (ptr, maxBytesToRead) => {
  assert(typeof ptr == 'number', `UTF8ToString expects a number (got ${typeof ptr})`);
  return ptr ? UTF8ArrayToString(HEAPU8, ptr, maxBytesToRead) : '';
};
var ___assert_fail = (condition, filename, line, func) => {
  abort(`Assertion failed: ${UTF8ToString(condition)}, at: ` + [filename ? UTF8ToString(filename) : 'unknown filename', line, func ? UTF8ToString(func) : 'unknown function']);
};
var __emscripten_memcpy_js = (dest, src, num) => HEAPU8.copyWithin(dest, src, src + num);
var getHeapMax = () =>
// Stay one Wasm page short of 4GB: while e.g. Chrome is able to allocate
// full 4GB Wasm memories, the size will wrap back to 0 bytes in Wasm side
// for any code that deals with heap sizes, which would require special
// casing all heap size related code to treat 0 specially.
2147483648;
var growMemory = size => {
  var b = wasmMemory.buffer;
  var pages = (size - b.byteLength + 65535) / 65536;
  try {
    // round size grow request up to wasm page size (fixed 64KB per spec)
    wasmMemory.grow(pages); // .grow() takes a delta compared to the previous size
    updateMemoryViews();
    return 1 /*success*/;
  } catch (e) {
    err(`growMemory: Attempted to grow heap from ${b.byteLength} bytes to ${size} bytes, but got error: ${e}`);
  }
  // implicit 0 return to save code size (caller will cast "undefined" into 0
  // anyhow)
};
var _emscripten_resize_heap = requestedSize => {
  var oldSize = HEAPU8.length;
  // With CAN_ADDRESS_2GB or MEMORY64, pointers are already unsigned.
  requestedSize >>>= 0;
  // With multithreaded builds, races can happen (another thread might increase the size
  // in between), so return a failure, and let the caller retry.
  assert(requestedSize > oldSize);

  // Memory resize rules:
  // 1.  Always increase heap size to at least the requested size, rounded up
  //     to next page multiple.
  // 2a. If MEMORY_GROWTH_LINEAR_STEP == -1, excessively resize the heap
  //     geometrically: increase the heap size according to
  //     MEMORY_GROWTH_GEOMETRIC_STEP factor (default +20%), At most
  //     overreserve by MEMORY_GROWTH_GEOMETRIC_CAP bytes (default 96MB).
  // 2b. If MEMORY_GROWTH_LINEAR_STEP != -1, excessively resize the heap
  //     linearly: increase the heap size by at least
  //     MEMORY_GROWTH_LINEAR_STEP bytes.
  // 3.  Max size for the heap is capped at 2048MB-WASM_PAGE_SIZE, or by
  //     MAXIMUM_MEMORY, or by ASAN limit, depending on which is smallest
  // 4.  If we were unable to allocate as much memory, it may be due to
  //     over-eager decision to excessively reserve due to (3) above.
  //     Hence if an allocation fails, cut down on the amount of excess
  //     growth, in an attempt to succeed to perform a smaller allocation.

  // A limit is set for how much we can grow. We should not exceed that
  // (the wasm binary specifies it, so if we tried, we'd fail anyhow).
  var maxHeapSize = getHeapMax();
  if (requestedSize > maxHeapSize) {
    err(`Cannot enlarge memory, requested ${requestedSize} bytes, but the limit is ${maxHeapSize} bytes!`);
    return false;
  }
  var alignUp = (x, multiple) => x + (multiple - x % multiple) % multiple;

  // Loop through potential heap size increases. If we attempt a too eager
  // reservation that fails, cut down on the attempted size and reserve a
  // smaller bump instead. (max 3 times, chosen somewhat arbitrarily)
  for (var cutDown = 1; cutDown <= 4; cutDown *= 2) {
    var overGrownHeapSize = oldSize * (1 + 0.2 / cutDown); // ensure geometric growth
    // but limit overreserving (default to capping at +96MB overgrowth at most)
    overGrownHeapSize = Math.min(overGrownHeapSize, requestedSize + 100663296);
    var newSize = Math.min(maxHeapSize, alignUp(Math.max(requestedSize, overGrownHeapSize), 65536));
    var replacement = growMemory(newSize);
    if (replacement) {
      return true;
    }
  }
  err(`Failed to grow the heap from ${oldSize} bytes to ${newSize} bytes, not enough memory!`);
  return false;
};
var ENV = {};
var getExecutableName = () => {
  return thisProgram || './this.program';
};
var getEnvStrings = () => {
  if (!getEnvStrings.strings) {
    // Default values.
    // Browser language detection #8751
    var lang = (typeof navigator == 'object' && navigator.languages && navigator.languages[0] || 'C').replace('-', '_') + '.UTF-8';
    var env = {
      'USER': 'web_user',
      'LOGNAME': 'web_user',
      'PATH': '/',
      'PWD': '/',
      'HOME': '/home/web_user',
      'LANG': lang,
      '_': getExecutableName()
    };
    // Apply the user-provided values, if any.
    for (var x in ENV) {
      // x is a key in ENV; if ENV[x] is undefined, that means it was
      // explicitly set to be so. We allow user code to do that to
      // force variables with default values to remain unset.
      if (ENV[x] === undefined) delete env[x];else env[x] = ENV[x];
    }
    var strings = [];
    for (var x in env) {
      strings.push(`${x}=${env[x]}`);
    }
    getEnvStrings.strings = strings;
  }
  return getEnvStrings.strings;
};
var stringToAscii = (str, buffer) => {
  for (var i = 0; i < str.length; ++i) {
    assert(str.charCodeAt(i) === (str.charCodeAt(i) & 0xff));
    HEAP8[buffer++] = str.charCodeAt(i);
  }
  // Null-terminate the string
  HEAP8[buffer] = 0;
};
var _environ_get = (__environ, environ_buf) => {
  var bufSize = 0;
  getEnvStrings().forEach((string, i) => {
    var ptr = environ_buf + bufSize;
    HEAPU32[__environ + i * 4 >> 2] = ptr;
    stringToAscii(string, ptr);
    bufSize += string.length + 1;
  });
  return 0;
};
var _environ_sizes_get = (penviron_count, penviron_buf_size) => {
  var strings = getEnvStrings();
  HEAPU32[penviron_count >> 2] = strings.length;
  var bufSize = 0;
  strings.forEach(string => bufSize += string.length + 1);
  HEAPU32[penviron_buf_size >> 2] = bufSize;
  return 0;
};
function _js_activate_timer() {
  timer_reference = performance.now();
  var frame = function (now) {
    timer = window.requestAnimationFrame(frame);
    // The callback might call js_deactivate_timer() below.
    timer_callback((now - timer_reference) / 1000.0);
    timer_reference = now;
  };
  timer = window.requestAnimationFrame(frame);
}
function _js_add_preset(menuid, ptr, value) {
  var name = UTF8ToString(ptr);
  var item = document.createElement("li");
  var label = document.createElement("label");
  var tick = document.createElement("input");
  tick.type = "radio";
  tick.className = "tick";
  tick.name = "preset";
  tick.value = value;
  label.appendChild(tick);
  label.appendChild(document.createTextNode(" " + name));
  item.appendChild(label);
  gametypesubmenus[menuid].appendChild(item);
  tick.onclick = function (event) {
    if (dlg_dimmer === null) {
      command(2);
    }
  };
}
function _js_add_preset_submenu(menuid, ptr, value) {
  var name = UTF8ToString(ptr);
  var item = document.createElement("li");
  // We still create a transparent tick element, even though it
  // won't ever be selected, to make submenu titles line up
  // nicely with their neighbours.
  var label = document.createElement("div");
  var tick = document.createElement("span");
  tick.className = "tick";
  label.appendChild(tick);
  label.tabIndex = 0;
  label.appendChild(document.createTextNode(" " + name));
  item.appendChild(label);
  var submenu = document.createElement("ul");
  label.appendChild(submenu);
  gametypesubmenus[menuid].appendChild(item);
  var toret = gametypesubmenus.length;
  gametypesubmenus.push(submenu);
  return toret;
}
function _js_canvas_blitter_load(dr, bl, x, y) {
  // Temporarily turn off the effects of fe_scale so we can do
  // it ourselves.
  ctx.save();
  ctx.resetTransform();
  x *= fe_scale;
  y *= fe_scale;
  ctx.drawImage(blitters.get(bl), x, y);
  ctx.restore();
}
function _js_canvas_blitter_save(dr, bl, x, y) {
  var blitter_ctx = blitters.get(bl).getContext('2d', {
    alpha: false
  });
  x *= fe_scale;
  y *= fe_scale;
  blitter_ctx.drawImage(offscreen_canvas, -x, -y);
}
function _js_canvas_clip(dr, x, y, w, h) {
  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, w, h);
  ctx.clip();
}
function _js_canvas_draw_circle(dr, x, y, r, fill, outline) {
  ctx.beginPath();
  ctx.arc(x + 0.5, y + 0.5, r, 0, 2 * Math.PI);
  if (fill >= 0) {
    ctx.fillStyle = colours[fill];
    ctx.fill();
  }
  ctx.lineWidth = '1';
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = colours[outline];
  ctx.stroke();
}
function _js_canvas_draw_line(x1, y1, x2, y2, width, colour) {
  colour = colours[colour];
  ctx.beginPath();
  ctx.moveTo(x1 + 0.5, y1 + 0.5);
  ctx.lineTo(x2 + 0.5, y2 + 0.5);
  ctx.lineWidth = width;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = colour;
  ctx.stroke();
  ctx.fillStyle = colour;
  ctx.fillRect(x1, y1, 1, 1);
  ctx.fillRect(x2, y2, 1, 1);
}
function _js_canvas_draw_poly(dr, pointptr, npoints, fill, outline) {
  ctx.beginPath();
  ctx.moveTo(getValue(pointptr, 'i32') + 0.5, getValue(pointptr + 4, 'i32') + 0.5);
  for (var i = 1; i < npoints; i++) ctx.lineTo(getValue(pointptr + 8 * i, 'i32') + 0.5, getValue(pointptr + 8 * i + 4, 'i32') + 0.5);
  ctx.closePath();
  if (fill >= 0) {
    ctx.fillStyle = colours[fill];
    ctx.fill();
  }
  ctx.lineWidth = '1';
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = colours[outline];
  ctx.stroke();
}
function _js_canvas_draw_rect(dr, x, y, w, h, colour) {
  ctx.fillStyle = colours[colour];
  ctx.fillRect(x, y, w, h);
}
function _js_canvas_draw_text(x, y, halign, colour, fontsize, monospaced, text) {
  canvas_set_font(ctx, fontsize, monospaced);
  ctx.fillStyle = colours[colour];
  ctx.textAlign = halign == 0 ? 'left' : halign == 1 ? 'center' : 'right';
  ctx.textBaseline = 'alphabetic';
  ctx.fillText(UTF8ToString(text), x, y);
}
function _js_canvas_draw_update(dr, x, y, w, h) {
  /*
   * Currently we do this in a really simple way, just by taking
   * the smallest rectangle containing all updates so far. We
   * could instead keep the data in a richer form (e.g. retain
   * multiple smaller rectangles needing update, and only redraw
   * the whole thing beyond a certain threshold) but this will
   * do for now.
   */
  x *= fe_scale;
  y *= fe_scale;
  w *= fe_scale;
  h *= fe_scale;
  if (update_xmin === undefined || update_xmin > x) update_xmin = x;
  if (update_ymin === undefined || update_ymin > y) update_ymin = y;
  if (update_xmax === undefined || update_xmax < x + w) update_xmax = x + w;
  if (update_ymax === undefined || update_ymax < y + h) update_ymax = y + h;
}
function _js_canvas_end_draw(dr) {
  if (update_xmin !== undefined) {
    var onscreen_ctx = onscreen_canvas.getContext('2d', {
      alpha: false
    });
    onscreen_ctx.drawImage(offscreen_canvas, update_xmin, update_ymin, update_xmax - update_xmin, update_ymax - update_ymin, update_xmin, update_ymin, update_xmax - update_xmin, update_ymax - update_ymin);
  }
}
function _js_canvas_find_font_midpoint(height, monospaced) {
  if (height == 0) {
    // Handle this degenerate case by hand. Otherwise we end
    // up passing height=0 to the getImageData call below,
    // causing browsers to report errors.
    return 0;
  }

  // Resolve the font into a string.
  var ctx1 = onscreen_canvas.getContext('2d', {
    alpha: false
  });
  canvas_set_font(ctx1, height, monospaced);

  // Reuse cached value if possible
  if (midpoint_cache[ctx1.font] !== undefined) return midpoint_cache[ctx1.font];

  // Find the width of the string
  var width = ctx1.measureText(midpoint_test_str).width + 1 | 0;

  // Construct a test canvas of appropriate size, initialise it to
  // black, and draw the string on it in white
  var measure_canvas = document.createElement('canvas');
  var ctx2 = measure_canvas.getContext('2d', {
    alpha: false
  });
  ctx2.canvas.width = width;
  ctx2.canvas.height = 2 * height;
  ctx2.fillStyle = "#000000";
  ctx2.fillRect(0, 0, width, 2 * height);
  var baseline = 1.5 * height | 0;
  ctx2.fillStyle = "#ffffff";
  canvas_set_font(ctx2, height, monospaced);
  ctx2.fillText(midpoint_test_str, 0, baseline);

  // Scan the contents of the test canvas to find the top and bottom
  // set pixels.
  var pixels = ctx2.getImageData(0, 0, width, 2 * height).data;
  var ymin = 2 * height,
    ymax = -1;
  for (var y = 0; y < 2 * height; y++) {
    for (var x = 0; x < width; x++) {
      if (pixels[4 * (y * width + x)] != 0) {
        if (ymin > y) ymin = y;
        if (ymax < y) ymax = y;
        break;
      }
    }
  }
  var ret = baseline - (ymin + ymax) / 2 | 0;
  midpoint_cache[ctx1.font] = ret;
  return ret;
}
function _js_canvas_free_blitter(bl) {
  blitters.delete(bl);
}
function _js_canvas_get_preferred_size(wp, hp) {
  if (containing_div !== null) {
    var dpr = window.devicePixelRatio || 1;
    setValue(wp, containing_div.clientWidth * dpr, "i32");
    setValue(hp, containing_div.clientHeight * dpr, "i32");
    return true;
  }
  return false;
}
function _js_canvas_new_blitter(bl, w, h) {
  w *= fe_scale;
  h *= fe_scale;
  var new_blitter = document.createElement("canvas");
  new_blitter.width = w;
  new_blitter.height = h;
  blitters.set(bl, new_blitter);
}
function _js_canvas_remove_statusbar() {
  if (statusbar !== null) statusbar.parentNode.removeChild(statusbar);
  statusbar = null;
}
function _js_canvas_set_size(w, h, new_fe_scale) {
  onscreen_canvas.width = w;
  offscreen_canvas.width = w;
  if (resizable_div !== null) resizable_div.style.width = w / (window.devicePixelRatio || 1) + "px";else {
    onscreen_canvas.style.width = w / (window.devicePixelRatio || 1) + "px";
    onscreen_canvas.style.height = h / (window.devicePixelRatio || 1) + "px";
  }
  onscreen_canvas.height = h;
  offscreen_canvas.height = h;
  fe_scale = new_fe_scale;
  ctx.resetTransform();
  ctx.scale(fe_scale, fe_scale);
}
function _js_canvas_start_draw(dr) {
  update_xmin = update_xmax = update_ymin = update_ymax = undefined;
}
function _js_canvas_status_bar(dr, ptr) {
  statusbar.textContent = UTF8ToString(ptr);
}
function _js_canvas_unclip(dr) {
  ctx.restore();
}
function _js_deactivate_timer() {
  window.cancelAnimationFrame(timer);
}
function _js_default_colour(output) {
  var col = window.getComputedStyle(onscreen_canvas).backgroundColor;
  /* We only support opaque sRGB colours. */
  var m = col.match(/^rgb\((\d+(?:\.\d+)?), (\d+(?:\.\d+)?), (\d+(?:\.\d+)?)\)$/);
  if (m) {
    setValue(output, +m[1] / 255, "float");
    setValue(output + 4, +m[2] / 255, "float");
    setValue(output + 8, +m[3] / 255, "float");
  }
}
function _js_dialog_boolean(index, title, initvalue) {
  var checkbox = document.createElement("input");
  checkbox.type = "checkbox";
  checkbox.checked = initvalue != 0;
  var checkboxlabel = document.createElement("label");
  checkboxlabel.appendChild(checkbox);
  checkboxlabel.appendChild(document.createTextNode(UTF8ToString(title)));
  dlg_form.appendChild(checkboxlabel);
  dlg_form.appendChild(document.createElement("br"));
  dlg_return_funcs.push(function () {
    dlg_return_ival(index, checkbox.checked ? 1 : 0);
  });
}
function _js_dialog_choices(index, title, choicelist, initvalue) {
  var label = document.createElement("label");
  label.textContent = UTF8ToString(title);
  dlg_form.appendChild(label);
  var dropdown = document.createElement("select");
  var choicestr = UTF8ToString(choicelist);
  var items = choicestr.slice(1).split(choicestr[0]);
  var options = [];
  for (var i in items) {
    var option = document.createElement("option");
    option.value = i;
    option.appendChild(document.createTextNode(items[i]));
    if (i == initvalue) option.selected = true;
    dropdown.appendChild(option);
    options.push(option);
  }
  label.appendChild(dropdown);
  dlg_form.appendChild(document.createElement("br"));
  dlg_return_funcs.push(function () {
    var val = 0;
    for (var i in options) {
      if (options[i].selected) {
        val = options[i].value;
        break;
      }
    }
    dlg_return_ival(index, val);
  });
}
function _js_dialog_cleanup() {
  dialog_cleanup();
}
function _js_dialog_init(titletext) {
  dialog_init(UTF8ToString(titletext));
}
function _js_dialog_launch() {
  dialog_launch(function (event) {
    for (var i in dlg_return_funcs) dlg_return_funcs[i]();
    command(3); // OK
  }, function (event) {
    command(4); // Cancel
  });
}
function _js_dialog_string(index, title, initialtext) {
  var label = document.createElement("label");
  label.textContent = UTF8ToString(title);
  dlg_form.appendChild(label);
  var editbox = document.createElement("input");
  editbox.type = "text";
  editbox.value = UTF8ToString(initialtext);
  label.appendChild(editbox);
  dlg_form.appendChild(document.createElement("br"));
  dlg_return_funcs.push(function () {
    dlg_return_sval(index, editbox.value);
  });
}
function _js_enable_undo_redo(undo, redo) {
  disable_menu_item(undo_button, undo == 0);
  disable_menu_item(redo_button, redo == 0);
}
function _js_error_box(ptr) {
  alert(UTF8ToString(ptr));
}
function _js_focus_canvas() {
  onscreen_canvas.focus();
}
function _js_get_date_64(ptr) {
  var d = new Date().valueOf();
  setValue(ptr, d, 'i64');
}
function _js_get_device_pixel_ratio() {
  return window.devicePixelRatio || 1;
}
function _js_get_selected_preset() {
  return menuform.elements["preset"].value;
}
function _js_init_puzzle() {
  initPuzzle();
}
function _js_load_prefs(me) {
  function load_prefs_from_string(prefsdata) {
    if (prefsdata !== undefined && prefsdata !== null) {
      var lenbytes = lengthBytesUTF8(prefsdata) + 1;
      var dest = _malloc(lenbytes);
      if (dest != 0) {
        stringToUTF8(prefsdata, dest, lenbytes);
        prefs_load_callback(me, dest);
        _free(dest);
      }
    }
  }
  // Load any default preferences embedded in HTML.
  for (var prefsscript of document.querySelectorAll("script.preferences")) load_prefs_from_string(prefsscript.textContent);
  // Load saved preferences if they exist.
  try {
    load_prefs_from_string(localStorage.getItem(location.pathname + " preferences"));
  } catch (error) {
    // Log the error but otherwise pretend the settings were
    // absent.
    console.warn(error);
  }
}
function _js_post_init() {
  post_init();
}
function _js_remove_solve_button() {
  if (solve_button === null) return;
  var solve_item = solve_button.closest("li");
  if (solve_item === null) return;
  solve_item.parentNode.removeChild(solve_item);
  solve_button = null;
}
function _js_remove_type_dropdown() {
  if (gametypelist === null) return;
  var gametypeitem = gametypelist.closest("li");
  if (gametypeitem === null) return;
  gametypeitem.parentNode.removeChild(gametypeitem);
  gametypelist = null;
}
function _js_save_prefs(buf) {
  var prefsdata = UTF8ToString(buf);
  try {
    localStorage.setItem(location.pathname + " preferences", prefsdata);
  } catch (error) {
    // Tell the user their preferences have not been saved.
    console.error(error);
    alert("Saving of preferences failed: " + error.message);
  }
}
function _js_savefile_read(buf, len) {
  return savefile_read_callback(buf, len);
}
function _js_select_preset(n) {
  menuform.elements["preset"].value = n;
}
function _js_set_colour(colour_number, colour_string) {
  colours[colour_number] = UTF8ToString(colour_string);
  if (colour_number == 0) document.documentElement.style.setProperty("--puzzle-background", colours[colour_number]);
}
function _js_update_key_labels(lsk_ptr, csk_ptr) {
  var elem;
  var lsk_text = UTF8ToString(lsk_ptr);
  var csk_text = UTF8ToString(csk_ptr);
  for (elem of document.querySelectorAll("#puzzle .lsk")) elem.textContent = lsk_text == csk_text ? "" : lsk_text;
  for (elem of document.querySelectorAll("#puzzle .csk")) elem.textContent = csk_text;
}
function _js_update_permalinks(desc, seed) {
  desc = encodeURI(UTF8ToString(desc)).replace(/#/g, "%23");
  if (permalink_desc !== null) permalink_desc.href = "#" + desc;
  if (permalink_seed !== null) {
    if (seed == 0) {
      permalink_seed.style.display = "none";
    } else {
      seed = encodeURI(UTF8ToString(seed)).replace(/#/g, "%23");
      ;
      permalink_seed.href = "#" + seed;
      permalink_seed.style.display = "";
    }
  }
}
var runtimeKeepaliveCounter = 0;
var keepRuntimeAlive = () => noExitRuntime || runtimeKeepaliveCounter > 0;
var _proc_exit = code => {
  EXITSTATUS = code;
  if (!keepRuntimeAlive()) {
    var _Module$onExit, _Module4;
    (_Module$onExit = (_Module4 = Module)['onExit']) === null || _Module$onExit === void 0 || _Module$onExit.call(_Module4, code);
    ABORT = true;
  }
  quit_(code, new ExitStatus(code));
};

/** @param {boolean|number=} implicit */
var exitJS = (status, implicit) => {
  EXITSTATUS = status;
  checkUnflushedContent();

  // if exit() was called explicitly, warn the user if the runtime isn't actually being shut down
  if (keepRuntimeAlive() && !implicit) {
    var msg = `program exited (with status: ${status}), but keepRuntimeAlive() is set (counter=${runtimeKeepaliveCounter}) due to an async operation, so halting execution but not exiting the runtime or preventing further async execution (you can use emscripten_force_exit, if you want to force a true shutdown)`;
    err(msg);
  }
  _proc_exit(status);
};
var handleException = e => {
  // Certain exception types we do not treat as errors since they are used for
  // internal control flow.
  // 1. ExitStatus, which is thrown by exit()
  // 2. "unwind", which is thrown by emscripten_unwind_to_js_event_loop() and others
  //    that wish to return to JS event loop.
  if (e instanceof ExitStatus || e == 'unwind') {
    return EXITSTATUS;
  }
  checkStackCookie();
  if (e instanceof WebAssembly.RuntimeError) {
    if (_emscripten_stack_get_current() <= 0) {
      err('Stack overflow detected.  You can try increasing -sSTACK_SIZE (currently set to 65536)');
    }
  }
  quit_(1, e);
};
var lengthBytesUTF8 = str => {
  var len = 0;
  for (var i = 0; i < str.length; ++i) {
    // Gotcha: charCodeAt returns a 16-bit word that is a UTF-16 encoded code
    // unit, not a Unicode code point of the character! So decode
    // UTF16->UTF32->UTF8.
    // See http://unicode.org/faq/utf_bom.html#utf16-3
    var c = str.charCodeAt(i); // possibly a lead surrogate
    if (c <= 0x7F) {
      len++;
    } else if (c <= 0x7FF) {
      len += 2;
    } else if (c >= 0xD800 && c <= 0xDFFF) {
      len += 4;
      ++i;
    } else {
      len += 3;
    }
  }
  return len;
};
var stringToUTF8Array = (str, heap, outIdx, maxBytesToWrite) => {
  assert(typeof str === 'string', `stringToUTF8Array expects a string (got ${typeof str})`);
  // Parameter maxBytesToWrite is not optional. Negative values, 0, null,
  // undefined and false each don't write out any bytes.
  if (!(maxBytesToWrite > 0)) return 0;
  var startIdx = outIdx;
  var endIdx = outIdx + maxBytesToWrite - 1; // -1 for string null terminator.
  for (var i = 0; i < str.length; ++i) {
    // Gotcha: charCodeAt returns a 16-bit word that is a UTF-16 encoded code
    // unit, not a Unicode code point of the character! So decode
    // UTF16->UTF32->UTF8.
    // See http://unicode.org/faq/utf_bom.html#utf16-3
    // For UTF8 byte structure, see http://en.wikipedia.org/wiki/UTF-8#Description
    // and https://www.ietf.org/rfc/rfc2279.txt
    // and https://tools.ietf.org/html/rfc3629
    var u = str.charCodeAt(i); // possibly a lead surrogate
    if (u >= 0xD800 && u <= 0xDFFF) {
      var u1 = str.charCodeAt(++i);
      u = 0x10000 + ((u & 0x3FF) << 10) | u1 & 0x3FF;
    }
    if (u <= 0x7F) {
      if (outIdx >= endIdx) break;
      heap[outIdx++] = u;
    } else if (u <= 0x7FF) {
      if (outIdx + 1 >= endIdx) break;
      heap[outIdx++] = 0xC0 | u >> 6;
      heap[outIdx++] = 0x80 | u & 63;
    } else if (u <= 0xFFFF) {
      if (outIdx + 2 >= endIdx) break;
      heap[outIdx++] = 0xE0 | u >> 12;
      heap[outIdx++] = 0x80 | u >> 6 & 63;
      heap[outIdx++] = 0x80 | u & 63;
    } else {
      if (outIdx + 3 >= endIdx) break;
      if (u > 0x10FFFF) warnOnce('Invalid Unicode code point ' + ptrToString(u) + ' encountered when serializing a JS string to a UTF-8 string in wasm memory! (Valid unicode code points should be in range 0-0x10FFFF).');
      heap[outIdx++] = 0xF0 | u >> 18;
      heap[outIdx++] = 0x80 | u >> 12 & 63;
      heap[outIdx++] = 0x80 | u >> 6 & 63;
      heap[outIdx++] = 0x80 | u & 63;
    }
  }
  // Null-terminate the pointer to the buffer.
  heap[outIdx] = 0;
  return outIdx - startIdx;
};
var stringToUTF8 = (str, outPtr, maxBytesToWrite) => {
  assert(typeof maxBytesToWrite == 'number', 'stringToUTF8(str, outPtr, maxBytesToWrite) is missing the third parameter that specifies the length of the output buffer!');
  return stringToUTF8Array(str, HEAPU8, outPtr, maxBytesToWrite);
};
var stackAlloc = sz => __emscripten_stack_alloc(sz);
var stringToUTF8OnStack = str => {
  var size = lengthBytesUTF8(str) + 1;
  var ret = stackAlloc(size);
  stringToUTF8(str, ret, size);
  return ret;
};
var getCFunc = ident => {
  var func = Module['_' + ident]; // closure exported function
  assert(func, 'Cannot call unknown function ' + ident + ', make sure it is exported');
  return func;
};
var writeArrayToMemory = (array, buffer) => {
  assert(array.length >= 0, 'writeArrayToMemory array must have a length (should be an array or typed array)');
  HEAP8.set(array, buffer);
};

/**
 * @param {string|null=} returnType
 * @param {Array=} argTypes
 * @param {Arguments|Array=} args
 * @param {Object=} opts
 */
var ccall = (ident, returnType, argTypes, args, opts) => {
  // For fast lookup of conversion functions
  var toC = {
    'string': str => {
      var ret = 0;
      if (str !== null && str !== undefined && str !== 0) {
        // null string
        // at most 4 bytes per UTF-8 code point, +1 for the trailing '\0'
        ret = stringToUTF8OnStack(str);
      }
      return ret;
    },
    'array': arr => {
      var ret = stackAlloc(arr.length);
      writeArrayToMemory(arr, ret);
      return ret;
    }
  };
  function convertReturnValue(ret) {
    if (returnType === 'string') {
      return UTF8ToString(ret);
    }
    if (returnType === 'boolean') return Boolean(ret);
    return ret;
  }
  var func = getCFunc(ident);
  var cArgs = [];
  var stack = 0;
  assert(returnType !== 'array', 'Return type should not be "array".');
  if (args) {
    for (var i = 0; i < args.length; i++) {
      var converter = toC[argTypes[i]];
      if (converter) {
        if (stack === 0) stack = stackSave();
        cArgs[i] = converter(args[i]);
      } else {
        cArgs[i] = args[i];
      }
    }
  }
  var ret = func(...cArgs);
  function onDone(ret) {
    if (stack !== 0) stackRestore(stack);
    return convertReturnValue(ret);
  }
  ret = onDone(ret);
  return ret;
};

/**
 * @param {string=} returnType
 * @param {Array=} argTypes
 * @param {Object=} opts
 */
var cwrap = (ident, returnType, argTypes, opts) => {
  return function () {
    for (var _len = arguments.length, args = new Array(_len), _key2 = 0; _key2 < _len; _key2++) {
      args[_key2] = arguments[_key2];
    }
    return ccall(ident, returnType, argTypes, args, opts);
  };
};
function checkIncomingModuleAPI() {
  ignoredModuleProp('fetchSettings');
}
var wasmImports = {
  /** @export */
  __assert_fail: ___assert_fail,
  /** @export */
  _emscripten_memcpy_js: __emscripten_memcpy_js,
  /** @export */
  emscripten_resize_heap: _emscripten_resize_heap,
  /** @export */
  environ_get: _environ_get,
  /** @export */
  environ_sizes_get: _environ_sizes_get,
  /** @export */
  js_activate_timer: _js_activate_timer,
  /** @export */
  js_add_preset: _js_add_preset,
  /** @export */
  js_add_preset_submenu: _js_add_preset_submenu,
  /** @export */
  js_canvas_blitter_load: _js_canvas_blitter_load,
  /** @export */
  js_canvas_blitter_save: _js_canvas_blitter_save,
  /** @export */
  js_canvas_clip: _js_canvas_clip,
  /** @export */
  js_canvas_draw_circle: _js_canvas_draw_circle,
  /** @export */
  js_canvas_draw_line: _js_canvas_draw_line,
  /** @export */
  js_canvas_draw_poly: _js_canvas_draw_poly,
  /** @export */
  js_canvas_draw_rect: _js_canvas_draw_rect,
  /** @export */
  js_canvas_draw_text: _js_canvas_draw_text,
  /** @export */
  js_canvas_draw_update: _js_canvas_draw_update,
  /** @export */
  js_canvas_end_draw: _js_canvas_end_draw,
  /** @export */
  js_canvas_find_font_midpoint: _js_canvas_find_font_midpoint,
  /** @export */
  js_canvas_free_blitter: _js_canvas_free_blitter,
  /** @export */
  js_canvas_get_preferred_size: _js_canvas_get_preferred_size,
  /** @export */
  js_canvas_new_blitter: _js_canvas_new_blitter,
  /** @export */
  js_canvas_remove_statusbar: _js_canvas_remove_statusbar,
  /** @export */
  js_canvas_set_size: _js_canvas_set_size,
  /** @export */
  js_canvas_start_draw: _js_canvas_start_draw,
  /** @export */
  js_canvas_status_bar: _js_canvas_status_bar,
  /** @export */
  js_canvas_unclip: _js_canvas_unclip,
  /** @export */
  js_deactivate_timer: _js_deactivate_timer,
  /** @export */
  js_default_colour: _js_default_colour,
  /** @export */
  js_dialog_boolean: _js_dialog_boolean,
  /** @export */
  js_dialog_choices: _js_dialog_choices,
  /** @export */
  js_dialog_cleanup: _js_dialog_cleanup,
  /** @export */
  js_dialog_init: _js_dialog_init,
  /** @export */
  js_dialog_launch: _js_dialog_launch,
  /** @export */
  js_dialog_string: _js_dialog_string,
  /** @export */
  js_enable_undo_redo: _js_enable_undo_redo,
  /** @export */
  js_error_box: _js_error_box,
  /** @export */
  js_focus_canvas: _js_focus_canvas,
  /** @export */
  js_get_date_64: _js_get_date_64,
  /** @export */
  js_get_device_pixel_ratio: _js_get_device_pixel_ratio,
  /** @export */
  js_get_selected_preset: _js_get_selected_preset,
  /** @export */
  js_init_puzzle: _js_init_puzzle,
  /** @export */
  js_load_prefs: _js_load_prefs,
  /** @export */
  js_post_init: _js_post_init,
  /** @export */
  js_remove_solve_button: _js_remove_solve_button,
  /** @export */
  js_remove_type_dropdown: _js_remove_type_dropdown,
  /** @export */
  js_save_prefs: _js_save_prefs,
  /** @export */
  js_savefile_read: _js_savefile_read,
  /** @export */
  js_select_preset: _js_select_preset,
  /** @export */
  js_set_colour: _js_set_colour,
  /** @export */
  js_update_key_labels: _js_update_key_labels,
  /** @export */
  js_update_permalinks: _js_update_permalinks
};
var wasmExports = createWasm();
var ___wasm_call_ctors = createExportWrapper('__wasm_call_ctors', 0);
var _malloc = Module['_malloc'] = createExportWrapper('malloc', 1);
var _free = Module['_free'] = createExportWrapper('free', 1);
var _timer_callback = Module['_timer_callback'] = createExportWrapper('timer_callback', 1);
var _rescale_puzzle = Module['_rescale_puzzle'] = createExportWrapper('rescale_puzzle', 0);
var _resize_puzzle = Module['_resize_puzzle'] = createExportWrapper('resize_puzzle', 2);
var _restore_puzzle_size = Module['_restore_puzzle_size'] = createExportWrapper('restore_puzzle_size', 2);
var _mousedown = Module['_mousedown'] = createExportWrapper('mousedown', 3);
var _mouseup = Module['_mouseup'] = createExportWrapper('mouseup', 3);
var _mousemove = Module['_mousemove'] = createExportWrapper('mousemove', 3);
var _key = Module['_key'] = createExportWrapper('key', 6);
var _dlg_return_sval = Module['_dlg_return_sval'] = createExportWrapper('dlg_return_sval', 2);
var _dlg_return_ival = Module['_dlg_return_ival'] = createExportWrapper('dlg_return_ival', 2);
var _command = Module['_command'] = createExportWrapper('command', 1);
var _get_text_format = Module['_get_text_format'] = createExportWrapper('get_text_format', 0);
var _free_text_format = Module['_free_text_format'] = createExportWrapper('free_text_format', 1);
var _get_save_file = Module['_get_save_file'] = createExportWrapper('get_save_file', 0);
var _free_save_file = Module['_free_save_file'] = createExportWrapper('free_save_file', 1);
var _load_game = Module['_load_game'] = createExportWrapper('load_game', 0);
var _prefs_load_callback = Module['_prefs_load_callback'] = createExportWrapper('prefs_load_callback', 2);
var _main = Module['_main'] = createExportWrapper('__main_argc_argv', 2);
var _fflush = createExportWrapper('fflush', 1);
var _emscripten_stack_init = () => (_emscripten_stack_init = wasmExports['emscripten_stack_init'])();
var _emscripten_stack_get_free = () => (_emscripten_stack_get_free = wasmExports['emscripten_stack_get_free'])();
var _emscripten_stack_get_base = () => (_emscripten_stack_get_base = wasmExports['emscripten_stack_get_base'])();
var _emscripten_stack_get_end = () => (_emscripten_stack_get_end = wasmExports['emscripten_stack_get_end'])();
var __emscripten_stack_restore = a0 => (__emscripten_stack_restore = wasmExports['_emscripten_stack_restore'])(a0);
var __emscripten_stack_alloc = a0 => (__emscripten_stack_alloc = wasmExports['_emscripten_stack_alloc'])(a0);
var _emscripten_stack_get_current = () => (_emscripten_stack_get_current = wasmExports['emscripten_stack_get_current'])();

// include: postamble.js
// === Auto-generated postamble setup entry stuff ===

Module['cwrap'] = cwrap;
var missingLibrarySymbols = ['writeI53ToI64', 'writeI53ToI64Clamped', 'writeI53ToI64Signaling', 'writeI53ToU64Clamped', 'writeI53ToU64Signaling', 'readI53FromI64', 'readI53FromU64', 'convertI32PairToI53', 'convertI32PairToI53Checked', 'convertU32PairToI53', 'bigintToI53Checked', 'getTempRet0', 'setTempRet0', 'zeroMemory', 'isLeapYear', 'ydayFromDate', 'arraySum', 'addDays', 'strError', 'inetPton4', 'inetNtop4', 'inetPton6', 'inetNtop6', 'readSockaddr', 'writeSockaddr', 'initRandomFill', 'randomFill', 'emscriptenLog', 'readEmAsmArgs', 'jstoi_q', 'listenOnce', 'autoResumeAudioContext', 'getDynCaller', 'dynCall', 'runtimeKeepalivePush', 'runtimeKeepalivePop', 'callUserCallback', 'maybeExit', 'asmjsMangle', 'asyncLoad', 'alignMemory', 'mmapAlloc', 'HandleAllocator', 'getNativeTypeSize', 'STACK_SIZE', 'STACK_ALIGN', 'POINTER_SIZE', 'ASSERTIONS', 'uleb128Encode', 'sigToWasmTypes', 'generateFuncType', 'convertJsFunctionToWasm', 'getEmptyTableSlot', 'updateTableMap', 'getFunctionAddress', 'addFunction', 'removeFunction', 'reallyNegative', 'unSign', 'strLen', 'reSign', 'formatString', 'intArrayFromString', 'intArrayToString', 'AsciiToString', 'UTF16ToString', 'stringToUTF16', 'lengthBytesUTF16', 'UTF32ToString', 'stringToUTF32', 'lengthBytesUTF32', 'stringToNewUTF8', 'registerKeyEventCallback', 'maybeCStringToJsString', 'findEventTarget', 'getBoundingClientRect', 'fillMouseEventData', 'registerMouseEventCallback', 'registerWheelEventCallback', 'registerUiEventCallback', 'registerFocusEventCallback', 'fillDeviceOrientationEventData', 'registerDeviceOrientationEventCallback', 'fillDeviceMotionEventData', 'registerDeviceMotionEventCallback', 'screenOrientation', 'fillOrientationChangeEventData', 'registerOrientationChangeEventCallback', 'fillFullscreenChangeEventData', 'registerFullscreenChangeEventCallback', 'JSEvents_requestFullscreen', 'JSEvents_resizeCanvasForFullscreen', 'registerRestoreOldStyle', 'hideEverythingExceptGivenElement', 'restoreHiddenElements', 'setLetterbox', 'softFullscreenResizeWebGLRenderTarget', 'doRequestFullscreen', 'fillPointerlockChangeEventData', 'registerPointerlockChangeEventCallback', 'registerPointerlockErrorEventCallback', 'requestPointerLock', 'fillVisibilityChangeEventData', 'registerVisibilityChangeEventCallback', 'registerTouchEventCallback', 'fillGamepadEventData', 'registerGamepadEventCallback', 'registerBeforeUnloadEventCallback', 'fillBatteryEventData', 'battery', 'registerBatteryEventCallback', 'setCanvasElementSize', 'getCanvasElementSize', 'jsStackTrace', 'getCallstack', 'convertPCtoSourceLocation', 'checkWasiClock', 'flush_NO_FILESYSTEM', 'wasiRightsToMuslOFlags', 'wasiOFlagsToMuslOFlags', 'createDyncallWrapper', 'safeSetTimeout', 'setImmediateWrapped', 'clearImmediateWrapped', 'polyfillSetImmediate', 'getPromise', 'makePromise', 'idsToPromises', 'makePromiseCallback', 'ExceptionInfo', 'findMatchingCatch', 'Browser_asyncPrepareDataCounter', 'setMainLoop', 'getSocketFromFD', 'getSocketAddress', 'FS_createPreloadedFile', 'FS_modeStringToFlags', 'FS_getMode', 'FS_stdin_getChar', 'FS_unlink', 'FS_createDataFile', 'FS_mkdirTree', '_setNetworkCallback', 'heapObjectForWebGLType', 'toTypedArrayIndex', 'webgl_enable_ANGLE_instanced_arrays', 'webgl_enable_OES_vertex_array_object', 'webgl_enable_WEBGL_draw_buffers', 'webgl_enable_WEBGL_multi_draw', 'emscriptenWebGLGet', 'computeUnpackAlignedImageSize', 'colorChannelsInGlTextureFormat', 'emscriptenWebGLGetTexPixelData', 'emscriptenWebGLGetUniform', 'webglGetUniformLocation', 'webglPrepareUniformLocationsBeforeFirstUse', 'webglGetLeftBracePos', 'emscriptenWebGLGetVertexAttrib', '__glGetActiveAttribOrUniform', 'writeGLArray', 'registerWebGlEventCallback', 'runAndAbortIfError', 'ALLOC_NORMAL', 'ALLOC_STACK', 'allocate', 'writeStringToMemory', 'writeAsciiToMemory', 'setErrNo', 'demangle', 'stackTrace'];
missingLibrarySymbols.forEach(missingLibrarySymbol);
var unexportedSymbols = ['run', 'addOnPreRun', 'addOnInit', 'addOnPreMain', 'addOnExit', 'addOnPostRun', 'addRunDependency', 'removeRunDependency', 'out', 'err', 'callMain', 'abort', 'wasmMemory', 'wasmExports', 'writeStackCookie', 'checkStackCookie', 'INT53_MAX', 'INT53_MIN', 'stackSave', 'stackRestore', 'stackAlloc', 'ptrToString', 'exitJS', 'getHeapMax', 'growMemory', 'ENV', 'MONTH_DAYS_REGULAR', 'MONTH_DAYS_LEAP', 'MONTH_DAYS_REGULAR_CUMULATIVE', 'MONTH_DAYS_LEAP_CUMULATIVE', 'ERRNO_CODES', 'DNS', 'Protocols', 'Sockets', 'timers', 'warnOnce', 'readEmAsmArgsArray', 'jstoi_s', 'getExecutableName', 'handleException', 'keepRuntimeAlive', 'wasmTable', 'noExitRuntime', 'getCFunc', 'ccall', 'freeTableIndexes', 'functionsInTableMap', 'setValue', 'getValue', 'PATH', 'PATH_FS', 'UTF8Decoder', 'UTF8ArrayToString', 'UTF8ToString', 'stringToUTF8Array', 'stringToUTF8', 'lengthBytesUTF8', 'stringToAscii', 'UTF16Decoder', 'stringToUTF8OnStack', 'writeArrayToMemory', 'JSEvents', 'specialHTMLTargets', 'findCanvasEventTarget', 'currentFullscreenStrategy', 'restoreOldWindowedStyle', 'UNWIND_CACHE', 'ExitStatus', 'getEnvStrings', 'promiseMap', 'uncaughtExceptionCount', 'exceptionLast', 'exceptionCaught', 'Browser', 'getPreloadedImageData__data', 'wget', 'SYSCALLS', 'preloadPlugins', 'FS_stdin_getChar_buffer', 'FS_createPath', 'FS_createDevice', 'FS_readFile', 'FS', 'FS_createLazyFile', 'MEMFS', 'TTY', 'PIPEFS', 'SOCKFS', 'tempFixedLengthArray', 'miniTempWebGLFloatBuffers', 'miniTempWebGLIntBuffers', 'GL', 'AL', 'GLUT', 'EGL', 'GLEW', 'IDBStore', 'SDL', 'SDL_gfx', 'allocateUTF8', 'allocateUTF8OnStack', 'print', 'printErr'];
unexportedSymbols.forEach(unexportedRuntimeSymbol);
var calledRun;
dependenciesFulfilled = function runCaller() {
  // If run has never been called, and we should call run (INVOKE_RUN is true, and Module.noInitialRun is not false)
  if (!calledRun) run();
  if (!calledRun) dependenciesFulfilled = runCaller; // try this again later, after new deps are fulfilled
};
function callMain() {
  let args = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : [];
  assert(runDependencies == 0, 'cannot call main when async dependencies remain! (listen on Module["onRuntimeInitialized"])');
  assert(__ATPRERUN__.length == 0, 'cannot call main when preRun functions remain to be called');
  var entryFunction = _main;
  args.unshift(thisProgram);
  var argc = args.length;
  var argv = stackAlloc((argc + 1) * 4);
  var argv_ptr = argv;
  args.forEach(arg => {
    HEAPU32[argv_ptr >> 2] = stringToUTF8OnStack(arg);
    argv_ptr += 4;
  });
  HEAPU32[argv_ptr >> 2] = 0;
  try {
    var ret = entryFunction(argc, argv);

    // if we're not running an evented main loop, it's time to exit
    exitJS(ret, /* implicit = */true);
    return ret;
  } catch (e) {
    return handleException(e);
  }
}
function stackCheckInit() {
  // This is normally called automatically during __wasm_call_ctors but need to
  // get these values before even running any of the ctors so we call it redundantly
  // here.
  _emscripten_stack_init();
  // TODO(sbc): Move writeStackCookie to native to to avoid this.
  writeStackCookie();
}
function run() {
  let args = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : arguments_;
  if (runDependencies > 0) {
    return;
  }
  stackCheckInit();
  preRun();

  // a preRun added a dependency, run will be called later
  if (runDependencies > 0) {
    return;
  }
  function doRun() {
    var _Module$onRuntimeInit, _Module5;
    // run may have just been called through dependencies being fulfilled just in this very frame,
    // or while the async setStatus time below was happening
    if (calledRun) return;
    calledRun = true;
    Module['calledRun'] = true;
    if (ABORT) return;
    initRuntime();
    preMain();
    (_Module$onRuntimeInit = (_Module5 = Module)['onRuntimeInitialized']) === null || _Module$onRuntimeInit === void 0 || _Module$onRuntimeInit.call(_Module5);
    if (shouldRunNow) callMain(args);
    postRun();
  }
  if (Module['setStatus']) {
    Module['setStatus']('Running...');
    setTimeout(function () {
      setTimeout(function () {
        Module['setStatus']('');
      }, 1);
      doRun();
    }, 1);
  } else {
    doRun();
  }
  checkStackCookie();
}
function checkUnflushedContent() {
  // Compiler settings do not allow exiting the runtime, so flushing
  // the streams is not possible. but in ASSERTIONS mode we check
  // if there was something to flush, and if so tell the user they
  // should request that the runtime be exitable.
  // Normally we would not even include flush() at all, but in ASSERTIONS
  // builds we do so just for this check, and here we see if there is any
  // content to flush, that is, we check if there would have been
  // something a non-ASSERTIONS build would have not seen.
  // How we flush the streams depends on whether we are in SYSCALLS_REQUIRE_FILESYSTEM=0
  // mode (which has its own special function for this; otherwise, all
  // the code is inside libc)
  var oldOut = out;
  var oldErr = err;
  var has = false;
  out = err = x => {
    has = true;
  };
  try {
    // it doesn't matter if it fails
    _fflush(0);
  } catch (e) {}
  out = oldOut;
  err = oldErr;
  if (has) {
    warnOnce('stdio streams had content in them that was not flushed. you should set EXIT_RUNTIME to 1 (see the Emscripten FAQ), or make sure to emit a newline when you printf etc.');
    warnOnce('(this may also be due to not including full filesystem support - try building with -sFORCE_FILESYSTEM)');
  }
}
if (Module['preInit']) {
  if (typeof Module['preInit'] == 'function') Module['preInit'] = [Module['preInit']];
  while (Module['preInit'].length > 0) {
    Module['preInit'].pop()();
  }
}

// shouldRunNow refers to calling main(), not run().
var shouldRunNow = true;
if (Module['noInitialRun']) shouldRunNow = false;
run();

// end include: postamble.js
