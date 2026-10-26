<?php

declare(strict_types=1);

/* The pickers inside the cell editor's popover (`partials/cell-editor`), their
   components in `view/scripts/pickers.js` and a reference's in `edge-picker.js`.
   A reference picks a record of the collections the field points at, each
   target it holds a link to its record, since the cell it opened from is none.
   A term field searches its own set alone, and a word it lacks becomes a term
   on save. Beside a reference's target the pencil opens what the edge says of
   it under schema.org's names, `position` being no line since it is the list's
   order. A value object's keys stand under their schema.org names, since the
   profile gives a repeated object's keys no words of their own, and a key on a
   closed list is a select of its tokens' words, a held value outside the list
   offered as it stands, so opening a row never rewrites it. Every hit passes
   the reader's scope, as `/search`'s do. A file field's Upload sits in the foot,
   as a picture is added after the search has not found it. */

if (!isset($template)) {
  return;
}
$icon = fn (string $name): string => trim($template->render('partials/icon', ['name' => $name]));
?>
<script type="text/x-template" id="edge-picker-template">
  <div class="picker">
    <ol class="picker-items" v-if="field.many">
      <li v-for="(item, index) in items" :key="index" class="picker-target">
        <div class="picker-item">
          <component :is="item.href ? 'a' : 'span'" :href="item.href || null" class="chip"
            :class="{ 'is-gone': item.gone }">
            <span class="chip-mark is-count" aria-hidden="true">{{ index + 1 }}</span><img v-if="item.preview"
              class="chip-preview" :src="item.preview" alt="">{{ item.gone ? words['cell.gone'] : item.label }}
          </component>
          <span class="picker-tools" v-if="!fixed">
            <button v-if="speaks(item)" type="button" class="picker-tool" :title="words['picker.says']"
              :aria-label="words['picker.says']" :aria-expanded="shows(item) ? 'true' : 'false'"
              @click="toggle(item)"><?= $icon('edit') ?></button>
            <button type="button" class="picker-tool" :title="words['picker.up']" :aria-label="words['picker.up']"
              :disabled="index === 0" @click="move(index, -1)"><?= $icon('up') ?></button>
            <button type="button" class="picker-tool" :title="words['picker.down']" :aria-label="words['picker.down']"
              :disabled="index === items.length - 1" @click="move(index, 1)"><?= $icon('down') ?></button>
            <button type="button" class="picker-tool" :title="words['picker.remove']"
              :aria-label="words['picker.remove']" @click="remove(index)"><?= $icon('close') ?></button>
          </span>
        </div>
        <p v-if="item.draft" class="popover-hint is-warning">{{ words['picker.draft'] }}</p>
        <edge-says v-if="shows(item)" :item="item" :lines="linesOf(item)" :words="words" @changed="changed"
          @done="$emit('done', result())"></edge-says>
      </li>
      <li v-if="items.length === 0" class="popover-hint">{{ words['picker.no_targets'] }}</li>
    </ol>
    <div class="picker-current" v-else-if="items.length > 0">
      <div class="picker-item">
        <component :is="items[0].href ? 'a' : 'span'" :href="items[0].href || null" class="chip"
          :class="{ 'is-gone': items[0].gone }">
          <span class="chip-mark"><svg class="glyph" aria-hidden="true"><use
            :href="'#glyph-' + items[0].glyph"></use></svg></span><img v-if="items[0].preview"
            class="chip-preview" :src="items[0].preview"
            alt="">{{ items[0].gone ? words['cell.gone'] : items[0].label }}
        </component>
        <span class="picker-tools" v-if="!fixed && speaks(items[0])">
          <button type="button" class="picker-tool" :title="words['picker.says']" :aria-label="words['picker.says']"
            :aria-expanded="shows(items[0]) ? 'true' : 'false'" @click="toggle(items[0])"><?= $icon('edit') ?></button>
        </span>
      </div>
      <p v-if="items[0].draft" class="popover-hint is-warning">{{ words['picker.draft'] }}</p>
      <edge-says v-if="shows(items[0])" :item="items[0]" :lines="linesOf(items[0])" :words="words" @changed="changed"
        @done="$emit('done', result())"></edge-says>
    </div>
    <template v-if="!fixed">
      <input ref="search" type="search" class="popover-input" v-model="typed" @input="typing" autocomplete="off"
        :placeholder="words[field.many ? 'picker.add_search' : 'picker.search']"
          :aria-label="words[field.many ? 'picker.add_search' : 'picker.search']"
        @keydown.enter.prevent="enter">
      <div class="options" :class="{ 'is-pictures': field.kind === 'image' }">
        <button v-for="hit in offered" :key="hit.id" type="button" class="option" @click="pick(hit)">
          <img v-if="hit.preview" class="option-preview" :src="hit.preview" alt="">
          <span v-else class="chip-mark"><svg class="glyph" aria-hidden="true"><use
            :href="'#glyph-' + hit.glyph"></use></svg></span><span>{{ hit.label }}</span>
          <span class="option-term" :title="hit.collection">{{ hit.slug }}<template
            v-if="hit.locale"> · {{ hit.locale }}</template></span>
        </button>
      </div>
      <p class="popover-hint" v-if="waits">{{ words['picker.type'] }}</p>
      <p class="popover-hint" v-else-if="asked && typed.trim() !== '' && offered.length === 0 && !wordable">{{
        words['picker.none'] }}</p>
      <div class="options" v-if="wordable">
        <button type="button" class="option" @click="addWord">
          <span class="chip-mark"><svg class="glyph" aria-hidden="true"><use href="#glyph-new"></use></svg></span><span
            >{{ fill(words['picker.add_term'], { term: typed.trim() }) }}</span>
        </button>
      </div>
      <p class="popover-hint is-error" role="alert" v-if="refused">{{ refused }}</p>
    </template>
    <div class="picker-foot">
      <label v-if="!fixed && field.upload" class="button-quiet upload-button picker-upload"
        :title="fill(words['media.upload_up_to'], { limit })"><input ref="file"
        type="file" class="visually-hidden" :accept="field.accept.map((kind) => kind + '/*').join() || null"
        :disabled="sending"
        @change="upload">{{ sending ? words['media.sending'] : words['media.upload'] }}</label>
      <button v-if="!fixed && !field.many && (items.length > 0 || all)" type="button" class="button-quiet"
        @click="$emit('done', [])">{{ words['picker.remove_relation'] }}</button>
      <button v-if="field.many || items.length > 0" type="button" class="button-primary"
        @click="$emit('done', result())">{{ words['picker.done'] }}</button>
    </div>
  </div>
