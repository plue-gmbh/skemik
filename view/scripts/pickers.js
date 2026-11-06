/* The popovers a cell opens for a list and for a flattened reference
   (`view/partials/pickers.php` holds their templates and contract), inside the
   cell editor's popover; a reference's is `view/scripts/edge-picker.js` and a
   link's, with the library search the list and the rows mix in, `file-picker.js`.
   Each reports its draft as it changes, so a press outside keeps what was done,
   and says `done` when a choice ends it. Every word is handed in. */

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
   select on the world's list suggests its names. A list of links that take a
   file offers the library under the line typed in, and a file uploaded fills
   the last line where it is blank, else a line of its own. */
const listPicker = {
  template: "#list-picker-template",
  mixins: [ordered, fileSearch],
  props: ["field", "value", "hints", "words", "terms", "collection", "locale", "csrf", "limit"],
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
    typedAt(index) { return String(this.items[index] ?? ""); },
    typing(index) {
      this.changed();
      this.entering(index, this.field.key, this.items[index]);
    },
    pick(index, hit) {
      this.items[index] = hit.file.address;
      this.dropHits();
      this.changed();
    },
    async upload(event) {
      const last = this.items.length - 1;
      const picked = await this.sent(last, this.field.key, event);
      if (picked === null) return;
      if (this.items[last]?.trim() === "") this.items[last] = picked.file.address;
      else this.items.push(picked.file.address);
      this.noted = { index: this.items.length - 1, key: this.field.key };
      this.changed();
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
   picked as a hit is, marked a draft. A row's other link keys that take a file,
   a download's `contentUrl`, offer the library the same way, and a pick writes
   the file's path. */
const objectsPicker = {
  template: "#objects-picker-template",
  mixins: [ordered, fileSearch],
  props: ["field", "value", "words", "terms", "collection", "locale", "csrf", "limit"],
  emits: ["draft", "done"],
  data() {
    const items = (this.value ?? []).map((entry) => (this.shaped(entry) ? { ...entry } : { value: entry }));
    const numbers = (entry) => Object.keys(entry).filter((key) => typeof entry[key] === "number");
    const declared = Object.keys(this.field.kinds).filter((key) => this.field.kinds[key] === "number");
    const numeric = new Set([...declared, ...items.flatMap(numbers)]);
    return { items, numeric };
  },
  mounted() {
    if (this.items.length === 0) return this.add();
    focusField(this.$el.querySelector(".picker-value"), this.$el.closest(".popover"));
  },
  methods: {
    shaped: (value) => value !== null && typeof value === "object",
    refers: (value) => value !== null && typeof value === "object" && "label" in value,
    long(key) { return ["longtext", "markdown"].includes(this.field.kinds[key]); },
    typedAt(index, key) { return String(this.items[index]?.[key] ?? ""); },
    typing(index, key) {
      this.changed();
      this.entering(index, key, this.typedAt(index, key));
    },
    /* Enter takes the first record offered to the referring key, as a reference's does, and ends the entry
       otherwise: a link key's Enter takes the address typed, as a link field's does. */
    enter(index, key) {
      if (key === this.field.reference && this.asks(index, key) && this.hits.length > 0) {
        return this.refer(this.items[index], this.hits[0]);
      }
      this.$emit("done");
    },
    pick(entry, key, hit) {
      key === this.field.reference ? this.refer(entry, hit) : this.address(entry, key, hit);
    },
    refer(entry, hit) {
      entry[this.field.reference] = { id: hit.id, label: hit.label, glyph: hit.glyph, preview: hit.preview,
        gone: false, ...(hit.draft ? { draft: true } : {}) };
      this.dropHits();
      this.changed();
    },
    /* The keys schema.org gives a file, filled from the asset wherever the value
       object carries them, a download's, so the three cannot disagree. */
    address(entry, key, hit) {
      const facts = { [key]: hit.file.address, encodingFormat: hit.file.format, contentSize: hit.file.size };
      const carried = Object.entries(facts).filter(([name]) => this.field.keys.includes(name));
      Object.assign(entry, Object.fromEntries(carried));
      this.dropHits();
      this.changed();
    },
    async upload(index, key, event) {
      const picked = await this.sent(index, key, event);
      if (picked !== null) this.pick(this.items[index], key, picked);
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
    return { held, word: held === null ? String(this.value ?? "") : "", hits: [], timer: null };
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
      const { body } = await answered(fetch("/search?" + query, { headers: { Accept: "application/json" } }));
      if (typed === this.word) this.hits = body.hits ?? [];
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
