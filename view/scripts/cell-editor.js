/* The cell editor component (`view/partials/cell-editor.php` holds its template
   and contract), one for the table and the record screen alike, and how a
   pending value is drawn in its cell until the server draws it. It ends once: a
   blur that follows Enter must not commit a second time. Every word it shows is
   handed in by the island that mounts it. */

const DURATION = /^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/;
const UNITS = ["Y", "M", "W", "D", "H", "m", "S"];
const UNIT_WORDS = ["years", "months", "weeks", "days", "hours", "minutes", "seconds"];
const INPUTS = { text: "text", link: "url", number: "number", date: "text",
  date_time: "datetime-local", time: "time", select: "text" };
const MOVES = { Enter: null, ArrowDown: [1, 0], ArrowUp: [-1, 0], Tab: [0, 1] };
/* The eight hits `Admin\SearchController` answers, as `.option` draws them, which arrive after placing. */
const HITS_ROOM = 330;

/* Which editor a field opens: in the cell, or one of the popovers. A select on
   the world's list has no options and edits as text that suggests the list; a
   link that takes a file of the library opens a popover offering it. */
const modeOf = (field) => (field.prose ? "prose" : field.edge ? "edge" : field.kind === "objects" ? "objects"
  : field.refers ? "refer" : field.kind === "select" && field.options.length > 0 ? "select"
  : field.many ? "list" : field.key in field.files ? "link" : "input");