</script>
<script type="text/x-template" id="edge-says-template">
  <div class="picker-says" role="group" :aria-label="words['picker.says'] + ': ' + item.label">
    <label v-for="(kind, key) in lines" :key="key" class="picker-key">
      <span class="picker-key-name">{{ key }}</span>
      <input class="popover-input picker-value" :type="kind === 'number' ? 'number' : 'text'"
        v-model="item.qualifiers[key]"
        :placeholder="item.hints?.[key] ?? (kind === 'date' ? words['sheet.date_shape'] : null)" autocomplete="off"
        @input="$emit('changed')" @keydown.enter.prevent="$emit('done')">
    </label>
  </div>
</script>
<script type="text/x-template" id="list-picker-template">
  <div class="picker">
    <ol class="picker-items">
      <li v-for="(item, index) in items" :key="index" class="picker-item">
        <input class="popover-input picker-value" :type="type" v-model="items[index]" @input="changed"
          :aria-label="field.label + ' ' + (index + 1)" :placeholder="hints?.[index]"
            @keydown.enter.prevent="$emit('done')"
          :list="field.suggested.length > 0 ? 'picker-suggested' : null">
        <span class="picker-tools">
          <button type="button" class="picker-tool" :title="words['picker.up']" :aria-label="words['picker.up']"
            :disabled="index === 0" @click="move(index, -1)"><?= $icon('up') ?></button>
          <button type="button" class="picker-tool" :title="words['picker.down']" :aria-label="words['picker.down']"
            :disabled="index === items.length - 1" @click="move(index, 1)"><?= $icon('down') ?></button>
          <button type="button" class="picker-tool" :title="words['picker.remove']" :aria-label="words['picker.remove']"
            @click="remove(index)"><?= $icon('close') ?></button>
        </span>
      </li>
      <li v-if="items.length === 0" class="popover-hint">{{ words['picker.no_values'] }}</li>
    </ol>
    <datalist id="picker-suggested"><option v-for="each in field.suggested" :key="each"
      :value="terms?.[each] || each"></option></datalist>
    <div class="picker-foot">
      <button type="button" class="button-quiet"
        @click="add(items.length - 1)">+ {{ words['picker.add_value'] }}</button>
      <button type="button" class="button-primary" @click="$emit('done')">{{ words['picker.done'] }}</button>
    </div>
  </div>
