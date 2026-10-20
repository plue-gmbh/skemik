/* A reference's popover (`view/partials/pickers.php` holds its template and
   contract), inside the cell editor's popover, and the lines under each target
   saying what the edge says of it. A held target keeps its words by its place
   and a removed one takes them along (`Admin\EdgeEdits`); a target picked here
   starts saying nothing. Which lines a target has is the server's
   word, `Profile\Qualifier::lines`, by its type. Every word is handed in. */

/* A target's lines as a save takes them: a blank one says nothing, a number stays one. */
function said(qualifiers, lines) {
  return Object.fromEntries(Object.entries(qualifiers).flatMap(([key, value]) => {
    const text = String(value ?? "").trim();
    const number = Number(text);
    return text === "" ? [] : [[key, lines[key] === "number" && Number.isFinite(number) ? number : text]];
  }));
}

/* One target's lines, each read as its kind; in another locale's words the ones
   it was written with are the placeholders, as a field's are. */
const edgeSays = {
  template: "#edge-says-template",
  props: ["item", "lines", "words"],
  emits: ["changed", "done"],
};

/* A reference: a search over the field's targets (`Admin\SearchController`) in
   the locale of the record edited, less the targets still listed, the held ones
   named by their place, since a held chip names no target, and asked again when
   one leaves, so it is offered back at once; and for a list the targets in
   order. `fixed` is another locale's words: the targets stand as the authored
   record holds them, and only what they say there is written. A file field
   uploads too, and what lands is picked as a hit is, marked a draft; a single
   target stays shown with that said, where a pick would end the picker unread. */
const edgePicker = {
  template: "#edge-picker-template",
  mixins: [ordered],
  components: { edgeSays },
  props: ["field", "value", "words", "collection", "locale", "record", "fixed", "csrf", "limit"],
  emits: ["draft", "done"],
  data() {
    const items = (this.value ?? []).map((item) => (item.qualifiers
      ? { ...item, qualifiers: { ...item.qualifiers } } : { ...item }));
    const opened = new Set(items.filter((item) => Object.keys(item.qualifiers ?? {}).length > 0));
    return { items, opened, typed: "", hits: [], asked: false, sending: false, refused: "" };
  },
  computed: {
    offered() {
      const taken = new Set(this.items.map((item) => item.id).filter(Boolean));
      return this.hits.filter((hit) => !taken.has(hit.id));
    },
    waits() { return this.field.any && this.typed.trim() === ""; },
    held() { return this.items.flatMap((item) => ("edge" in item ? [item.edge] : [])).sort().join(","); },
    /* A term field takes the word typed where its set has no term of it, *Vegan* being `vegan`'s. */
    wordable() {
      const word = this.typed.trim().toLocaleLowerCase();
      return this.field.term && this.asked && word !== ""
        && !this.hits.some((hit) => hit.label.toLocaleLowerCase() === word);
    },
  },
  watch: { held() { this.find(); } },
  mounted() {
    const popover = this.$el.closest(".popover");
    if (this.fixed) return focusField(this.$el.querySelector(".picker-value"), popover);
    this.find();
    focusField(this.$refs.search, popover);
  },
  methods: {
    result() {
      return this.items.map((item) => (item.qualifiers
        ? { ...item, qualifiers: said(item.qualifiers, this.linesOf(item)) } : item));
    },
    /* A held target's lines come with it, a new one's by its type. */
    linesOf(item) {
      if (item.gone) return {};
      return "edge" in item ? item.lines ?? {} : this.field.lines[item.type] ?? this.field.lines[""] ?? {};
    },
    speaks(item) { return Object.keys(this.linesOf(item)).length > 0; },
    shows(item) { return this.speaks(item) && (this.fixed || this.opened.has(item)); },
    toggle(item) { this.opened.has(item) ? this.opened.delete(item) : this.opened.add(item); },
    async find() {
      const typed = this.typed;
      if (this.waits) return ([this.hits, this.asked] = [[], false]);
      const query = new URLSearchParams({
        q: typed, collection: this.collection, field: this.field.key, in: this.locale,
        ...(this.held !== "" ? { record: this.record, held: this.held } : {}),
      });
      const answer = await (await fetch("/search?" + query, { headers: { Accept: "application/json" } })).json();
      if (typed !== this.typed) return;
      [this.hits, this.asked] = [answer.hits ?? [], true];
    },
    typing() {
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.find(), 200);
    },
    take(item) {
      if (!this.field.many) return this.$emit("done", [item]);
      this.items.push(item);
      this.typed = "";
      this.changed();
      this.find();
      focusField(this.$refs.search, this.$el.closest(".popover"));
    },
    pick(hit) {
      this.take({ id: hit.id, label: hit.label, glyph: hit.glyph, preview: hit.preview, type: hit.type,
        gone: false, qualifiers: {} });
    },
    async upload() {
      const file = this.$refs.file.files[0];
      if (file === undefined) return;
      const body = new FormData();
      body.set("file", file);
      body.set("picks", this.field.accept.join());
      [this.sending, this.refused] = [true, ""];
      announce(this.words["media.sending"]);
      const answer = await fetch("/media", { method: "POST", body,
        headers: { Accept: "application/json", "X-CSRF-Token": this.csrf } })
        .then((response) => response.json()).catch(() => ({}));
      [this.sending, this.$refs.file.value] = [false, ""];
      if (!answer.picked) return (this.refused = answer.error ?? fill(this.words["media.lost"], { limit: this.limit }));
      const item = { ...answer.picked, gone: false, qualifiers: {}, draft: true };
      announce(this.words["picker.draft"]);
      if (this.field.many) return this.take(item);
      this.items = [item];
      this.changed();
    },
    /* Enter takes the first hit, or the word a term set lacks, and ends the picker. */
    enter() {
      if (this.offered.length > 0) this.pick(this.offered[0]);
      else if (this.wordable) this.addWord();
      else return this.$emit("done", this.result());
      if (this.field.many) this.$emit("done", this.result());
    },
    addWord() {
      const word = this.typed.trim();
      this.take({ word, label: word, glyph: "DefinedTerm", gone: false });
    },
  },
};
