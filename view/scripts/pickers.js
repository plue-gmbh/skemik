/* The popovers a cell opens for a list and for a flattened reference
   (`view/partials/pickers.php` holds their templates and contract), inside the
   cell editor's popover; a reference's is `view/scripts/edge-picker.js`. Each
   reports its draft as it changes, so a press outside keeps what was done, and
   says `done` when a choice ends it. Every word is handed in. */

/* ↑ ↓ ✕ on a list, shared by the two below and a reference's (`edge-picker.js`). */
const ordered = {
  methods: {
    move(index, step) {
      const next = index + step;
      if (next < 0 || next >= this.items.length) return;
      [this.items[index], this.items[next]] = [this.items[next], this.items[index]];
      this.changed();
    },
    remove(index) {
      this.items.splice(index, 1);
      this.changed();
    },
    changed() {
      this.$emit("draft", this.result());
    },
    fill: (text, values) => fill(text, values),
  },
};

/* A suggested value is typed and picked by its name, `Deutsch`, and stored as
   its code, `de`: a pick must not put a word in the input other than the one
   offered. A code typed as such stays one, and anything else goes as typed, for
   the server to refuse. */
const named = (field, terms, value) => (field.suggested.length > 0 ? terms?.[value] ?? value : value);
function coded(field, terms, text) {
  const typed = text.trim().toLocaleLowerCase();
  return field.suggested.find((code) => (terms?.[code] ?? code).toLocaleLowerCase() === typed) ?? text;
}

/* Plain values — a person's job titles, every type's `sameAs` — one input each.
   A record's kind suggests the classes a save would take, and takes no other; a
   select on the world's list suggests its names. */
const listPicker = {
  template: "#list-picker-template",
  mixins: [ordered],
  props: ["field", "value", "hints", "words", "terms"],
  emits: ["draft", "done"],
  data() {
    return { items: (this.value ?? []).map((each) => named(this.field, this.terms, String(each))) };
  },
  computed: {
    type() { return this.field.kind === "link" ? "url" : "text"; },
  },
  mounted() {
    this.items.length === 0 ? this.add(-1) : this.focus(0);
  },
  methods: {
    result() {
      return this.items.map((item) => item.trim()).filter((item) => item !== "")
        .map((item) => coded(this.field, this.terms, item));
    },
    add(after) {
      this.items.splice(after + 1, 0, "");
      this.$nextTick(() => this.focus(after + 1));
    },
    focus(index) {
      focusField(this.$el.querySelectorAll(".picker-value")[index], this.$el.closest(".popover"));
    },
  },
};

/* A list of value objects: an entry per object, a line per key, the keys
   that bear it marked. A key the value object declares a number is sent as one,
   a key held as a number stays one while it reads as one, since a property's
   value is either, and a value this editor cannot spell is kept and shown, never rewritten. A
   row held as a text, as a field holds it from before it took rows, opens as its `value`, the
   key a row of one value names it by. The key that refers takes a word, and offers the records
   the word finds: a pick replaces the word by the record, ✕ returns the line to a word. */
/* A row's referring key is met as a reference is: its records offered as the key
   is focused, the newest first, and a file key, a step's image, with previews
   and, where the account writes the library, an upload beside them, what lands
   picked as a hit is, marked a draft. */