</script>
<script type="text/x-template" id="objects-picker-template">
  <div class="picker">
    <fieldset v-for="(entry, index) in items" :key="index" class="picker-entry">
      <legend class="picker-entry-head">
        <span>{{ fill(words['picker.entry'], { n: index + 1 }) }}</span>
        <span class="picker-tools">
          <button type="button" class="picker-tool" :title="words['picker.up']" :aria-label="words['picker.up']"
            :disabled="index === 0" @click="move(index, -1)"><?= $icon('up') ?></button>
          <button type="button" class="picker-tool" :title="words['picker.down']" :aria-label="words['picker.down']"
            :disabled="index === items.length - 1" @click="move(index, 1)"><?= $icon('down') ?></button>
          <button type="button" class="picker-tool" :title="words['picker.remove']" :aria-label="words['picker.remove']"
            @click="remove(index)"><?= $icon('close') ?></button>
        </span>
      </legend>
      <template v-for="key in field.keys" :key="key">
        <div v-if="refers(entry[key])" class="picker-key picker-referred" role="group" :aria-label="key">
          <span class="picker-key-name">{{ key }}<span v-if="field.bearing.includes(key)"
            class="picker-bearing"> · {{ words['picker.bearing'] }}</span></span>
          <span class="picker-item">
            <span class="chip" :class="{ 'is-gone': entry[key].gone }"><span class="chip-mark"><svg class="glyph"
              aria-hidden="true"><use :href="'#glyph-' + entry[key].glyph"></use></svg></span><img
              v-if="entry[key].preview" class="chip-preview" :src="entry[key].preview" alt="">{{ entry[key].gone
              ? words['cell.gone'] : entry[key].label }}</span>
            <button type="button" class="picker-tool" :title="words['picker.unrefer']"
              :aria-label="words['picker.unrefer']" @click="unrefer(entry)"><?= $icon('close') ?></button>
          </span>
          <p v-if="entry[key].draft" class="popover-hint is-warning">{{ words['picker.draft'] }}</p>
        </div>
        <label v-else class="picker-key">
          <span class="picker-key-name">{{ key }}<span v-if="field.bearing.includes(key)"
            class="picker-bearing"> · {{ words['picker.bearing'] }}</span></span>
          <input v-if="shaped(entry[key])" class="popover-input picker-value" :value="JSON.stringify(entry[key])"
            disabled>
          <select v-else-if="field.choices[key]" class="popover-input picker-value" v-model="entry[key]"
            @change="typing(index, key)">
            <option value="">{{ words['sheet.empty_option'] }}</option>
            <option v-if="entry[key] && !field.choices[key].includes(entry[key])" :value="entry[key]">{{
              entry[key] }}</option>
            <option v-for="token in field.choices[key]" :key="token" :value="token">{{ terms?.[token] ?? token
              }}</option>
          </select>
          <textarea v-else-if="long(key)" class="popover-input picker-value picker-text" rows="3"
            v-model="entry[key]" @input="typing(index, key)"></textarea>
          <input v-else class="popover-input picker-value" :class="{ 'picker-refer': key === field.reference }"
            v-model="entry[key]" @input="typing(index, key)" autocomplete="off"
            @focus="entering(index, key)" @blur="leaving(index)"
            @keydown.enter.prevent="enter(index, key)"
            :aria-describedby="key === field.reference ? 'picker-refers-' + index : null">
        </label>
        <template v-if="key === field.reference && !refers(entry[key])">
          <p class="popover-hint picker-refers" :id="'picker-refers-' + index"><template
            v-if="file(key) && field.upload">{{ hint()[0] }}<label class="picker-upload-word"
            :title="fill(words['media.upload_up_to'], { limit })"><input type="file" class="visually-hidden"
            :accept="field.accept.map((kind) => kind + '/*').join() || null" :disabled="sending"
            @change="upload(index, $event)">{{ sending ? words['media.sending'] : words['picker.refers_upload']
            }}</label>{{ hint()[1] }}</template><template v-else>{{
            words['picker.refers'] }}</template></p>
          <div class="options" :class="{ 'is-pictures': file(key) }" v-if="asking === index" role="group"
            :aria-label="words['picker.search']">
            <button v-for="hit in hits" :key="hit.id" type="button" class="option" @click="refer(entry, hit)">
              <img v-if="hit.preview" class="option-preview" :src="hit.preview" alt="">
              <span v-else class="chip-mark"><svg class="glyph" aria-hidden="true"><use
                :href="'#glyph-' + hit.glyph"></use></svg></span><span>{{ hit.label }}</span>
              <span class="option-term" :title="hit.collection">{{ hit.slug }}<template
                v-if="hit.locale"> · {{ hit.locale }}</template></span>
            </button>
          </div>
          <p class="popover-hint is-error" role="alert" v-if="refused">{{ refused }}</p>
        </template>
      </template>
    </fieldset>
    <p v-if="items.length === 0" class="popover-hint">{{ words['picker.no_entries'] }}</p>
    <div class="picker-foot">
      <button type="button" class="button-quiet" @click="add">+ {{ words['picker.add_entry'] }}</button>
      <button type="button" class="button-primary" @click="$emit('done')">{{ words['picker.done'] }}</button>
    </div>
  </div>
