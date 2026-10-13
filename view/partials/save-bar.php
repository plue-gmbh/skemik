<?php

declare(strict_types=1);

/* Unsaved changes, and the one place a person learns that edits are pending until
   saved. Saving sends each changed record once, by its `PATCH` with only its
   changed values and the version the page loaded, so a save can partly fail and
   says so per record. A `412` names who changed it and offers Take theirs or Save
   anyway: overwriting a colleague is possible, never silent, and reaches only the
   fields this page changed. Pending edits are never kept in browser storage,
   which would be a second, unversioned draft, and there is no autosave: a write
   per keystroke races the conditional write it relies on, and ten cells of a price
   list are one decision. Deleting, translating, uploading and the house's
   structure are never pending; each applies when its dialog is confirmed. */

if (!isset($template)) {
  return;
}
?>
<script type="application/json" id="sheet-data"><?= str_replace('<', '\\u003c', $data) ?></script>
<div data-island="sheet"></div>
<script type="text/x-template" id="save-bar-template">
  <div class="save-bar" :class="{ 'is-open': open, 'is-done': saved }" :aria-hidden="open ? 'false' : 'true'">
    <span class="save-bar-dot" aria-hidden="true"></span>
    <span class="save-bar-text">{{ sentence }}</span>
    <template v-if="conflict">
      <button type="button" class="button-quiet" @click="takeTheirs">{{ words['sheet.reload'] }}</button>
      <button type="button" class="button-primary" @click="overwrite">{{ words['sheet.overwrite'] }}</button>
    </template>
    <template v-else-if="count > 0">
      <button type="button" class="button-quiet" :disabled="saving"
        @click="discard">{{ words['sheet.discard'] }}</button>
      <button type="button" class="button-primary" :disabled="saving"
        aria-keyshortcuts="Control+S Meta+S" @click="save">{{ words['sheet.save'] }}</button>
    </template>
  </div>
  <cell-editor v-if="editing" :key="editing.id + editing.field.key" :editing="editing" :words="words"
    :language="language" @commit="commit" @cancel="cancel"></cell-editor>
  <spine-dialog v-if="spine" :key="spine.id" :opened="spine" :pending="pending[spine.id] || {}"
    @change="change" @close="closeSpine"></spine-dialog>
  <teleport v-if="ticked.length > 0" to="[data-ticked]">
    <div class="ticked-bar" role="toolbar" :aria-label="words['sheet.ticked_actions']">
      <span class="ticked-count" :title="fill(words['sheet.ticked'], { n: ticked.length })"><span
        aria-hidden="true">{{ fill(words['sheet.ticked_count'], { n: ticked.length }) }}</span><span
        class="visually-hidden">{{ fill(words['sheet.ticked'], { n: ticked.length }) }}</span></span>
      <select v-if="statuses.length > 0" class="ticked-field" :aria-label="words['sheet.set_status']"
        :value="status" @change="setStatus">
        <option value="">{{ words['sheet.set_status'] }}</option>
        <option v-for="each in statuses" :key="each.value" :value="each.value">{{ each.word }}</option>
      </select>
      <select ref="setter" class="ticked-field" :aria-label="words['sheet.set_field']" @change="setField">
        <option value="">{{ words['sheet.set_field'] }}</option>
        <option v-for="each in settable" :key="each.value" :value="each.value">{{ each.label }}</option>
      </select>
      <button type="button" class="tool-button is-danger" aria-haspopup="dialog" :title="words['delete.confirm']"
        @click="$refs.trash.showModal()"><?= $template->render('partials/icon', ['name' => 'trash']) ?><span
        class="visually-hidden">{{ words['delete.confirm'] }}</span></button>
      <button type="button" class="tool-button" :title="words['sheet.untick']"
        @click="untick"><?= $template->render('partials/icon', ['name' => 'close']) ?><span
        class="visually-hidden">{{ words['sheet.untick'] }}</span></button>
    </div>
    <dialog ref="trash" class="dialog" aria-labelledby="ticked-trash-title" @click="outside">
      <form method="post" :action="'/c/' + collection + '/trash'">
        <input type="hidden" name="_csrf" :value="csrf">
        <input type="hidden" name="ids" :value="ticked.join(',')">
        <input type="hidden" name="back" :value="back()">
        <h2 id="ticked-trash-title">{{ fill(words['delete.question_many'], { n: ticked.length }) }}</h2>
        <p>{{ words['delete.many'] }}</p>
        <div class="dialog-actions">
          <button type="button" class="button-quiet" @click="$refs.trash.close()">{{ words['dialog.cancel'] }}</button>
          <button type="submit" class="button-danger">{{ words['delete.confirm'] }}</button>
        </div>
      </form>
    </dialog>
  </teleport>
</script>
<?= $template->render('partials/cell-editor') ?>
<?= $template->render('partials/spine-dialog', ['spine' => $spine]) ?>
