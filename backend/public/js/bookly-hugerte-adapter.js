/* Bookly adapter for the official self-hosted HugeRTE (MIT). */
(function () {
  'use strict';
  const BASE = '/vendor/hugerte';
  const instances = new WeakMap();
  const byId = new Map();
  let scriptPromise = null;

  function loadHugeRteScript() {
    if (window.hugerte && window.hugerte.init) {
      return Promise.resolve(window.hugerte);
    }
    if (!scriptPromise) {
      scriptPromise = new Promise(function (resolve, reject) {
        const existing = document.querySelector('script[src*="hugerte.min.js"]');
        if (existing) {
          if (window.hugerte && window.hugerte.init) {
            resolve(window.hugerte);
            return;
          }
          existing.addEventListener('load', function () { resolve(window.hugerte); });
          existing.addEventListener('error', function (err) { reject(err); });
          return;
        }
        const script = document.createElement('script');
        script.src = BASE + '/hugerte.min.js';
        script.defer = true;
        script.onload = function () { resolve(window.hugerte); };
        script.onerror = function (err) { reject(err); };
        document.head.appendChild(script);
      });
    }
    return scriptPromise;
  }

  function getModel(wrapper, editorId) {
    if (editorId) {
      const cleanId = String(editorId).replace(/^hugerte-/, '');
      const exact = document.querySelector('textarea[data-hugerte-model="' + cleanId + '"]');
      if (exact) return exact;
    }
    const target = wrapper.nextElementSibling;
    if (target && target.matches('textarea[data-hugerte-model]')) return target;
    const parent = wrapper.parentElement;
    if (parent) {
      const model = parent.querySelector('textarea[data-hugerte-model]');
      if (model) return model;
    }
    return null;
  }

  function commitState(wrapper, editor) {
    const model = getModel(wrapper, editor ? editor.id : null);
    if (!model) return;
    const html = editor.getContent();
    if (model.value === html) return;
    const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set;
    setter.call(model, html);
    model.dispatchEvent(new Event('input', { bubbles: true }));
    model.dispatchEvent(new Event('change', { bubbles: true }));
  }

  async function openMediaPicker(editor, callback) {
    try {
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
    } catch (err) {
      editor.windowManager.alert(err.message);
    }
  }

  function mountOne(wrapper) {
    if (!wrapper || instances.has(wrapper) || !document.contains(wrapper)) return;
    const source = wrapper.querySelector('[data-hugerte-source]');
    const model = getModel(wrapper);
    if (!source || !model) return;

    instances.set(wrapper, true);

    loadHugeRteScript().then(function (hugerte) {
      if (!document.contains(wrapper)) {
        instances.delete(wrapper);
        return;
      }
      const editorId = source.id;
      if (hugerte.get(editorId)) {
        hugerte.get(editorId).remove();
      }

      const isDark = document.documentElement.classList.contains('dark') ||
                     (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

      hugerte.init({
        target: source,
        base_url: BASE,
        suffix: '.min',
        height: 480,
        min_height: 300,
        resize: true,
        menubar: 'edit view insert format tools table help',
        plugins: 'advlist anchor autolink autoresize charmap code codesample directionality emoticons fullscreen image link lists nonbreaking preview searchreplace table visualblocks wordcount',
        toolbar: wrapper.dataset.editorToolbar || 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | blockquote codesample | searchreplace preview code fullscreen | wordcount',
        toolbar_mode: 'sliding',
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default',
        image_caption: true,
        image_title: true,
        image_advtab: true,
        automatic_uploads: false,
        file_picker_types: 'image',
        file_picker_callback: function (cb, _value, meta) {
          if (meta.filetype !== 'image') return;
          openMediaPicker(hugerte.get(editorId), cb).catch(function (err) {
            hugerte.get(editorId)?.windowManager.alert(err.message);
          });
        },
        convert_urls: false,
        relative_urls: false,
        promotion: false,
        branding: false,
        setup(editor) {
          byId.set(editorId, editor);
          editor.on('change Change input Input undo redo keyup KeyUp setcontent SetContent NodeChange', function () { commitState(wrapper, editor); });
          editor.on('remove', function () {
            byId.delete(editorId);
            instances.delete(wrapper);
          });
          editor.on('init', function () {
            const latest = model.value || source.value || '';
            if (editor.getContent() !== latest) editor.setContent(latest);
            commitState(wrapper, editor);
          });
        },
      }).catch(function (err) {
        instances.delete(wrapper);
        const error = wrapper.querySelector('[data-hugerte-error]');
        if (error) {
          error.textContent = 'Editor failed to load. Your content is preserved. ' + err.message;
          error.hidden = false;
        }
        source.style.visibility = 'visible';
        source.style.height = 'auto';
      });
    }).catch(function (err) {
      instances.delete(wrapper);
      const error = wrapper.querySelector('[data-hugerte-error]');
      if (error) {
        error.textContent = 'Failed to load editor script. ' + err.message;
        error.hidden = false;
      }
      source.style.visibility = 'visible';
      source.style.height = 'auto';
    });
  }

  function unmountOne(editorId) {
    if (window.hugerte && editorId) {
      const ed = window.hugerte.get(editorId);
      if (ed) {
        ed.remove();
      }
    }
    byId.delete(editorId);
  }

  function mountAll() {
    document.querySelectorAll('[data-bookly-hugerte]').forEach(mountOne);
    for (const [id, editor] of byId) {
      if (!document.getElementById(id)) editor.remove();
    }
  }

  function registerAlpine() {
    if (window.Alpine && !window.Alpine.__booklyHugeRteRegistered) {
      window.Alpine.__booklyHugeRteRegistered = true;
      window.Alpine.data('booklyHugeRteEditor', function (config) {
        return {
          editorId: config && config.editorId ? config.editorId : null,
          statePath: config && config.statePath ? config.statePath : null,
          init: function () {
            const el = this.$el;
            this.$nextTick(function () {
              mountOne(el);
            });
          },
          destroy: function () {
            if (this.editorId) {
              unmountOne(this.editorId);
            }
          }
        };
      });
    }
  }

  registerAlpine();
  document.addEventListener('alpine:init', registerAlpine);
  document.addEventListener('DOMContentLoaded', mountAll);
  document.addEventListener('livewire:navigated', mountAll);
  document.addEventListener('livewire:initialized', mountAll);
  if (window.Livewire && window.Livewire.hook) {
    window.Livewire.hook('morph.updated', mountAll);
  }
  if (document.readyState !== 'loading') mountAll();

  window.BooklyHugeRte = {
    mount: mountOne,
    unmount: unmountOne,
    mountAll: mountAll,
    commit: function (editor) {
      if (!editor) return;
      const id = typeof editor === 'string' ? editor : editor.id;
      const ed = typeof editor === 'string' && window.hugerte ? window.hugerte.get(id) : editor;
      if (!ed) return;
      const source = document.getElementById(id);
      const wrapper = source ? source.closest('[data-bookly-hugerte]') : null;
      if (wrapper) commitState(wrapper, ed);
    }
  };
})();
