# Design brief: CDC Placement Portal

Written in Phase 3 milestone PU (2026-10-12) with the frontend-design plugin, before any UI code. Re-read this before
building any new page. Where this brief and the owner's hard constraints disagree, the constraints win.

## 1. Who it is for and what it has to do

The portal runs a placement season at IIT (ISM) Dhanbad. Four kinds of people use it:

| Who | How they use it | What the screen must do for them |
|---|---|---|
| CDC admin | All day, on a desktop, across twenty-odd pages | Find the page fast, scan long tables, never lose their place |
| Student | Often on a phone, under a deadline | Say what is open, what they applied to, what happened |
| Recruiter | A few visits a season, formal | Fill a long form without getting lost; see their drives |
| Mentor | Occasionally | See their own mentees and their resumes |

The job of the interface is to make a season legible: which drive is at which stage, and who is where in it. It is a
working tool, not a brochure. Every design choice below is judged by "can an admin read a 200-row table for an hour".

## 2. Hard constraints (owner)

- MUI 6 with the `sx` prop and `lib/theme.ts` tokens only. No Tailwind classes, no CSS modules, no new UI library.
- Primary colour is institute maroon `#7B1113`; secondary is navy `#1A237E`. New neutrals and accents are theme tokens,
  never hex values inside components.
- Typeface: Inter (loaded with `next/font`, self-hosted), with the existing Roboto and system fallbacks. No other font.
- Text contrast meets WCAG AA.
- No `text-align: justify` anywhere.
- Layout and visual design only. Every page keeps its data, actions and routes.
- All portal navigation lives in the left sidebar. The top bar never carries navigation links.

## 3. Tokens

### Colour

| Token | Value | Use |
|---|---|---|
| `primary.main` | `#7B1113` | Buttons, links in tables, the active item in the sidebar, the brand block (10.9:1 on white, 9.4:1 on its own 8% tint) |
| `primary.dark` / `primary.light` | `#5A0C0E` / `#A52422` | Hover and pressed; never large areas |
| `secondary.main` | `#1A237E` | Internship (INF) surfaces and their chips, as before |
| `background.default` | `#F4F5F7` | The page behind everything |
| `background.paper` | `#FFFFFF` | Cards, the sidebar, the top bar, dialogs |
| `text.primary` | `#1F2328` | Body text and headings (15.8:1 on white, 14.5:1 on the page) |
| `text.secondary` | `#5A626C` | Captions, helper text, table headers (6.2:1 on white, 5.7:1 on the page) |
| `divider` | `#E3E6EA` | Card borders, table rules, the sidebar edge |
| `sidebar.activeBg` | maroon at 8% | Background of the active sidebar item |
| `sidebar.hoverBg` | `text.primary` at 4% | Hover in the sidebar |

Status colours are MUI's `success`, `warning`, `info`, `error` and `default`, chosen in exactly one place,
`lib/statuscolors.js`. Colour is never the only signal: a status always has its word next to it.

