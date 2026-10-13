/* The spine dialog component (`view/partials/spine-dialog.php` holds its template
   and contract), mounted by the save bar's island. It keeps no value of its own:
   it shows the pending edit or the loaded value, and each change goes back to
   the island as a pending value under `spine.{column}`. The slug is folded here
   only to show the address it will have; `Domain\Slug` folds it again on save. */

const GERMAN_FOLDS = { ä: "ae", ö: "oe", ü: "ue", ß: "ss", Ä: "Ae", Ö: "Oe", Ü: "Ue", ẞ: "SS" };

const slugify = (text) => text.replace(/[äöüßÄÖÜẞ]/g, (letter) => GERMAN_FOLDS[letter])
  .normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase()
  .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");

/* A wall time with or without seconds, spelled one way for comparing. */
const wall = (value) => (value && value.length === 16 ? value + ":00" : value);

/* A save sends the dialog's edits under `spine`, apart from the values, since a
   field may share a spine column's name (`Admin\RecordController`). */
function withSpine(sent) {
  const entries = Object.entries(sent);
  const body = Object.fromEntries(entries.filter(([key]) => !key.startsWith("spine.")));
  const spine = entries.filter(([key]) => key.startsWith("spine.")).map(([key, value]) => [key.slice(6), value]);
  return spine.length > 0 ? { ...body, spine: Object.fromEntries(spine) } : body;
}

/* A rename or a move of language changes a record's address, and the old one
   answers 404, so the row's links to the record follow it. Only those: the same
   row links to other records through its references. */
function relink(row, before, after) {
  const [from, to] = [new URL(before, location.origin), new URL(after, location.origin)];
  row.querySelectorAll("a[href]").forEach((link) => {
    const url = new URL(link.getAttribute("href"), location.origin);
    if (url.pathname !== from.pathname || url.searchParams.get("in") !== from.searchParams.get("in")) return;
    url.pathname = to.pathname;
    url.searchParams.set("in", to.searchParams.get("in"));
    link.setAttribute("href", url.pathname + url.search + url.hash);
  });
}

const spineDialog = {
  template: "#spine-dialog-template",
  props: ["opened", "pending"],
  emits: ["change", "close"],
  data: () => ({ typed: null, wrongWindow: false }),
  computed: {
    record() { return this.opened.record; },
    common() { return this.opened.common; },
    words() { return this.common.words; },
    address() {
      const [slug, collection] = [this.current("slug"), this.common.collection];
      return this.common.public ? `/feed/${this.current("locale")}/${collection}/${slug}` : `/c/${collection}/${slug}`;
    },
    moved() {
      return this.current("slug") !== this.record.slug || this.current("locale") !== this.record.locale;
    },
  },
  mounted() {
    const dialog = this.$refs.dialog;
    dialog.showModal();
    const [box, at] = [dialog.getBoundingClientRect(), this.opened.chip.getBoundingClientRect()];
    const below = at.bottom + 4 + box.height <= window.innerHeight - 8;
    dialog.style.left = Math.max(8, Math.min(at.left, window.innerWidth - box.width - 8)) + "px";
    dialog.style.top = Math.max(8, below ? at.bottom + 4 : at.top - box.height - 4) + "px";
    focusField(this.$refs.slug, dialog);
  },
  methods: {
    fill: (text, values) => fill(text, values),
    current(key) {
      return "spine." + key in this.pending ? this.pending["spine." + key] : this.record[key];
    },
    set(key, value) {
      this.$emit("change", this.opened.id, "spine." + key, value);
    },
    typeSlug(event) {
      this.typed = event.target.value;
      this.set("slug", slugify(this.typed) || this.record.slug);
    },
    /* An end before the start is refused here and never made pending. */
    setWindow(key, event) {
      const value = event.target.value || null;
      const [from, to] = key === "publish_from"
        ? [value, this.current("publish_to")] : [this.current("publish_from"), value];
      this.wrongWindow = from !== null && to !== null && wall(to) <= wall(from);
      if (!this.wrongWindow) this.set(key, value);
    },
    /* The dialog fills the page's top layer, so a press on its backdrop lands on the element itself. */
    outside(event) {
      if (event.target === this.$refs.dialog) this.$refs.dialog.close();
    },
  },
};
