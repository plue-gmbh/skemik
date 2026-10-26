<?php

declare(strict_types=1);

/* A value edited, by kind, one component for the table and the record screen.
   A date is typed as text, since a year alone is a date and the browser's input
   takes none; an empty date-time shows the house's now, which Enter takes and
   passing through does not, or the browser's picker would open on a day the
   field does not hold. A currency or a country edits as text suggesting names,
   stored as codes, since three hundred buttons are no popover, and prose in a
   textarea, so a row that requires it is made from the table. The keys are a
   spreadsheet's: Enter commits, the arrows and Tab commit and move, Escape
   restores. */

if (!isset($template)) {
  return;
}
?>
<script type="text/x-template" id="cell-editor-template">
  <teleport v-if="mode === 'input'" :to="editing.cell">
    <div class="cell-editor" @keydown="key" @focusout="left">
      <template v-if="editing.field.kind === 'duration'">
        <input ref="input" class="cell-input duration-amount" type="number" min="0" step="1" v-model="amount"
          :aria-label="editing.field.label + ', ' + words['sheet.amount']">
        <select class="cell-input duration-unit" v-model="unit"
          :aria-label="editing.field.label + ', ' + words['sheet.unit']">
          <option v-for="each in units" :key="each" :value="each">{{ words['duration.unit.' + each] }}</option>
        </select>
      </template>
      <input v-else ref="input" class="cell-input" :type="type"
        :step="editing.field.kind === 'number' ? (editing.field.whole ? '1' : 'any')
          : (editing.field.kind === 'date_time' ? '1' : null)"
        v-model="draft" :aria-label="editing.field.label"
          :placeholder="editing.hint || (editing.field.kind === 'date' ? words['sheet.date_shape'] : null)"
        :list="editing.field.suggested.length > 0 ? 'cell-suggested' : null">
      <datalist v-if="editing.field.suggested.length > 0" id="cell-suggested">
        <option v-for="each in editing.field.suggested" :key="each"
          :value="editing.terms[each] || each"></option>
      </datalist>
    </div>
  </teleport>
  <teleport v-else to="body">
    <div ref="popover" class="popover" tabindex="-1"
      :class="{ 'is-wide': mode !== 'select', 'is-prose': mode === 'prose' }" role="dialog"
      :aria-label="editing.field.label" :style="place" @keydown="choose">
      <p class="popover-label">{{ editing.field.label }}<template
        v-if="editing.field.target"> → {{ editing.field.target }}</template><template
        v-if="editing.field.kind === 'markdown'"> · Markdown</template></p>
      <template v-if="mode === 'prose'">
        <textarea ref="prose" class="record-textarea" v-model="draft" :aria-label="editing.field.label"></textarea>
        <div class="picker-foot">
          <button type="button" class="button-primary"
            @click="finish(result(), null)">{{ words['picker.done'] }}</button>
        </div>
      </template>
      <template v-else-if="mode === 'select'">
        <div class="options">
          <button v-for="token in editing.field.options" :key="token" type="button" class="option"
            :aria-pressed="pressed(token) ? 'true' : 'false'" @click="pick(token)">
            <span>{{ editing.terms[token] || token }}</span>
            <span class="option-term" v-if="(editing.terms[token] || token) !== token">{{ token }}</span>
          </button>
          <button v-if="!many" type="button" class="option option-empty"
            :aria-pressed="draft === null ? 'true' : 'false'"
            @click="finish(null, null)">{{ words['sheet.empty_option'] }}</button>
        </div>
        <p class="popover-hint">{{ words['sheet.term_hint'] }}</p>
        <div class="picker-foot" v-if="many">
          <button type="button" class="button-primary" @click="finish(draft, null)">{{ words['picker.done'] }}</button>
        </div>
      </template>
      <edge-picker v-else-if="mode === 'edge'" :field="editing.field" :value="editing.value" :words="words"
        :collection="editing.collection" :locale="editing.locale" :record="editing.id" :fixed="Boolean(editing.words)"
        :csrf="editing.csrf" :limit="editing.limit" :all="editing.all" @draft="draft = $event"
        @done="finish($event, null)"></edge-picker>
      <list-picker v-else-if="mode === 'list'" :field="editing.field" :value="editing.value" :hints="editing.hint"
        :words="words"
        :terms="editing.terms" @draft="draft = $event" @done="finish(draft, null)"></list-picker>
      <refer-picker v-else-if="mode === 'refer'" :field="editing.field" :value="editing.value" :words="words"
        :collection="editing.collection" :locale="editing.locale" @draft="draft = $event"
          @done="finish($event, null)"></refer-picker>
      <objects-picker v-else :field="editing.field" :value="editing.value" :words="words" :terms="editing.terms"
        :collection="editing.collection" :locale="editing.locale" :csrf="editing.csrf" :limit="editing.limit"
        @draft="draft = $event" @done="finish(draft, null)"></objects-picker>
    </div>
  </teleport>
</script>
<?= $template->render('partials/pickers') ?>