The sidebar is white, as in Superset. The one saturated area on any signed-in screen is the brand block at the top of
the sidebar (maroon, with the institute logo and the portal's name). Everything else is quiet so that tables and forms
carry the page.

### Type

One family, Inter. Numbers in tables, tiles and dates use tabular figures (`font-variant-numeric: tabular-nums`) so
columns of roll numbers, CGPAs and counts line up.

| Role | Size / line height | Weight | Notes |
|---|---|---|---|
| Page title | 24 / 32 (20 / 28 on phones) | 700 | letter-spacing −0.01em |
| Section title | 18 / 26 | 700 | inside a page |
| Card title | 16 / 24 | 600 | |
| Body | 16 / 24 (MUI `body1`); 14 / 20 (`body2`) for dense text | 400 | MUI defaults kept; text fields stay at 16 px so phones do not zoom on focus |
| Table cell | 14 / 20 | 400 | header cells 13 / 20, 600, `text.secondary` |
| Caption / helper | 12 / 18 | 400 | never smaller than 12 |
| Sidebar item | 14 / 20 | 500 (600 when active) | |
| Sidebar group heading | 11 / 16 | 600 | small caps look: upper case, letter-spacing 0.06em (owner's request) |

Public pages (landing, login) may use the larger MUI heading sizes; signed-in screens do not go above the page title.
On public pages the gold of the institute header (`brand.gold` in `lib/theme.ts`) is used only on maroon; on white the
darker `brand.goldText` is used, because gold on white is about 2.2:1 and fails AA.
Line length for running text stays under about 80 characters (`maxWidth: "72ch"` on paragraphs of explanation).

### Space, shape, depth

- Spacing steps: 4, 8, 12, 16, 24, 32, 48 (MUI `spacing` 0.5, 1, 1.5, 2, 3, 4, 6). Page padding 24 on desktop, 16 on
  phones. Gap between cards and sections 24; inside a card 16 to 24.
- Radius by level, not one value everywhere: controls 8 (buttons, inputs), cards and dialogs 12, sidebar items 8,
  chips fully round. The sidebar and top bar have square edges.
- Depth: cards are flat, white, with a 1px `divider` border. Shadows are for things that float above the page only:
  menus, dialogs, the phone drawer. Cards do not lift or move on hover.
- Motion only answers an action: the sidebar collapsing, a drawer opening, a row expanding. Nothing animates on load.
  `prefers-reduced-motion` turns the collapse animation off.

## 4. Layout

```
≥ 1200 px                                           768 to 1199 px            < 768 px
┌────────────┬───────────────────────────────┐      ┌────┬───────────────┐    ┌─────────────────┐
│▓ logo  CDC │ Placement / Job Profiles  🔍 🔔│      │▓ ◉ │ … / page  🔍 🔔│    │ ☰  Job Profiles 🔔│
│▓ Admin     ├───────────────────────────────┤      ├────┼───────────────┤    ├─────────────────┤
│ OVERVIEW   │ Page title          [Action]  │      │ ▣  │               │    │ Page title      │
│ ▌Dashboard │ one line of explanation       │      │ ▣  │   content     │    │ [Action]        │
│ PLACEMENT  │                               │      │ ▣  │               │    │                 │
│  Placements│ ┌───────────────────────────┐ │      │ ▣  │               │    │  content, one   │
│  Job Prof. │ │ card / table              │ │      │    │               │    │  column         │
│  …         │ └───────────────────────────┘ │      │ 🔔 │               │    │                 │
│────────────│                               │      │ 👤 │               │    └─────────────────┘
│ 🔔 Notifs 3│                               │      │ ⎋  │               │     ☰ opens the full
│ 👤 Name    │                               │      └────┴───────────────┘     sidebar as a drawer
│ ⎋ Sign out │                               │       icon rail, 72 px,
└────────────┴───────────────────────────────┘       labels as tooltips
 260 px, can collapse to the 72 px rail
```

- **Sidebar.** Brand block; then grouped sections with a heading each; each item has an icon and a label. The active
  item has the maroon tint, maroon text and icon, and a 3px maroon bar on its left edge. An optional "Recently
  visited" group lists the last five pages. At the bottom: Notifications (with the unread count), the signed-in person,
  and Sign out. The collapse state is remembered.
- **Top bar.** 56px, white, one bottom border. Left: where you are ("Section / Page") on desktop, the menu button and
  the page name on phones. Right: global utilities only (the admin's student search, the notifications bell). No
  links to pages.
- **Content.** Left aligned. Maximum width 1440px, so wide tables can use a wide screen. Text is left aligned
  everywhere; numbers in table columns are right aligned.
- **Page header** (`components/shared/pageheader.jsx`): the title, one line of explanation, the primary action on the
  right, and a quiet "Back" link above the title when the page has a parent. It sits on the page background with no
  box, colour or gradient.
- **Sections inside a page** (a job profile's Overview / Applicants / Pipeline) stay as horizontal tabs in the content
  area. The "left side only" rule is about navigation between pages.
- **Public pages** have no sidebar. They use the same type, buttons, radius and maroon.

## 5. Component rules

- **Buttons.** One contained (maroon) action per view; the rest outlined or text. A button says what happens:
  "Save changes", "Publish results", "Download". The confirmation uses the same word ("Published").
- **Tables.** `size="small"`, sticky header, header text 13px 600 `text.secondary`, row height about 44px, no zebra
  stripes, a hover tint. On a phone a table keeps readable columns and scrolls sideways inside its card; the page
  itself never scrolls sideways.
- **Filters.** One row above the table on desktop. On a phone the filters collapse behind a "Filters" button that
  shows how many are set, so the table is on the first screen.
- **Status chips.** `size="small"`, colour from `lib/statuscolors.js`, label in sentence case.
- **Stat tiles** (`components/shared/stattile.jsx`): a label, a large tabular number, an optional one-line note. Flat,
  bordered, no icon tile, no gradient. Used on dashboards only.
- **Forms.** Fields in section cards; 16px between fields, 24px between sections; a sticky bar with the actions on
  long forms. Labels are sentence case. Helper text says what to type, error text says what is wrong and how to fix
  it.
- **Empty states.** One sentence that says what would appear here and, where there is one, the button that makes it
  appear. No illustration.
- **Loading.** `LinearProgress` at the top of the card that is loading, or `Skeleton` rows in a table. No spinners in
  the middle of a blank page.
- **Dialogs.** Radius 12, title 18px 700, the confirming action on the right and named after what it does.
- **Focus.** Every interactive element keeps a visible focus ring (MUI default, or a 2px maroon outline in the
  sidebar).

## 6. Do and don't

| Do | Don't |
|---|---|
| Put every link to a page in the sidebar config `lib/nav/<role>.js` | Add a link to a page in the top bar |
| Take colours, radius and spacing from the theme | Write a hex value, a pixel radius or a shadow in a component |
| Take status colours from `lib/statuscolors.js` | Write another `getStatusColor` |
| Left align text; right align numbers in columns | Justify or centre running text |
| Use sentence case for labels, buttons and headings | Use all caps (the sidebar group headings are the one exception) |
| Use a bordered flat card | Add a shadow, a gradient or a hover lift to a card |
| Say what a control does ("Download login log") | Say "Submit", "Click here", or add an arrow to a label |
| Keep emoji out of headings and chips on new pages | Decorate with emoji |
| Check 390, 768, 1024 and 1440 px before calling a page done | Let the page scroll sideways at any width |

## 7. Review against the generic defaults (plugin step)

The plugin asks for the plan to be checked against what a similar brief would produce by default. Three changes came
out of that check:

1. **The first idea for the page header was a maroon gradient band with a white icon tile** (it is also what the
   portal has today, on every page). It reads as decoration and it spends the brand colour on every screen. Replaced
   by a plain title on the page background; the brand colour is now spent once, in the sidebar's brand block.
2. **The first idea for cards was the usual soft shadow and a lift on hover** (also what the theme does today for
   every card). On a page of twenty cards and tables that is noise. Replaced by flat bordered cards; shadows only for
   things that really float.
3. **The first idea for dashboards was icon tiles with coloured gradient backgrounds.** Replaced by plain stat tiles
   whose only emphasis is a large tabular number, because the number is the information.

What was deliberately kept from the owner's reference (Superset): the white grouped sidebar with section headings in
small caps, the active-item accent bar, the collapsible rail, and the greeting line on dashboards.

## 8. Constraint log

Places where the plugin's guidance and the owner's constraints pulled apart; the constraint won.

| Plugin guidance | Owner constraint | Outcome |
|---|---|---|
| Avoid all-caps labels | "Grouped sections with small caps headings" in the sidebar | Sidebar group headings are small caps; nothing else is |
| Choose a distinctive typeface for the brief | Inter, no other font | Inter only; character comes from weight, tabular figures and spacing |
| Take an aesthetic risk with the palette | Maroon primary, navy secondary stay | Palette kept; the risk taken is restraint (one saturated block per screen) |
| Body text 14 px on signed-in screens (first draft of this brief) | No functional change; forms must stay usable on phones | MUI sizes kept (body 16 px, inputs 16 px); density comes from table cells and sidebar items at 14 px |
