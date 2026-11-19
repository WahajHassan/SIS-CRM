/*
 * Small rich text editor for the email composer and template screens.
 *
 * This replaces TinyMCE, which was loaded from cdn.tiny.cloud with an API key that
 * belonged to someone else's account. Cloud keys are tied to approved domains, so the
 * editor would have stopped working the moment the app moved to its production
 * domain, and the previous code hid that warning with CSS instead of fixing it.
 *
 * Everything here is built on contenteditable, which every browser supports, so there
 * is no key, no CDN and nothing to break when the site moves or the network is down.
 *
 * The original <textarea> is kept in the form and updated as the user types, so the
 * surrounding forms post exactly the same field they always did.
 *
 * Usage:
 *   RichEditor.attach('#email_body');            // one or more selectors
 *   RichEditor.setContent('email_body', html);   // load a template into it
 *   RichEditor.getContent('email_body');
 */
(function (window, document) {
    'use strict';

    var instances = {};
    var stylesInjected = false;

    // The app may be served from a subfolder (systematicitsolutions.com/crm), so a
    // path starting with "/" would miss the app entirely. This script is always
    // loaded as <base>/js/rich-editor.js, so its own src gives us the base back.
    var BASE = (function () {
        var el = document.currentScript ||
            document.querySelector('script[src*="rich-editor.js"]');
        if (!el) {
            return '';
        }
        // Resolve against the page in case the src is relative, then drop "/js/...".
        var src = new URL(el.getAttribute('src'), window.location.href).pathname;
        return src.replace(/\/js\/rich-editor\.js.*$/, '');
    })();

    var STYLES = [
        '.re-wrap{border:1px solid var(--border,#e2e8f0);border-radius:10px;overflow:hidden;background:#fff}',
        '.re-toolbar{display:flex;flex-wrap:wrap;gap:2px;padding:6px;border-bottom:1px solid var(--border,#e2e8f0);background:#f8fafc}',
        '.re-btn{border:none;background:none;cursor:pointer;padding:6px 9px;border-radius:6px;font-size:14px;line-height:1;color:#334155;min-width:32px}',
        '.re-btn:hover{background:#e2e8f0}',
        '.re-btn.is-active{background:#e2e8f0;color:#0f172a}',
        '.re-sep{width:1px;background:#e2e8f0;margin:4px 4px}',
        '.re-area{padding:12px;min-height:200px;overflow-y:auto;outline:none;font-family:inherit;font-size:14px;line-height:1.6;color:#0f172a;background:#fff}',
        '.re-area:empty:before{content:attr(data-placeholder);color:#94a3b8}',
        '.re-area img{max-width:100%;height:auto}',
        '.re-source{display:none;width:100%;border:none;padding:12px;font-family:ui-monospace,Consolas,monospace;font-size:13px;outline:none;resize:vertical;background:#0f172a;color:#e2e8f0}',
        '.re-wrap.re-showing-source .re-area{display:none}',
        '.re-wrap.re-showing-source .re-source{display:block}'
    ].join('');

    function injectStyles() {
        if (stylesInjected) {
            return;
        }
        var style = document.createElement('style');
        style.textContent = STYLES;
        document.head.appendChild(style);
        stylesInjected = true;
    }

    /* Anything that could run when the body is rendered back is taken out. */
    function clean(html) {
        var holder = document.createElement('div');
        holder.innerHTML = html || '';

        holder.querySelectorAll('script,style,iframe,object,embed,link,meta').forEach(function (node) {
            node.remove();
        });

        holder.querySelectorAll('*').forEach(function (node) {
            Array.prototype.slice.call(node.attributes).forEach(function (attr) {
                var name = attr.name.toLowerCase();
                var value = (attr.value || '').replace(/\s+/g, '').toLowerCase();

                if (name.indexOf('on') === 0 || value.indexOf('javascript:') === 0) {
                    node.removeAttribute(attr.name);
                }
            });
        });

        return holder.innerHTML;
    }

    var BUTTONS = [
        { cmd: 'bold', label: '<b>B</b>', title: 'Bold' },
        { cmd: 'italic', label: '<i>I</i>', title: 'Italic' },
        { cmd: 'underline', label: '<u>U</u>', title: 'Underline' },
        { sep: true },
        { cmd: 'formatBlock', value: 'h2', label: 'H2', title: 'Heading' },
        { cmd: 'formatBlock', value: 'p', label: '¶', title: 'Paragraph' },
        { sep: true },
        { cmd: 'insertUnorderedList', label: '&bull;&nbsp;&#8801;', title: 'Bullet list' },
        { cmd: 'insertOrderedList', label: '1.&nbsp;&#8801;', title: 'Numbered list' },
        { sep: true },
        { cmd: 'justifyLeft', label: '&#8801;', title: 'Align left' },
        { cmd: 'justifyCenter', label: '&#8776;', title: 'Align centre' },
        { sep: true },
        { cmd: 'createLink', label: '&#128279;', title: 'Insert link', prompt: 'Link address (https://...)' },
        { cmd: 'unlink', label: '&#9986;', title: 'Remove link' },
        { sep: true },
        { image: true, label: '&#128247;', title: 'Insert image' },
        { sep: true },
        { cmd: 'removeFormat', label: '&#10005;', title: 'Clear formatting' },
        { source: true, label: '&lt;/&gt;', title: 'Edit HTML source' }
    ];

    function build(textarea) {
        var id = textarea.id;

        if (!id || instances[id]) {
            return;
        }

        injectStyles();

        var height = textarea.style.height || '300px';

        var wrap = document.createElement('div');
        wrap.className = 're-wrap';

        var toolbar = document.createElement('div');
        toolbar.className = 're-toolbar';

        var area = document.createElement('div');
        area.className = 're-area';
        area.contentEditable = 'true';
        area.style.height = height;
        area.setAttribute('data-placeholder', textarea.getAttribute('placeholder') || 'Write your message...');
        area.innerHTML = clean(textarea.value);

        var source = document.createElement('textarea');
        source.className = 're-source';
        source.style.height = height;

        BUTTONS.forEach(function (item) {
            if (item.sep) {
                var sep = document.createElement('span');
                sep.className = 're-sep';
                toolbar.appendChild(sep);
                return;
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 're-btn';
            button.innerHTML = item.label;
            button.title = item.title;

            button.addEventListener('click', function () {
                if (item.source) {
                    toggleSource(id);
                    return;
                }

                if (item.image) {
                    pickAndInsertImage(id);
                    return;
                }

                area.focus();

                var value = item.value || null;

                if (item.prompt) {
                    value = window.prompt(item.prompt, 'https://');
                    if (!value) {
                        return;
                    }
                }

                document.execCommand(item.cmd, false, value);
                sync(id);
                refreshState(id);
            });

            toolbar.appendChild(button);
        });

        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(toolbar);
        wrap.appendChild(area);
        wrap.appendChild(source);
        wrap.appendChild(textarea);

        instances[id] = { textarea: textarea, wrap: wrap, area: area, source: source, toolbar: toolbar };

        area.addEventListener('input', function () { sync(id); });
        area.addEventListener('keyup', function () { refreshState(id); });
        area.addEventListener('mouseup', function () { refreshState(id); });
        source.addEventListener('input', function () { textarea.value = source.value; });

        /* Pasted content is cleaned before it lands in the document. */
        area.addEventListener('paste', function (event) {
            event.preventDefault();
            var data = event.clipboardData || window.clipboardData;
            var html = data.getData('text/html');
            var text = data.getData('text/plain');

            if (html) {
                document.execCommand('insertHTML', false, clean(html));
            } else {
                document.execCommand('insertText', false, text);
            }

            sync(id);
        });

        /* A last sync on submit covers changes made without an input event firing. */
        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (!instances[id].wrap.classList.contains('re-showing-source')) {
                    sync(id);
                }
            });
        }

        sync(id);
    }

    function sync(id) {
        var editor = instances[id];
        if (editor) {
            editor.textarea.value = editor.area.innerHTML;
        }
    }

    function refreshState(id) {
        var editor = instances[id];
        if (!editor) {
            return;
        }

        var buttons = editor.toolbar.querySelectorAll('.re-btn');
        var index = 0;

        BUTTONS.forEach(function (item) {
            if (item.sep) {
                return;
            }

            var button = buttons[index++];
            if (!button || item.source || item.prompt || !item.cmd) {
                return;
            }

            try {
                button.classList.toggle('is-active', document.queryCommandState(item.cmd));
            } catch (e) {
                /* queryCommandState throws for commands it does not track */
            }
        });
    }

    function pickAndInsertImage(id) {
        var editor = instances[id];
        if (!editor) {
            return;
        }

        var savedRange = null;
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0 && editor.area.contains(sel.anchorNode)) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';
        document.body.appendChild(input);

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            document.body.removeChild(input);
            if (!file) {
                return;
            }

            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            var formData = new FormData();
            formData.append('image', file);

            fetch(BASE + '/admin/rich-editor/upload-image', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.url) {
                        window.alert(data.message || 'Image upload failed.');
                        return;
                    }

                    editor.area.focus();
                    var sel2 = window.getSelection();
                    if (savedRange) {
                        sel2.removeAllRanges();
                        sel2.addRange(savedRange);
                    }
                    document.execCommand('insertImage', false, data.url);
                    sync(id);
                })
                .catch(function () {
                    window.alert('Image upload failed.');
                });
        });

        input.click();
    }

    function toggleSource(id) {
        var editor = instances[id];
        if (!editor) {
            return;
        }

        if (editor.wrap.classList.contains('re-showing-source')) {
            editor.area.innerHTML = clean(editor.source.value);
            editor.wrap.classList.remove('re-showing-source');
            sync(id);
        } else {
            editor.source.value = editor.area.innerHTML;
            editor.wrap.classList.add('re-showing-source');
        }
    }

    window.RichEditor = {
        attach: function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                if (node.tagName === 'TEXTAREA') {
                    build(node);
                }
            });
        },

        setContent: function (id, html) {
            var editor = instances[id];
            if (!editor) {
                return;
            }

            editor.area.innerHTML = clean(html);
            editor.source.value = editor.area.innerHTML;
            sync(id);
        },

        getContent: function (id) {
            var editor = instances[id];
            return editor ? editor.area.innerHTML : '';
        }
    };
})(window, document);
