# Games (खेल)

Games section (`/games`) Simon Tatham का Portable Puzzle Collection है — 41 logic puzzles जो WebAssembly में compile होकर सीधे browser में चलते हैं। खेलने के लिए account या internet की जरूरत नहीं (page load के बाद)।

## Overview

बाईं तरफ game list से कोई भी title चुनें। **New game** पर क्लिक करने से हर बार नया random puzzle मिलता है। Game state sessions के बीच save नहीं होती।

**टिप:** Navigation bar में ? आइकन से help mode चालू करें, फिर game area पर क्लिक करें — उस puzzle का विवरण मिलेगा।

## Controls

सभी games में एक जैसा interface है:

- **Game menu** — ऊपर की bar: New, Restart, Undo, Redo, Solve, Type
- **Resize handle** — puzzle के bottom-right कोने पर triangle को drag करके आकार बदलें
- **Undo / Redo** — moves आगे-पीछे करें
- **Solve** — solution दिखाता है (अधिकतर games में)
- **Enter game ID / Random seed** — specific puzzle दोबारा खेलें
- **Save / Load** — puzzle file download/upload करें
- **Preferences** — game-specific settings
- **Permalink** — puzzle bookmark/share करने के लिए link

अधिकतर games में keyboard shortcuts काम करते हैं (arrow keys, Enter, Escape)।

## Blackbox

एक black box में छिपी balls को rays shoot करके ढूंढें। Rays या तो बाहर निकलती हैं, absorb होती हैं, या reflect हो जाती हैं। Entry/exit points से balls की position का अनुमान लगाएं।

## Bridges

Islands को bridges से जोड़ें। हर island पर उतनी ही bridges होनी चाहिए जितना उसका number है। Bridges cross नहीं हो सकतीं, और सभी islands एक connected group बनाएं।

## Cube

Net-covered cube को grid पर roll करें। Rolling से grid squares paint होते हैं — हर square को सही colour से paint करें।

## Dominosa

Number-filled grid पर dominoes रखें — हर possible domino exactly एक बार आना चाहिए।

## Fifteen

Classic sliding-tile puzzle। Tiles 1–15 को order में arrange करें, blank नीचे-दाईं तरफ।

## Filling

हर cell में positive integer भरें। Same number की हर connected region का area उसी number के बराबर होना चाहिए।

## Flip

Two-sided tiles की grid में सभी tiles को face-up करें। एक tile click करने से वह और उसके neighbours flip होते हैं।

## Flood

Top-left corner से शुरू करके, move limit में पूरी grid को एक colour से भरें।

## Galaxies

Grid को regions में बांटें — हर galaxy dot के लिए एक region। हर region अपने dot के around 180° rotationally symmetric होना चाहिए।

## Group

Grid में mathematical group बनाएं — हर row और column में हर symbol exactly एक बार आए, और group axioms पूरे हों।

## Guess

Mastermind-style puzzle। Hidden colour sequence guess करें। हर guess पर बताया जाता है कितने सही जगह हैं और कितने गलत जगह।

## Inertia

Ball को slide करके सभी gems collect करें। Ball दीवार से टकराने तक straight line में चलती है। Mines से बचें।

## Keen

Grid में digits 1 से N भरें (Sudoku की तरह)। Cages में दिए operator (+, −, ×, ÷) और target number को satisfy करें।

## Lightup

Empty cells में light bulbs रखें। हर blank cell illuminate हो। कोई भी दो bulbs एक-दूसरे को illuminate न करें।

## Loopy

Grid dots से होते हुए single closed loop बनाएं। Numbered squares बताते हैं उनकी कितनी sides loop का हिस्सा हैं।

## Magnets

Magnetic dominoes grid में fit करें। Positive/negative poles की row और column counts match करें। Same polarity adjacent न हो।

## Map

Map को colour करें — adjacent regions का colour अलग-अलग हो (maximum 4 colours)।

## Mines

Classic Minesweeper। Numbers से mines का अनुमान लगाएं, सभी safe squares uncover करें।

## Mosaic

Minesweeper variant — हर numbered cell अपने 3×3 neighbourhood में filled cells की count बताता है।

## Net

Tiles rotate करके सभी terminals को central hub से connect करें।

## Netslide

Net की तरह, लेकिन tiles rotate की जगह rows/columns slide होती हैं।

## Palisade

Grid को equal-size connected regions में बांटें। Numbered cells बताते हैं उनकी कितनी edges fences हैं।

## Pattern

Nonogram/Picross। Row और column clues से cells fill करके picture बनाएं।

## Pearl

Single closed loop बनाएं। Black circles पर loop मुड़े, white circles पर सीधा जाए।

## Pegs

Peg solitaire। एक peg दूसरे के ऊपर कूदकर उसे हटाए। Goal: एक peg बचे।

## Range

Cells में black squares रखें। हर white cell उतने white cells "देख" सके जितना उसका number है।

## Rect

Grid को rectangles में बांटें। हर rectangle का area उसमें दिए number के बराबर हो।

## Samegame

Same colour के touching balls के groups click करके हटाएं। ज्यादा balls एक साथ = ज्यादा points।

## Signpost

Grid में 1 से N तक numbers भरें। हर cell का arrow बताता है next number किस direction में है।

## Singles

कुछ cells black करें। White cells में कोई digit row/column में दो बार न आए। Black cells adjacent न हों।

## Sixteen

4×4 grid में पूरी rows/columns slide होती हैं (wrap-around)। Tiles को order में लाएं।

## Slant

हर cell में diagonal line (/ या \\) बनाएं। कोई loop न बने, और intersections पर count सही हो।

## Solo

Classic Sudoku। हर row, column, और box में हर digit exactly एक बार।

## Tents

Trees के पास tents रखें। हर tree का exactly एक adjacent tent हो। Tents आपस में adjacent न हों।

## Towers

Grid में building heights भरें। Edges से दिखने वाली buildings की count match करें।

## Tracks

Entry से exit तक train track बनाएं। Row और column segments की count match करें।

## Twiddle

2×2 groups को rotate करके सभी tiles को order में लाएं।

## Undead

Grid में ghosts, vampires, zombies रखें। Mirrors में visibility rules और row/column counts match करें।

## Unequal

Sudoku + inequality signs। Adjacent cells के बीच < > signs satisfy करें।

## Unruly

Binary grid (0s और 1s) भरें। हर row/column में equal count। कोई तीन consecutive same value न हों।

## Untangle

Graph के nodes drag करें ताकि कोई edges cross न करें।
