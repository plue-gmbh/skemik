/* The library behind a key that takes a file as a link: a link field's own
   popover (`linkPicker`, its template in `view/partials/pickers.php`), and the
   search the row and list pickers mix in (`pickers.js`). The files found as the
   key is typed in, the newest with nothing typed, asked of the field and the
   row's key (`Admin\SearchController`), which narrows them to what the link
   takes, and an upload beside them where the account writes the library. A pick
   writes the file's address, a path of this install and no edge, so the file's
   uses do not count it; where it is placed is each picker's. A row's referring
   key searches here too and picks the record (`objectsPicker`). The hits go
   when the key is left, a moment later, so a press on one of them still lands. */
const fileSearch = {
  data: () => ({ hits: [], asking: null, timer: null, sending: false, refused: "", noted: null }),
  methods: {
    takesFile(key) {
      return key in this.field.files || (key === this.field.reference && this.field.kinds[key] === "link");
    },
    searches(key) { return this.takesFile(key) || key === this.field.reference; },
    uploads(key) { return this.field.upload && this.takesFile(key); },
    kindsOf(key) { return key === this.field.reference ? this.field.accept : this.field.files[key] ?? []; },
    pictures(key) { return this.kindsOf(key).includes("image"); },
    asks(index, key) { return this.asking?.index === index && this.asking.key === key; },
    hint(key) {
      const word = this.uploads(key) ? "picker.refers_file" : key === this.field.reference ? "picker.refers"
        : "picker.link_file";
      return this.words[word].split("{upload}");
    },
    entering(index, key, typed) {
      if (!this.searches(key)) return;
      this.noted = null;
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.find(index, key, typed), 200);
    },
    leaving(index, key) {
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => {
        if (this.asks(index, key)) this.dropHits();
      }, 200);
    },
    dropHits() {
      this.hits = [];
      this.asking = null;
    },
    async find(index, key, typed) {
      const query = new URLSearchParams({ q: typed, collection: this.collection, field: this.field.key,
        in: this.locale, ...(key === this.field.reference ? {} : { key }) });
      const { body } = await answered(fetch("/search?" + query, { headers: { Accept: "application/json" } }));
      if (typed !== this.typedAt(index, key)) return;
      this.hits = body.hits ?? [];
      this.asking = { index, key };
    },
    /* What landed, as a hit is, or null with the refusal shown; a draft, which the
       key's line says until it is typed in again, since an announcement alone is heard and not seen. */
    async sent(index, key, event) {
      const file = event.target.files[0];
      if (file === undefined) return null;
      const answer = await this.posted(file, this.kindsOf(key).join());
      event.target.value = "";
      this.dropHits();
      if (!answer.picked) {
        this.refused = answer.error ?? fill(this.words["media.lost"], { limit: this.limit });
        return null;
      }
      this.noted = { index, key };
      announce(this.words["picker.draft"]);
      return { ...answer.picked, draft: true };
    },
    /* The library's answer, or nothing where none came. */
    async posted(file, picks) {
      const body = new FormData();
      body.set("file", file);
      body.set("picks", picks);
      this.sending = true;
      this.refused = "";
      announce(this.words["media.sending"]);
      const answer = await fetch("/media", { method: "POST", body,
        headers: { Accept: "application/json", "X-CSRF-Token": this.csrf } })
        .then((response) => response.json()).catch(() => ({}));
      this.sending = false;
      return answer;
    },
    fill: (text, values) => fill(text, values),
  },
};

/* A link field's popover: the address typed, or a file of the library picked
   beneath it, which ends the picker with the file's path. Enter takes what is
   typed and never a hit, since an address typed whole may still find a file by
   one of its words; a file uploaded stands in the input as a draft for Enter or
   Done to take, so the sentence saying so is read. */
const linkPicker = {
  template: "#link-picker-template",
  mixins: [fileSearch],
  props: ["field", "value", "words", "collection", "locale", "csrf", "limit"],
  emits: ["draft", "done"],
  data() {
    return { typed: String(this.value ?? "") };
  },
  mounted() {
    this.$refs.address.focus();
    this.find(0, this.field.key, this.typed);
  },
  methods: {
    typedAt() { return this.typed; },
    result() { return this.typed.trim() === "" ? null : this.typed.trim(); },
    typing() {
      this.$emit("draft", this.result());
      this.entering(0, this.field.key, this.typed);
    },
    pick(hit) { this.$emit("done", hit.file.address); },
    async upload(event) {
      const picked = await this.sent(0, this.field.key, event);
      if (picked === null) return;
      this.typed = picked.file.address;
      this.$emit("draft", this.result());
      focusField(this.$refs.address, this.$el.closest(".popover"));
    },
  },
};
