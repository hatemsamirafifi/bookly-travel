/* Bookly adapter for the official self-hosted HugeRTE (MIT). */
(function () {
  'use strict';
  const BASE = '/vendor/hugerte';
  const instances = new WeakMap();
  const byId = new Map();

  function getModel(wrapper) {
    const target = wrapper.nextElementSibling;
    return target && target.matches('textarea[data-hugerte-model]') ? target : null;
  }

  function commitState(wrapper, editor) {
    const model = getModel(wrapper);
    if (!model) return;
    const html = editor.getContent();
    if (model.value === html) return;
    const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set;
    setter.call(model, html);
    model.dispatchEvent(new Event('input', { bubbles: true }));
    model.dispatchEvent(new Event('change', { bubbles: true }));
  }

  async function openMediaPicker(editor, callback) {
    const response = await fetch('/admin/hugerte/media', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    if (!response.ok) throw new Error('Media library unavailable (' + response.status + ')');
    const data = await response.json();
    if (!Array.isArray(data.assets)) throw new Error('Invalid media response');
    if (data.assets.length === 0) {
      editor.windowManager.alert('No public ready images found in Media Library.');
      return;
    }
    const items = data.assets.map(asset => ({ text: asset.label, value: String(asset.id) }));
    const index = new Map(data.assets.map(asset => [String(asset.id), asset]));
    editor.windowManager.open({
      title: 'Bookly Media Library',
      body: { type: 'panel', items: [{ type: 'selectbox', name: 'asset', label: 'Image', items }] },
      buttons: [{ type: 'cancel', text: 'Cancel' }, { type: 'submit', text: 'Insert', primary: true }],
      onSubmit(api) {
        const asset = index.get(api.getData().asset);
        if (!asset) return;
        callback(asset.url, { alt: asset.alt || asset.label });
        api.close();
      }
    });
  }

  function mountOne(wrapper) {
    if (!window.hugerte || !window.hugerte.init || instances.has(wrapper)) return;
    const source = wrapper.querySelector('[data-hugerte-source]');
    const model = getModel(wrapper);
    if (!source || !model || !document.contains(wrapper)) return;

    // Stable within the current page, no hidden cross-field sharing.
    const editorId = source.id;
    instances.set(wrapper, true);

    window.hugerte.init({
      target: source,
      base_url: BASE,
      suffix: '.min',
      height: 480,
      min_height: 300,
      resize: true,
      menubar: 'edit view insert format tools table help',
      plugins: 'advlist anchor autolink autoresize charmap code codesample directionality emoticons fullscreen image link lists nonbreaking preview searchreplace table visualblocks wordcount',
      toolbar: wrapper.dataset.editorToolbar || 'undo redo | blocks | bold italic',
      toolbar_mode: 'sliding',
      image_caption: true,
      image_title: true,
      image_advtab: true,
      automatic_uploads: false,
      file_picker_types: 'image',
      file_picker_callback: function (cb, _value, meta) {
        if (meta.filetype !== 'image') return;
        openMediaPicker(window.hugerte.get(editorId), cb).catch(err => {
          window.hugerte.get(editorId)?.windowManager.alert(err.message);
        });
      },
      convert_urls: false,
      relative_urls: false,
      promotion: false,
      branding: false,
      setup(editor) {
        byId.set(editorId, editor);
        editor.on('change input undo redo keyup', () => commitState(wrapper, editor));
        editor.on('remove', () => {
          byId.delete(editorId);
          instances.delete(wrapper);
        });
        editor.on('init', () => {
          const latest = model.value || source.value || '';
          if (editor.getContent() !== latest) editor.setContent(latest);
          commitState(wrapper, editor);
        });
      },
    }).catch(err => {
      instances.delete(wrapper);
      const error = wrapper.querySelector('[data-hugerte-error]');
      if (error) { error.textContent = 'Editor failed to load. Your content is preserved. ' + err.message; error.hidden = false; }
      source.hidden = false;
    });
  }

  function mountAll() {
    document.querySelectorAll('[data-bookly-hugerte]').forEach(mountOne);
    for (const [id, editor] of byId) {
      if (!document.getElementById(id)) editor.remove();
    }
  }

  function boot() {
    if (!window.hugerte) return;
    mountAll();
  }

  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('livewire:navigated', boot);
  document.addEventListener('livewire:initialized', boot);
  // Livewire morphing can add fields without a full navigation.
  if (window.Livewire?.hook) {
    window.Livewire.hook('morph.updated', boot);
  }
  if (document.readyState !== 'loading') boot();
  window.BooklyHugeRte = { mountAll: boot };
})();
