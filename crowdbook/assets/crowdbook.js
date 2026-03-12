(function () {
  var crowdbookEditor = null;
  var markdownTextarea = null;

  function bindLikeButtons() {
    var likeButtons = document.querySelectorAll('.crowdbook-like-button');
    likeButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.disabled) {
          return;
        }

        var chapterId = btn.getAttribute('data-chapter-id');
        if (!chapterId || !window.crowdbook) {
          return;
        }

        var payload = new URLSearchParams({
          action: 'crowdbook_like',
          chapter_id: chapterId,
          nonce: window.crowdbook.nonce,
        });

        fetch(window.crowdbook.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: payload.toString(),
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (!data || !data.success) {
              if (data && data.data && data.data.message) {
                btn.title = data.data.message;
                if (String(data.data.message).toLowerCase().indexOf('bereits') !== -1) {
                  btn.disabled = true;
                  btn.classList.add('liked');
                  if (!btn.nextElementSibling || !btn.nextElementSibling.classList || !btn.nextElementSibling.classList.contains('crowdbook-like-note')) {
                    var note = document.createElement('span');
                    note.className = 'crowdbook-like-note';
                    note.textContent = 'Bereits geliked';
                    btn.insertAdjacentElement('afterend', note);
                  }
                }
              }
              return;
            }

            var count = data.data && typeof data.data.count !== 'undefined' ? data.data.count : null;
            if (count !== null) {
              var span = btn.querySelector('.like-count');
              if (span) {
                span.textContent = String(count);
              }
            }

            btn.disabled = true;
            btn.classList.add('liked');
          })
          .catch(function () {
            // ignore network errors
          });
      });
    });
  }

  function bindCopyButtons() {
    var copyButtons = document.querySelectorAll('.crowdbook-copy-link');
    copyButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url');
        if (!url || !navigator.clipboard) {
          return;
        }

        navigator.clipboard.writeText(url).then(function () {
          btn.textContent = window.crowdbook && window.crowdbook.copy_label ? window.crowdbook.copy_label : 'Link kopiert';
        });
      });
    });
  }

  function initMonacoEditor() {
    var editorContainer = document.getElementById('crowdbook_monaco_editor');
    var textarea = document.getElementById('crowdbook_markdown_content');
    markdownTextarea = textarea;

    if (!editorContainer || !textarea || typeof window.require === 'undefined') {
      return;
    }

    window.require.config({
      paths: {
        vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs',
      },
    });

    window.require(['vs/editor/editor.main'], function () {
      crowdbookEditor = window.monaco.editor.create(editorContainer, {
        value: textarea.value,
        language: 'markdown',
        minimap: { enabled: false },
        automaticLayout: true,
        fontSize: 14,
      });

      // Monaco becomes the source of truth; browser validation should not block hidden textarea.
      textarea.style.display = 'none';
      textarea.required = false;

      crowdbookEditor.onDidChangeModelContent(function () {
        textarea.value = crowdbookEditor.getValue();
      });

      var form = textarea.closest('form');
      if (!form) {
        return;
      }

      form.addEventListener('submit', function () {
        textarea.value = crowdbookEditor.getValue();
      });
    });
  }

  function insertTextIntoEditor(text) {
    if (!text) {
      return;
    }

    if (crowdbookEditor && window.monaco) {
      var selection = crowdbookEditor.getSelection();
      var range = selection || new window.monaco.Range(1, 1, 1, 1);
      crowdbookEditor.executeEdits('crowdbook-upload', [{ range: range, text: text + '\n' }]);
      crowdbookEditor.focus();
      return;
    }

    if (!markdownTextarea) {
      markdownTextarea = document.getElementById('crowdbook_markdown_content');
    }
    if (!markdownTextarea) {
      return;
    }

    var start = markdownTextarea.selectionStart || 0;
    var end = markdownTextarea.selectionEnd || 0;
    var value = markdownTextarea.value || '';
    markdownTextarea.value = value.slice(0, start) + text + '\n' + value.slice(end);
    markdownTextarea.focus();
    markdownTextarea.selectionStart = markdownTextarea.selectionEnd = start + text.length + 1;
  }

  function bindUploadButton() {
    var uploadButton = document.getElementById('crowdbook_upload_button');
    var uploadInput = document.getElementById('crowdbook_image_upload');
    var uploadStatus = document.getElementById('crowdbook_upload_status');
    markdownTextarea = document.getElementById('crowdbook_markdown_content');

    if (!uploadButton || !uploadInput || !window.crowdbook) {
      return;
    }

    uploadButton.addEventListener('click', function () {
      if (!uploadInput.files || uploadInput.files.length === 0) {
        if (uploadStatus) {
          uploadStatus.textContent = 'Bitte zuerst ein Bild auswaehlen.';
        }
        return;
      }

      var file = uploadInput.files[0];
      var body = new FormData();
      body.append('action', 'crowdbook_upload_image');
      body.append('nonce', window.crowdbook.upload_nonce || '');
      body.append('image', file);

      uploadButton.disabled = true;
      if (uploadStatus) {
        uploadStatus.textContent = 'Upload laeuft...';
      }

      fetch(window.crowdbook.ajax_url, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.success || !data.data || !data.data.markdown) {
            var msg = data && data.data && data.data.message ? data.data.message : (window.crowdbook.upload_fail || 'Upload fehlgeschlagen.');
            if (uploadStatus) {
              uploadStatus.textContent = msg;
            }
            return;
          }

          insertTextIntoEditor(data.data.markdown);
          if (uploadStatus) {
            uploadStatus.textContent = data.data.message || window.crowdbook.upload_ok || 'Bild hochgeladen und eingefuegt.';
          }
          uploadInput.value = '';
        })
        .catch(function () {
          if (uploadStatus) {
            uploadStatus.textContent = window.crowdbook.upload_fail || 'Upload fehlgeschlagen.';
          }
        })
        .finally(function () {
          uploadButton.disabled = false;
        });
    });
  }

  function uploadCoverFile(file, statusEl, urlInput, previewWrap, previewImg) {
    if (!file || !window.crowdbook) {
      return;
    }

    var body = new FormData();
    body.append('action', 'crowdbook_upload_cover');
    body.append('nonce', window.crowdbook.cover_upload_nonce || '');
    body.append('cover', file);

    if (statusEl) {
      statusEl.textContent = 'Cover wird hochgeladen...';
    }

    fetch(window.crowdbook.ajax_url, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success || !data.data || !data.data.url) {
          var msg = data && data.data && data.data.message ? data.data.message : (window.crowdbook.cover_upload_fail || 'Cover-Upload fehlgeschlagen.');
          if (statusEl) {
            statusEl.textContent = msg;
          }
          return;
        }

        if (urlInput) {
          urlInput.value = data.data.url;
        }
        if (previewImg) {
          previewImg.src = data.data.url;
        }
        if (previewWrap) {
          previewWrap.style.display = '';
        }
        if (statusEl) {
          var dims = '';
          if (data.data.width && data.data.height) {
            dims = ' (' + data.data.width + 'x' + data.data.height + ' px)';
          }
          statusEl.textContent = (data.data.message || window.crowdbook.cover_upload_ok || 'Cover hochgeladen.') + dims;
        }
      })
      .catch(function () {
        if (statusEl) {
          statusEl.textContent = window.crowdbook.cover_upload_fail || 'Cover-Upload fehlgeschlagen.';
        }
      });
  }

  function bindCoverUpload() {
    var dropzone = document.getElementById('crowdbook_cover_dropzone');
    var fileInput = document.getElementById('crowdbook_cover_upload');
    var pickButton = document.getElementById('crowdbook_cover_pick_button');
    var statusEl = document.getElementById('crowdbook_cover_status');
    var urlInput = document.getElementById('crowdbook_book_cover');
    var previewWrap = document.getElementById('crowdbook_cover_preview_wrap');
    var previewImg = document.getElementById('crowdbook_cover_preview');

    if (!dropzone || !fileInput || !pickButton || !urlInput || !window.crowdbook) {
      return;
    }

    pickButton.addEventListener('click', function () {
      fileInput.click();
    });

    fileInput.addEventListener('change', function () {
      if (!fileInput.files || fileInput.files.length === 0) {
        return;
      }
      uploadCoverFile(fileInput.files[0], statusEl, urlInput, previewWrap, previewImg);
      fileInput.value = '';
    });

    urlInput.addEventListener('input', function () {
      var val = (urlInput.value || '').trim();
      if (val && previewImg) {
        previewImg.src = val;
      }
      if (previewWrap) {
        previewWrap.style.display = val ? '' : 'none';
      }
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function (e) {
        e.preventDefault();
        dropzone.classList.add('is-dragover');
      });
    });

    ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
      dropzone.addEventListener(eventName, function (e) {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
      });
    });

    dropzone.addEventListener('drop', function (e) {
      var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
      if (!files || files.length === 0) {
        return;
      }
      uploadCoverFile(files[0], statusEl, urlInput, previewWrap, previewImg);
    });
  }

  function initBooksIsotope() {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.isotope) {
      return;
    }

    var $ = window.jQuery;
    var $grid = $('.crowdbook-cards');
    if (!$grid.length || !$grid.find('.crowdbook-book-item').length) {
      return;
    }

    $grid.isotope({
      itemSelector: '.crowdbook-book-item',
      layoutMode: 'fitRows',
      getSortData: {
        title: function (itemElem) {
          return String($(itemElem).attr('data-title') || '').toLowerCase();
        },
        chapters: function (itemElem) {
          return parseInt($(itemElem).attr('data-chapters') || '0', 10);
        },
      },
      sortBy: 'title',
      sortAscending: {
        title: true,
        chapters: false,
      },
    });

    $('.crowdbook-books-filter-btn').on('click', function () {
      var $btn = $(this);
      var filterValue = String($btn.attr('data-filter') || '*');
      $('.crowdbook-books-filter-btn').removeClass('is-active');
      $btn.addClass('is-active');
      $grid.isotope({ filter: filterValue });
    });

    $('.crowdbook-books-sort-btn').on('click', function () {
      var $btn = $(this);
      var sortBy = String($btn.attr('data-sort-by') || 'title');
      $('.crowdbook-books-sort-btn').removeClass('is-active');
      $btn.addClass('is-active');
      $grid.isotope({ sortBy: sortBy });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindLikeButtons();
    bindCopyButtons();
    initMonacoEditor();
    bindUploadButton();
    bindCoverUpload();
    initBooksIsotope();
  });
})();
