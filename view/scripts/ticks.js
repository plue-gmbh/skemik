/* A view's ticks and the bar over the view for what is ticked (each view draws
   its ticks, `partials/save-bar` the bar), mixed into the sheet island over its
   `change` and `edit`. A status, a language or one field's value across the
   ticked records lands as pending edits the save bar saves record by record, so
   each is checked, versioned and logged as a single edit is; the trash is a form,
   confirmed, since deleting is never pending. A field is set through its own
   editor, opened empty on the first ticked record that has it, so a reference
   picked is picked for every record and never one record's own edge; a record
   whose cell has no editor, off for its kind or a file's, is left, as is a record
   that already keeps its own words in the language set, which the spine dialog
   refuses one by one. */

function ticked(table, data, spineData) {
  const boxes = () => [...table.querySelectorAll("[data-tick]")];
  return {
    data: () => ({ ticked: [], statuses: spineData?.statuses ?? [], locales: spineData?.forks ? spineData.locales : [],
      collection: data.collection, csrf: data.csrf }),
    computed: {
      /* A field the view shows no cell of is not offered, and a boolean, which has
         no editor to open, is offered as its two values. */
      settable() {
        const shown = data.fields.filter((field) => table.querySelector(`[data-field="${field.key}"] [data-edit]`));
        return shown.flatMap((field) => (field.kind !== "boolean" ? [{ value: field.key, label: field.label }]
          : [true, false].map((value) => ({ value: field.key + ":" + Number(value),
            label: fill(this.words["sheet.set_to"], { field: field.label,
              value: this.words[value ? "cell.yes" : "cell.no"] }) }))));
      },
      /* The status, and the language, every ticked record would be saved with, so one set stays shown
         until the ticks or the pending edits make it untrue, and a mixed selection shows the placeholder. */
      status() {
        return this.held("spine.status");
      },
      locale() {
        return this.held("spine.locale");
      },
    },
    mounted() {
      table.addEventListener("change", (event) => this.tick(event.target));
    },
    methods: {
      fill: (text, values) => fill(text, values),
      tick(box) {
        if (box.matches("[data-tick-all]")) boxes().forEach((each) => { each.checked = box.checked; });
        else if (!box.matches("[data-tick]")) return;
        this.ticked = boxes().filter((each) => each.checked)
          .map((each) => each.closest("[data-record]").dataset.record);
        const all = table.querySelector("[data-tick-all]");
        if (all) {
          all.checked = this.ticked.length > 0 && this.ticked.length === boxes().length;
          all.indeterminate = this.ticked.length > 0 && !all.checked;
        }
        announce(fill(this.words["sheet.ticked"], { n: this.ticked.length }));
      },
      takers(key) {
        return this.ticked.filter((id) => table.querySelector(
          `[data-record="${id}"] [data-field="${key}"]:not([data-off]) [data-edit]`));
      },
      /* A page of one record draws no tick for all, so focus returns to its own. */
      untick() {
        const back = table.querySelector("[data-tick-all]") ?? boxes()[0];
        boxes().forEach((each) => { each.checked = false; });
        back.checked = false;
        this.tick(back);
        back.focus();
      },
      held(key) {
        const held = new Set(this.ticked.map((id) => this.current(id, key)));
        return held.size === 1 ? [...held][0] : "";
      },
      setStatus(event) {
        const value = event.target.value;
        if (value) this.ticked.forEach((id) => this.change(id, "spine.status", value));
      },
      setLocale(event) {
        const value = event.target.value;
        if (!value) return;
        const kept = this.ticked.filter((id) => spineData.rows[id].worded.includes(value));
        this.ticked.filter((id) => !kept.includes(id)).forEach((id) => this.change(id, "spine.locale", value));
        if (kept.length > 0) {
          toast(fill(this.words["sheet.set_locale_kept"], { n: kept.length, tag: value }), event.target);
        }
      },
      setField(event) {
        const [key, flag] = event.target.value.split(":");
        event.target.value = "";
        const takers = key ? this.takers(key) : [];
        if (key && takers.length === 0) return toast(this.words["sheet.set_none"], event.target);
        if (flag !== undefined) return takers.forEach((id) => this.change(id, key, flag === "1"));
        if (!key) return;
        /* The cell the editor opens on may be scrolled out of the table's view. */
        table.querySelector(`[data-record="${takers[0]}"] [data-field="${key}"]`)
          .scrollIntoView({ block: "nearest", inline: "nearest" });
        this.edit(takers[0], key, true);
      },
      back: () => location.search.slice(1),
      /* The dialog fills the page's top layer, so a press on its backdrop lands on the element itself. */
      outside(event) {
        if (event.target === this.$refs.trash) this.$refs.trash.close();
      },
    },
  };
}
