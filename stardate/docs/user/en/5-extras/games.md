# Games

The Games section (`/games`) provides Simon Tatham's Portable Puzzle Collection — 41 logic puzzles compiled to WebAssembly and running entirely in your browser. No account or internet connection is needed to play after the page loads.

## Overview

Browse the game list on the left and click any title to start playing. Each puzzle generates a new random game every time you click **New game**. Your game state is not saved between sessions.

**Tip:** Activate help mode (the ? icon in the navigation bar) and click the game area to see a description of the current puzzle.

## Controls

All games share the same interface:

- **Game menu** — top bar with New, Restart, Undo, Redo, Solve, Type, and game-specific menus
- **Resize handle** — drag the triangle at the bottom-right corner of the puzzle to make it bigger or smaller
- **Undo / Redo** — step back and forward through your moves
- **Solve** — reveals the solution (available in most games)
- **Enter game ID / Random seed** — replay a specific puzzle by entering its ID or seed
- **Save / Load** — download or upload a save file to resume a puzzle later
- **Preferences** — game-specific display and behaviour settings
- **Permalink** — a link you can bookmark or share to return to the exact same puzzle

Keyboard shortcuts work in most games (arrow keys, Enter, Escape). Hover over the canvas with keyboard focus for best results.

## Blackbox

Shoot rays into a box containing hidden balls. Rays enter from the edges and either exit elsewhere, are absorbed, or reflected back. Deduce the exact position of every ball from the entry/exit points alone. Mark where you think balls are and confirm when ready.

## Bridges

Connect a set of islands with bridges so that each island has the number of bridges equal to its displayed number. Bridges run horizontally or vertically, cannot cross each other, and all islands must form one connected group.

## Cube

Roll a net-covered cube across a grid. Each square on the cube's faces is coloured; rolling it paints the grid squares. Return every grid square to the correct colour by rolling the cube so the right face lands on each square.

## Dominosa

A grid is filled with numbers. Place dominoes over every pair of adjacent numbers so that each possible domino (e.g. 0-0, 0-1, … up to n-n) appears exactly once. Use logic to determine which pairing belongs where.

## Fifteen

A classic sliding-tile puzzle. Arrange tiles numbered 1–15 in order (left to right, top to bottom) with the blank space in the bottom-right corner. Click a tile adjacent to the blank to slide it.

## Filling

Fill each cell with a positive integer. Every maximal connected region of cells bearing the same number must have an area (cell count) exactly equal to that number. Pre-filled clue cells are fixed.

## Flip

A grid of two-sided tiles starts face-down. Clicking a tile flips it and all of its neighbours. Flip all tiles to the face-up side to win. Plan your moves to avoid undoing previous work.

## Flood

The top-left corner starts with a colour. Each move, choose a colour to flood-fill from that corner, extending its region. Turn the entire grid the same colour within the move limit.

## Galaxies

Divide the grid into regions, one per galaxy dot. Each region must be 180°-rotationally symmetric around its dot. Every cell belongs to exactly one region.

## Group

Fill a grid to form a valid mathematical group (abstract algebra). Each row and column must contain each symbol exactly once (like a Latin square), and the operation table must satisfy the group axioms. Harder levels require finding the correct symmetry.

## Guess

A Mastermind-style code-breaking game. Guess the hidden sequence of colours. After each guess the game tells you how many pegs are the right colour in the right place, and how many are the right colour in the wrong place. Deduce the exact sequence within the allotted guesses.

## Inertia

Guide a ball across a grid collecting all the gems. The ball slides in a straight line until it hits a wall or stops naturally. Avoid mines — hitting one ends the game. Plan a route that collects every gem without a fatal collision.

## Keen

Fill a grid with digits 1 to N (like Sudoku: each digit once per row and column). Cells are grouped into cages with a target number and arithmetic operator (×, ÷, +, −). The digits in each cage must produce the target when combined with that operator.

## Lightup

Place light bulbs in empty cells. Every blank cell must be illuminated (in the same row or column as a bulb, with no wall in between). No two bulbs may illuminate each other. Numbered black squares indicate how many bulbs touch them orthogonally.

## Loopy

Draw a single closed loop through the grid, passing through dot intersections. Numbered squares indicate exactly how many of their four sides form part of the loop. The loop must not branch or cross itself.

## Magnets

Fit magnetic dominoes into a grid of slots. Each domino has a positive (+) and a negative (−) pole; they can also be placed as neutral (blank). Row and column totals tell you exactly how many + and − poles appear. No two adjacent cells may have the same polarity.

## Map

Colour a map so that no two adjacent regions share the same colour, using as few colours as possible (at most four). Drag or click to assign colours. A classic four-colour-theorem puzzle.