/* The install's wall clock to the minute, as a `datetime-local` input holds it. */
function nowIn(zone) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat("en-GB", { timeZone: zone, year: "numeric",
    month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
    .formatToParts(new Date()).map((part) => [part.type, part.value]));
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

const cellEditor = {
  template: "#cell-editor-template",
  components: { edgePicker, listPicker, objectsPicker, referPicker, linkPicker },
  props: ["editing", "words", "language"],
  emits: ["commit", "cancel"],
  data() {
    const parts = DURATION.exec(this.editing.value ?? "") ?? [];
    const first = UNITS.findIndex((unit, index) => parts[index + 1] !== undefined);
    const mode = modeOf(this.editing.field);
    const offered = mode === "input" && this.editing.field.kind === "date_time" && !this.editing.value
      ? nowIn(this.editing.zone) : null;
    const shown = named(this.editing.field, this.editing.terms, this.editing.value);
    const draft = mode === "select" && this.editing.field.many ? [...(this.editing.value ?? [])]
      : ["input", "prose"].includes(mode) ? offered ?? shown ?? ""
      : mode === "select" ? this.editing.value ?? null : undefined;
    /* By the field from the first render, since a picker takes focus as it mounts,
       before its popover is measured and placed. */
    return { mode, draft, amount: first < 0 ? "" : Number(parts[first + 1]), unit: first < 0 ? "H" : UNITS[first],
      units: UNITS, finished: false, place: placement(this.editing.cell), touched: false, offered };
  },
  /* A duration held in two units shows its first: left untouched it stays as held. */
  watch: {
    amount() { this.touched = true; },
    unit() { this.touched = true; },
  },
  computed: {
    type() { return INPUTS[this.editing.field.kind]; },
    many() { return this.editing.field.many; },
  },
  mounted() {
    if (this.mode === "input") {
      this.$refs.input.focus();
      return;
    }
    this.place = this.placed();
    this.$nextTick(() => { this.unfollow = this.$refs.popover && following(this.editing.cell, this.$refs.popover,
      (at) => { this.place = { ...this.place, ...at }; }); });
    this.undismiss = dismissing((node) => this.$refs.popover?.contains(node), () => this.finish(this.mode === "select"
      && !this.many ? undefined : this.mode === "prose" ? this.result() : this.draft, null));
    /* Two queries, since one selector list answers the first option in the document, pressed or not. */
    if (this.mode === "select") {
      this.$nextTick(() => (this.$refs.popover.querySelector("[aria-pressed=true]")
        ?? this.$refs.popover.querySelector(".option"))?.focus());
    }
    if (this.mode === "prose") this.$nextTick(() => this.$refs.prose.focus());
  },
  unmounted() {
    this.unfollow?.();
    this.undismiss?.();
  },
  methods: {
    /* A picker that searches is placed by the height its hits grow it to, so a row near the foot opens it above. */
    placed() {
      const box = this.$refs.popover.getBoundingClientRect();
      const searching = ["edge", "link", "refer"].includes(this.mode) && !this.editing.words;
      return placement(this.editing.cell, { width: box.width, height: box.height + (searching ? HITS_ROOM : 0) });
    },
    /* An empty time offers now: Enter or the button takes it, and passing through leaves the field empty. */
    result(taken = false) {
      const kind = this.editing.field.kind;
      if (!taken && this.offered !== null && this.draft === this.offered) return null;
      if (kind === "duration" && !this.touched) return this.editing.value ?? null;
      if (kind === "duration") {
        const amount = Number(this.amount);
        const unit = this.unit === "m" ? "M" : this.unit;
        return amount > 0 ? "P" + ("HmS".includes(this.unit) ? "T" : "") + amount + unit : null;
      }
      if (this.draft === "" || this.draft === null) return null;
      const number = Number(this.draft);
      return kind === "number" && Number.isFinite(number) ? number
        : coded(this.editing.field, this.editing.terms, this.draft);
    },
    /* The clock read again, since the editor may have stood open a while. Pressing it keeps the input
       focused, so the editor is not left before the click lands. */
    takeNow() {
      this.draft = nowIn(this.editing.zone);
      this.finish(this.result(true), null);
    },
    finish(value, move) {
      if (this.finished) return;
      this.finished = true;
      value === undefined ? this.$emit("cancel") : this.$emit("commit", value, move);
    },
    key(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        return this.finish(undefined, null);
      }
      const back = event.shiftKey && event.key === "Tab";
      const toUnit = event.key === "Tab" && !back && event.target.classList.contains("duration-amount");
      if (!(event.key in MOVES) || toUnit || event.target.tagName === "SELECT") return;
      event.preventDefault();
      this.finish(this.result(event.key === "Enter"), back ? [0, -1] : MOVES[event.key]);
    },
    left(event) {
      if (!event.currentTarget.contains(event.relatedTarget)) this.finish(this.result(), null);
    },
    /* A list keeps its order: a term chosen again goes to the end. */
    pick(token) {
      if (!this.many) return this.finish(token, null);
      this.draft = this.draft.includes(token) ? this.draft.filter((each) => each !== token) : [...this.draft, token];
    },
    pressed(token) {
      return this.many ? this.draft.includes(token) : token === this.draft;
    },
    choose(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        return this.finish(undefined, null);
      }
      if (this.mode !== "select") return cycle(event, this.$refs.popover);
      const options = [...this.$refs.popover.querySelectorAll(".option")];
      const index = options.indexOf(document.activeElement);
      if (event.key === "Tab" && !this.many) {
        event.preventDefault();
        this.finish(undefined, null);
      } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        const step = event.key === "ArrowDown" ? 1 : -1;
        options[(index + step + options.length) % options.length].focus();
      } else {
        cycle(event, this.$refs.popover);
      }
    },
  },
};

/* A specialisation's fields stand on a record marked as that kind and on no other. */
function showKinds(row, kinds) {
  row.querySelectorAll("[data-of-kind]").forEach((cell) => {
    cell.toggleAttribute("data-off", ![].concat(kinds ?? []).includes(cell.dataset.ofKind));
  });
}

