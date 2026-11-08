'use client';

import React, { useEffect, useRef, useState, useId } from 'react';

export interface PartnerRichTextEditorProps {
  id?: string;
  name?: string;
  value: string;
  onChange: (value: string) => void;
  onBlur?: () => void;
  placeholder?: string;
  disabled?: boolean;
  minHeight?: number;
  className?: string;
  'aria-invalid'?: boolean;
  'aria-describedby'?: string;
  'aria-label'?: string;
}

interface MediaAsset {
  id: number | string;
  url: string;
  label?: string;
  alt?: string;
}

interface HugeRteEditor {
  id: string;
  initialized?: boolean;
  getContent: () => string;
  setContent: (content: string) => void;
  remove: () => void;
  mode: {
    set: (mode: 'readonly' | 'design') => void;
  };
  on: (event: string, callback: (...args: unknown[]) => void) => void;
  windowManager: {
    alert: (msg: string) => void;
    open: (config: {
      title: string;
      body: unknown;
      buttons: Array<{ type: string; text: string; primary?: boolean }>;
      onSubmit: (api: { getData: () => { asset: string }; close: () => void }) => void;
    }) => void;
  };
}

interface HugeRteGlobal {
  init: (config: Record<string, unknown>) => Promise<HugeRteEditor[]>;
  get: (id: string) => HugeRteEditor | null;
  activeEditor?: HugeRteEditor | null;
}

let scriptPromise: Promise<HugeRteGlobal> | null = null;

function loadHugeRte(): Promise<HugeRteGlobal> {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Window is not available during server-side rendering.'));
  }
  const win = window as unknown as { hugerte?: HugeRteGlobal };
  if (win.hugerte && typeof win.hugerte.init === 'function') {
    return Promise.resolve(win.hugerte);
  }
  if (!scriptPromise) {
    scriptPromise = new Promise<HugeRteGlobal>((resolve, reject) => {
      const existing = document.querySelector('script[src*="hugerte.min.js"]');
      if (existing) {
        if (typeof win.hugerte?.init === 'function') {
          resolve(win.hugerte);
          return;
        }
        existing.addEventListener('load', () => {
          if (win.hugerte) resolve(win.hugerte);
          else reject(new Error('HugeRte script loaded without global instance'));
        });
        existing.addEventListener('error', (err) => reject(err));
        return;
      }
      const script = document.createElement('script');
      script.src = '/vendor/hugerte/hugerte.min.js';
      script.defer = true;
      script.onload = () => {
        if (win.hugerte) resolve(win.hugerte);
        else reject(new Error('HugeRte script loaded without global instance'));
      };
      script.onerror = (err) => reject(err);
      document.head.appendChild(script);
    });
  }
  return scriptPromise;
}

async function openPartnerMediaPicker(
  editor: HugeRteEditor | null,
  callback: (url: string, meta: { alt: string }) => void
) {
  if (!editor) return;
  try {
    const res = await fetch('/api/partner/hugerte/media', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
      throw new Error(`Partner media unavailable (${res.status})`);
    }
    const data = (await res.json()) as { assets?: MediaAsset[] };
    const assets = Array.isArray(data.assets) ? data.assets : [];
    if (assets.length === 0) {
      editor.windowManager.alert(
        'No existing tour images found. You can add images in the Media step or enter an image URL.'
      );
      return;
    }
    const items = assets.map((asset) => ({
      text: asset.label || asset.url,
      value: String(asset.id),
    }));
    const index = new Map<string, MediaAsset>(assets.map((asset) => [String(asset.id), asset]));

    editor.windowManager.open({
      title: 'Partner Tour Media',
      body: {
        type: 'panel',
        items: [
          {
            type: 'selectbox',
            name: 'asset',
            label: 'Choose Image',
            items,
          },
        ],
      },
      buttons: [
        { type: 'cancel', text: 'Cancel' },
        { type: 'submit', text: 'Insert Image', primary: true },
      ],
      onSubmit(api) {
        const asset = index.get(api.getData().asset);
        if (asset) {
          callback(asset.url, { alt: asset.alt || asset.label || '' });
        }
        api.close();
      },
    });
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Error loading partner media.';
    editor.windowManager.alert(message);
  }
}