## Mines

Classic Minesweeper. Click to reveal squares; flagged squares are mines. Numbers tell you how many mines touch each revealed square. Uncover all safe squares without hitting a mine. The first click is always safe.

## Mosaic

A Minesweeper variant where every cell is either filled or empty. Each numbered cell indicates the total count of filled cells in its 3×3 neighbourhood (including itself). Deduce which cells to fill.

## Net

Rotate individual tiles to connect all terminal nodes (endpoints) to the central hub in one continuous network. No loose ends and no loops allowed. Tiles wrap at the edges in harder modes.

## Netslide

Like Net, but instead of rotating tiles individually, you push entire rows or columns to slide them (the tile at one end wraps to the other). Connect all terminals to the hub.

## Palisade

Divide the grid into connected regions all of the same size (the target size is given). Draw fences along cell edges to separate regions. Numbered cells indicate how many of their four edges are fences.

## Pattern

A nonogram (Picross). Fill cells in a grid based on numerical clues on each row and column. Each clue lists the lengths of consecutive filled runs in that row or column, in order.

## Pearl

Draw a single closed loop through the grid. Black circles must be on a corner of the loop (the loop turns there). White circles must be on a straight section (the loop does not turn there). The loop passes through every circle.

## Pegs

Classic peg solitaire. A peg can jump over an adjacent peg into an empty hole, removing the jumped peg. Clear as many pegs as possible; the goal is to leave exactly one peg (or reach the target configuration).

## Range

Fill cells with black squares. Every white cell must be able to "see" exactly as many other white cells as its number, looking in all four cardinal directions until a black cell or edge is reached. Black cells must all be connected to each other or separated, and no 2×2 block of black squares is allowed.

## Rect

Divide the grid into rectangles. Each rectangle contains exactly one number, and that number equals the rectangle's area (width × height). Deduce the boundaries of every rectangle from the given numbers.

## Samegame

Click groups of two or more touching same-coloured balls to remove them. Larger groups score more points. Clear as many balls as possible. Isolated single balls cannot be removed.

## Signpost

Fill a grid with numbers 1 to N (where N = grid area). Each cell contains an arrow. The number you place in a cell must be one less than the number in the cell the arrow points to, forming a single chain from 1 to N. Some numbers are pre-filled as clues.

## Singles

Mark some cells black. No digit may appear twice in any row or column among the white (unmarked) cells. Black cells may not be adjacent to each other horizontally or vertically, and all white cells must form one connected region.

## Sixteen

A sliding-tile puzzle on a 4×4 grid where entire rows and columns slide (the tile at one end wraps to the other). Arrange the tiles in numerical order.

## Slant

Draw a diagonal line in every cell, either / or \\. The diagonals must form a forest (no closed loops) and the number at each grid intersection indicates how many of the up-to-four adjacent diagonals touch that point.

## Solo

Classic Sudoku. Fill a 9×9 grid (or other size variant) so that every row, column, and bold-bordered box contains each digit exactly once. Harder variants include irregular boxes, killer cages, or extra constraints.

## Tents

Place tents in empty grid cells. Every tree must have exactly one tent adjacent to it (horizontally or vertically). No two tents may be adjacent (including diagonally). Row and column counts tell you exactly how many tents appear in each.

## Towers

Fill a grid with building heights (1 to N). Each row and column must contain each height exactly once. Numbers on the outside edges tell you how many buildings are visible from that direction (taller buildings hide shorter ones behind them).

## Tracks

Draw a train track from the given entry point to the exit point. Row and column numbers tell you how many track segments appear in each. Some segments are given as clues. The track must not branch.

## Twiddle

Rotate 2×2 groups of tiles within a grid to sort all the tiles into numerical order. Each move selects a 2×2 block and rotates its four tiles 90° clockwise or counter-clockwise.

## Undead

Place ghosts, vampires, and zombies in grid cells. Each monster type is visible or invisible depending on whether it is reflected in mirrors (diagonal lines in some cells). Row, column, and mirror-view counts must all match the given numbers.

## Unequal

Like Sudoku: fill digits 1 to N once per row and column. Additional inequality signs (< >) between adjacent cells must also be satisfied. In harder modes only the inequalities are given, with no row/column uniqueness constraint.

## Unruly

Fill a binary grid with 0s and 1s. Each row and column must have equal numbers of 0s and 1s. No three consecutive cells in any row or column may have the same value. Some cells are pre-filled as clues.

## Untangle

A graph is drawn with nodes and edges. Some edges cross. Drag the nodes to new positions until no edges intersect. All planar graphs can be untangled.