const objectsPicker = {
  template: "#objects-picker-template",
  mixins: [ordered],
  props: ["field", "value", "words", "terms", "collection", "locale", "csrf", "limit"],
  emits: ["draft", "done"],
  data() {
    const items = (this.value ?? []).map((entry) => (this.shaped(entry) ? { ...entry } : { value: entry }));
    const numbers = (entry) => Object.keys(entry).filter((key) => typeof entry[key] === "number");
    const declared = Object.keys(this.field.kinds).filter((key) => this.field.kinds[key] === "number");
    const numeric = new Set([...declared, ...items.flatMap(numbers)]);
    return { items, numeric, hits: [], asking: null, sending: false, refused: "" };
  },
  mounted() {
    if (this.items.length === 0) return this.add();
    focusField(this.$el.querySelector(".picker-value"), this.$el.closest(".popover"));
  },
  methods: {
    shaped: (value) => value !== null && typeof value === "object",
    refers: (value) => value !== null && typeof value === "object" && "label" in value,
    long(key) { return ["longtext", "markdown"].includes(this.field.kinds[key]); },
    file(key) { return key === this.field.reference && this.field.kinds[key] === "link"; },
    typing(index, key) {
      this.changed();
      this.entering(index, key);
    },
    entering(index, key) {
      if (key !== this.field.reference) return;
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.find(index, String(this.items[index][key] ?? "")), 200);
    },
    /* The hits go when the key is left, a moment later, so a press on one of them still lands. */
    leaving(index) {
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.asking === index && ([this.hits, this.asking] = [[], null]), 200);
    },
    hint() { return this.words["picker.refers_file"].split("{upload}"); },
    /* Enter takes the first hit offered, as a reference's does, and ends the entry otherwise. */
    enter(index, key) {
      if (this.asking === index && this.hits.length > 0) return this.refer(this.items[index], this.hits[0]);
      this.$emit("done");
    },
    async find(index, typed) {
      const query = new URLSearchParams({
        q: typed, collection: this.collection, field: this.field.key, in: this.locale,
      });
      const answer = await (await fetch("/search?" + query, { headers: { Accept: "application/json" } })).json();
      if (typed !== String(this.items[index]?.[this.field.reference] ?? "")) return;
      [this.hits, this.asking] = [answer.hits ?? [], index];
    },
    refer(entry, hit) {
      entry[this.field.reference] = { id: hit.id, label: hit.label, glyph: hit.glyph, preview: hit.preview,
        gone: false };
      [this.hits, this.asking] = [[], null];
      this.changed();
    },
    async upload(index, event) {
      const file = event.target.files[0];
      if (file === undefined) return;
      const body = new FormData();
      body.set("file", file);
      body.set("picks", this.field.accept.join());
      [this.sending, this.refused] = [true, ""];
      announce(this.words["media.sending"]);
      const answer = await fetch("/media", { method: "POST", body,
        headers: { Accept: "application/json", "X-CSRF-Token": this.csrf } })
        .then((response) => response.json()).catch(() => ({}));
      [this.sending, event.target.value] = [false, ""];
      if (!answer.picked) return (this.refused = answer.error ?? fill(this.words["media.lost"], { limit: this.limit }));
      this.items[index][this.field.reference] = { ...answer.picked, gone: false, draft: true };
      announce(this.words["picker.draft"]);
      [this.hits, this.asking] = [[], null];
      this.changed();
    },
    unrefer(entry) {
      entry[this.field.reference] = "";
      this.changed();
    },
    result() {
      return this.items.map((entry) => Object.fromEntries(Object.keys(entry).flatMap((key) => {
        const value = entry[key];
        if (this.shaped(value)) return [[key, value]];
        const text = String(value ?? "").trim();
        const number = Number(text);
        if (text === "") return [];
        return [[key, this.numeric.has(key) && Number.isFinite(number) ? number : text]];
      }))).filter((entry) => Object.keys(entry).length > 0);
    },
    add() {
      this.items.push({});
      this.$nextTick(() => focusField([...this.$el.querySelectorAll(".picker-entry")].pop()
        ?.querySelector(".picker-value"), this.$el.closest(".popover")));
    },
  },
};

/* A flattened key that refers: the record held as a chip, or the word typed
   and the records it finds. A chip sent back keeps the record, `{id}` picks one,
   a word is the word, and an empty word empties the field. */
const referPicker = {
  template: "#refer-picker-template",
  props: ["field", "value", "words", "collection", "locale"],
  emits: ["draft", "done"],
  data() {
    const held = this.value !== null && typeof this.value === "object" ? this.value : null;
    return { held, word: held === null ? String(this.value ?? "") : "", hits: [] };
  },
  mounted() {
    focusField(this.$refs.word, this.$el.closest(".popover"));
  },
  methods: {
    result() {
      return this.held ?? (this.word.trim() === "" ? null : this.word.trim());
    },
    typing() {
      this.$emit("draft", this.result());
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.find(this.word), 200);
    },
    async find(typed) {
      if (typed.trim() === "") return (this.hits = []);
      const query = new URLSearchParams({
        q: typed, collection: this.collection, field: this.field.key, in: this.locale,
      });
      const answer = await (await fetch("/search?" + query, { headers: { Accept: "application/json" } })).json();
      if (typed === this.word) this.hits = answer.hits ?? [];
    },
    refer(hit) {
      this.held = { id: hit.id, label: hit.label, glyph: hit.glyph, gone: false };
      this.hits = [];
      this.$emit("done", this.held);
    },
    unrefer() {
      this.held = null;
      this.$emit("draft", null);
      this.$nextTick(() => focusField(this.$refs.word, this.$el.closest(".popover")));
    },
  },
};