export function PartnerRichTextEditor({
  id,
  name,
  value,
  onChange,
  onBlur,
  placeholder,
  disabled = false,
  minHeight = 300,
  className = '',
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
  'aria-label': ariaLabel,
}: PartnerRichTextEditorProps) {
  const generatedId = useId();
  const elementId = id || `partner-hugerte-${generatedId.replace(/[^a-zA-Z0-9_-]/g, '')}`;
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const editorRef = useRef<HugeRteEditor | null>(null);
  const valueRef = useRef<string>(value);
  const onChangeRef = useRef(onChange);
  const onBlurRef = useRef(onBlur);
  const disabledRef = useRef(disabled);
  const [editorReady, setEditorReady] = useState(false);

  useEffect(() => {
    onChangeRef.current = onChange;
    onBlurRef.current = onBlur;
    disabledRef.current = disabled;
  }, [onChange, onBlur, disabled]);

  // Keep valueRef in sync with the incoming value prop
  useEffect(() => {
    valueRef.current = value;
    if (editorRef.current && editorRef.current.initialized) {
      const currentContent = editorRef.current.getContent();
      if (currentContent !== (value || '')) {
        editorRef.current.setContent(value || '');
      }
    }
  }, [value]);

  // Handle disabled state changes
  useEffect(() => {
    if (editorRef.current && editorRef.current.initialized) {
      editorRef.current.mode.set(disabled ? 'readonly' : 'design');
    }
  }, [disabled]);

  // Mount official HugeRTE when component mounts in browser
  useEffect(() => {
    let isMounted = true;

    loadHugeRte()
      .then((hugerte) => {
        if (!isMounted || !textareaRef.current) return;

        // Clean up any existing editor instance bound to this target
        const existing = hugerte.get(elementId);
        if (existing) {
          existing.remove();
        }

        const isDark =
          document.documentElement.classList.contains('dark') ||
          (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

        hugerte.init({
          target: textareaRef.current,
          base_url: '/vendor/hugerte',
          suffix: '.min',
          height: minHeight,
          min_height: 250,
          resize: true,
          menubar: 'edit view insert format tools table help',
          plugins:
            'advlist anchor autolink autoresize charmap code codesample directionality emoticons fullscreen image link lists nonbreaking preview searchreplace table visualblocks wordcount',
          toolbar:
            'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | blockquote codesample | searchreplace preview code fullscreen | wordcount',
          toolbar_mode: 'sliding',
          skin: isDark ? 'oxide-dark' : 'oxide',
          content_css: isDark ? 'dark' : 'default',
          branding: false,
          promotion: false,
          convert_urls: false,
          relative_urls: false,
          image_caption: true,
          image_title: true,
          image_advtab: true,
          file_picker_types: 'image',
          file_picker_callback: (
            cb: (url: string, meta: { alt: string }) => void,
            _val: unknown,
            meta: { filetype?: string }
          ) => {
            if (meta?.filetype !== 'image') return;
            openPartnerMediaPicker(hugerte.get(elementId), cb);
          },
          setup(editor: HugeRteEditor) {
            editorRef.current = editor;

            editor.on('init', () => {
              if (isMounted) {
                const initial = valueRef.current || '';
                if (editor.getContent() !== initial) {
                  editor.setContent(initial);
                }
                if (disabledRef.current) {
                  editor.mode.set('readonly');
                }
                setEditorReady(true);
              }
            });

            editor.on('change input undo redo keyup setcontent', () => {
              const html = editor.getContent();
              if (html !== valueRef.current) {
                valueRef.current = html;
                onChangeRef.current(html);
              }
            });

            editor.on('blur', () => {
              onBlurRef.current?.();
            });

            editor.on('remove', () => {
              if (editorRef.current === editor) {
                editorRef.current = null;
              }
              if (isMounted) {
                setEditorReady(false);
              }
            });
          },
        });
      })
      .catch(() => {
        // Fallback: the native textarea remains visible and editable
        setEditorReady(false);
      });

    return () => {
      isMounted = false;
      if (editorRef.current) {
        editorRef.current.remove();
        editorRef.current = null;
      } else if (typeof window !== 'undefined') {
        const win = window as unknown as { hugerte?: HugeRteGlobal };
        win.hugerte?.get(elementId)?.remove();
      }
    };
  }, [elementId, minHeight]);

  return (
    <div className={`bookly-partner-editor relative ${className}`} data-testid="partner-rich-text-editor">
      <textarea
        ref={textareaRef}
        id={elementId}
        name={name}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onBlur={onBlur}
        placeholder={placeholder}
        disabled={disabled}
        aria-invalid={ariaInvalid}
        aria-describedby={ariaDescribedBy}
        aria-label={ariaLabel}
        rows={6}
        className={`w-full rounded-lg border bg-surface px-3.5 py-2.5 text-text outline-none transition-colors focus:border-bookly-navy dark:border-border dark:bg-surface-elevated ${
          editorReady ? 'hidden' : ''
        }`}
        style={editorReady ? { display: 'none' } : { minHeight: `${minHeight}px` }}
      />
    </div>
  );
}

export default PartnerRichTextEditor;