</script>
<script type="text/x-template" id="refer-picker-template">
  <div class="picker">
    <p class="picker-current" v-if="held">
      <span class="chip" :class="{ 'is-gone': held.gone }"><span class="chip-mark"><svg class="glyph"
        aria-hidden="true"><use :href="'#glyph-' + held.glyph"></use></svg></span>{{ held.gone ? words['cell.gone']
        : held.label }}</span>
      <button type="button" class="picker-tool" :title="words['picker.unrefer']" :aria-label="words['picker.unrefer']"
        @click="unrefer"><?= $icon('close') ?></button>
    </p>
    <input v-else ref="word" type="text" class="popover-input" v-model="word" @input="typing" autocomplete="off"
      :aria-label="field.label" aria-describedby="refer-hint" @keydown.enter.prevent="$emit('done', result())">
    <p class="popover-hint" id="refer-hint">{{ words['picker.refers'] }}</p>
    <div class="options" v-if="hits.length > 0" role="group" :aria-label="words['picker.search']">
      <button v-for="hit in hits" :key="hit.id" type="button" class="option" @click="refer(hit)">
        <span class="chip-mark"><svg class="glyph" aria-hidden="true"><use
          :href="'#glyph-' + hit.glyph"></use></svg></span><span>{{ hit.label }}</span>
        <span class="option-term" :title="hit.collection">{{ hit.slug }}<template
          v-if="hit.locale"> · {{ hit.locale }}</template></span>
      </button>
    </div>
    <div class="picker-foot">
      <button type="button" class="button-primary" @click="$emit('done', result())">{{ words['picker.done'] }}</button>
    </div>
  </div>
</script>