/* A pending value, drawn plainly until the server draws it for real. */
function cellShown(field, value, terms, language, words) {
  const span = document.createElement("span");
  if (field.kind === "boolean") {
    span.className = "switch" + (value ? " is-on" : "");
    span.append(document.createElement("i"));
    return span;
  }
  if (Array.isArray(value) && value.length > 0) return listShown(field, value, terms, language, words);
  span.textContent = value === null || Array.isArray(value) ? "—"
    : typeof value === "number" ? numberShown(value, language)
    : field.kind === "select" ? terms[value] ?? value
    : field.kind === "duration" ? durationShown(String(value), words)
    : field.kind === "date_time" ? String(value).replace(/^(\d{4}-\d\d-\d\dT\d\d:\d\d):00$/, "$1").replace("T", " ")
    : String(value);
  return span;
}

const numberShown = (value, language) => new Intl.NumberFormat(language, { maximumFractionDigits: 20,
  useGrouping: Math.abs(value) >= 10000 }).format(value);

/* As `Admin\Cells` draws it, `2 Std.`: the stored `PT2H` reads as a code to most people. */
function durationShown(value, words) {
  const parts = DURATION.exec(value);
  return parts ? UNIT_WORDS.flatMap((unit, index) => (parts[index + 1] === undefined ? []
    : [fill(words["duration." + unit], { n: parts[index + 1] })])).join(" ") : value;
}

function listShown(field, value, terms, language, words) {
  const span = document.createElement("span");
  if (field.edge) {
    span.className = "chips";
    value.forEach((item, index) => span.append(chipShown(item, field.many ? index + 1 : null, language, words)));
    return span;
  }
  const word = (each) => (field.kind === "select" ? terms[each] ?? each : String(each));
  const names = field.kind === "objects" ? value.map((row) => rowName(row, field, words)).filter((name) => name) : [];
  span.textContent = field.kind !== "objects" ? value.map(word).join(", ")
    : names.join(", ") || fill(words["cell.entries"], { n: value.length });
  return span;
}

function rowName(row, field, words) {
  const name = row?.name ?? row?.[field.bearing[0]];
  if (["string", "number"].includes(typeof name)) return String(name);
  return name?.label !== undefined ? (name.gone ? words["cell.gone"] : name.label) : "";
}

/* One target as `partials/cell` draws it: a list numbers its chips. */
function chipShown(item, number, language, words) {
  const chip = document.createElement("span");
  if (item.gone) {
    chip.className = "chip is-gone";
    chip.textContent = words["cell.gone"];
    return chip;
  }
  chip.className = "chip";
  const mark = number ? Object.assign(document.createElement("span"), { className: "chip-mark is-count",
    textContent: number }) : glyphMark(item.glyph);
  if (number) mark.setAttribute("aria-hidden", "true");
  chip.append(mark, document.createTextNode(item.label));
  if (item.preview) {
    mark.after(Object.assign(document.createElement("img"), { className: "chip-preview", src: item.preview, alt: "" }));
  }
  const says = saysShown({ ...item.hints, ...item.qualifiers }, language);
  if (says !== "") {
    chip.append(Object.assign(document.createElement("span"), { className: "chip-says", textContent: says }));
  }
  return chip;
}

/* What an edge says of its target as `Admin\Cells` draws it, „CTO · 2021–2024“, its description last;
   another locale's words stand over the ones it was written with. */
function saysShown(said, language) {
  const spelt = (key) => {
    const value = said[key];
    if (typeof value === "number") return numberShown(value, language);
    if (typeof value !== "string") return "";
    const day = new Date(value + "T00:00:00Z");
    return !key.endsWith("Date") || !/^\d{4}-\d\d-\d\d$/.test(value) || Number.isNaN(day.getTime()) ? value
      : new Intl.DateTimeFormat(language, { dateStyle: "medium", timeZone: "UTC" }).format(day);
  };
  const span = spelt("startDate") + "–" + spelt("endDate");
  return [spelt("roleName"), spelt("name"), span === "–" ? "" : span, spelt("membershipNumber"),
    spelt("membershipPointsEarned"), spelt("description")].filter((part) => part !== "").join(" · ");
}
