/**
 * Convert <input type="file"> selections to base64 hidden fields so uploads
 * work on cPanel hosts where PHP's upload_tmp_dir / system /tmp is missing.
 */
window.DeshiMartUpload = {
  readFile(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(String(reader.result || '')); };
      reader.onerror = function () { reject(reader.error || new Error('Could not read file')); };
      reader.readAsDataURL(file);
    });
  },

  /** logo_file → logo_file_b64; images[] → images_b64[]; fields[x] → fields[x_b64] */
  fieldName(inputName, suffix) {
    if (/\[\]$/.test(inputName)) {
      return inputName.replace(/\[\]$/, '_' + suffix + '[]');
    }
    var nested = inputName.match(/^(.*)\[([^\]]+)\]$/);
    if (nested) {
      return nested[1] + '[' + nested[2] + '_' + suffix + ']';
    }
    return inputName + '_' + suffix;
  },

  trackingKey(inputName) {
    return (inputName || 'file').replace(/\[\]$/, '');
  },

  async injectBase64(form) {
    var inputs = Array.from(form.querySelectorAll('input[type="file"]'));
    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      if (!input.files || !input.files.length) continue;

      var track = this.trackingKey(input.name);
      form.querySelectorAll('[data-b64-for="' + track + '"]').forEach(function (el) { el.remove(); });

      var files = Array.from(input.files);
      for (var f = 0; f < files.length; f++) {
        var dataUrl = await this.readFile(files[f]);

        var b64 = document.createElement('input');
        b64.type = 'hidden';
        b64.name = this.fieldName(input.name, 'b64');
        b64.value = dataUrl;
        b64.setAttribute('data-b64-for', track);

        var name = document.createElement('input');
        name.type = 'hidden';
        name.name = this.fieldName(input.name, 'name');
        name.value = files[f].name || ('upload-' + f + '.jpg');
        name.setAttribute('data-b64-for', track);

        form.appendChild(b64);
        form.appendChild(name);
      }

      // Avoid PHP multipart staging (fails when upload_tmp_dir is missing)
      input.value = '';
      input.disabled = true;
    }
  },

  reenableFiles(form) {
    form.querySelectorAll('input[type="file"]').forEach(function (input) {
      input.disabled = false;
    });
  },
};

document.addEventListener('submit', function (e) {
  var form = e.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (!form.querySelector('input[type="file"]')) return;
  if (form.dataset.b64Ready === '1') {
    form.dataset.b64Ready = '0';
    return;
  }

  var hasFiles = Array.from(form.querySelectorAll('input[type="file"]')).some(function (input) {
    return !input.disabled && input.files && input.files.length > 0;
  });
  if (!hasFiles) return;

  e.preventDefault();
  e.stopPropagation();

  window.DeshiMartUpload.injectBase64(form)
    .then(function () {
      form.dataset.b64Ready = '1';
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    })
    .catch(function () {
      window.DeshiMartUpload.reenableFiles(form);
      alert('Could not read the selected image. Please try another file.');
    });
}, true);
