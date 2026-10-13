<?php

declare(strict_types=1);

/* The spine dialog (`view/scripts/spine-dialog.js`), opened from a record's chip:
   the columns that are this install's custody of a record rather than its
   content, pending edits like any value, so one save bar and one `PATCH` carry
   them. A rename says that the old address then answers 404, since nothing
   forwards. The window shows wherever it holds a value, since a leftover end
   would otherwise take a record down unseen. Language is a work's alone, its
   translations being the record screen's Languages card. */

if (!isset($template)) {
  return;
}
?>
<script type="application/json" id="spine-data"><?= str_replace('<', '\\u003c', $spine) ?></script>
<script type="text/x-template" id="spine-dialog-template">
  <teleport to="body">
    <dialog ref="dialog" class="spine-dialog" tabindex="-1" autofocus :aria-label="words['spine.open']"
      @close="$emit('close')"
      @click="outside">
      <div class="spine-body">
        <label class="spine-label" for="spine-slug">{{ words['spine.slug'] }}</label>
        <input id="spine-slug" ref="slug" class="popover-input" type="text"
          :value="typed === null ? current('slug') : typed"
          autocomplete="off" spellcheck="false" aria-describedby="spine-address" @input="typeSlug"
          @keydown.enter.prevent="$refs.dialog.close()">
        <p class="popover-hint" id="spine-address">{{ words['spine.address'] }}
          <a v-if="common.public && !moved" class="spine-mono" :href="address" target="_blank"
            rel="noreferrer">{{ address }}</a>
          <span v-else class="spine-mono">{{ address }}</span></p>
        <p class="popover-hint is-warning" v-if="moved">{{ words['spine.moved'] }}</p>

        <div class="spine-group" role="group" aria-labelledby="spine-status">
          <p class="spine-label" id="spine-status">{{ words['spine.status'] }}</p>
          <div class="spine-options">
            <button v-for="each in common.statuses" :key="each.value" type="button" class="spine-option"
              :aria-pressed="current('status') === each.value ? 'true' : 'false'"
              @click="set('status', each.value)">{{ each.word }}</button>
          </div>
          <p class="popover-hint" v-if="record.own">{{ record.own }}</p>
        </div>
        <div class="spine-window">
          <label class="spine-label"
            v-if="current('status') === 'published' || current('publish_from')">{{ words['spine.publish_from'] }}
            <input class="popover-input" type="datetime-local" :value="current('publish_from')"
              @change="setWindow('publish_from', $event)"></label>
          <label class="spine-label"
            v-if="current('status') === 'published' || current('publish_to')">{{ words['spine.publish_to'] }}
            <input class="popover-input" type="datetime-local" :value="current('publish_to')"
              @change="setWindow('publish_to', $event)"></label>
        </div>
        <p class="popover-hint is-error" role="alert" v-if="wrongWindow">{{ words['spine.window_order'] }}</p>

        <template v-if="common.forks">
          <div class="spine-group" role="group" aria-labelledby="spine-locale">
            <p class="spine-label" id="spine-locale">{{ words['spine.locale'] }}</p>
            <select v-if="common.locales.length > 4" class="popover-input" aria-labelledby="spine-locale"
              :value="current('locale')" @change="set('locale', $event.target.value)">
              <option v-for="tag in common.locales" :key="tag" :value="tag"
                :disabled="record.worded.includes(tag)">{{ tag }}</option>
            </select>
            <div v-else class="spine-options">
              <button v-for="tag in common.locales" :key="tag" type="button" class="spine-option"
                :aria-pressed="current('locale') === tag ? 'true' : 'false'"
                :aria-disabled="record.worded.includes(tag) ? 'true' : null"
                @click="record.worded.includes(tag) || set('locale', tag)">{{ tag }}</button>
            </div>
            <p class="popover-hint"
              v-if="record.worded.length > 0">{{ fill(words['spine.worded'],
              { locales: record.worded.join(', ') }) }}</p>
          </div>
        </template>

        <p class="popover-hint spine-foot">{{ record.footer }}</p>
      </div>
    </dialog>
  </teleport>
</script>
