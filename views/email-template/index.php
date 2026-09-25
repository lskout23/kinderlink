<?php if (!empty($_GET['saved'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  if (typeof showToast === 'function') showToast('Το πρότυπο email αποθηκεύτηκε.', 'success');
  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, '', window.location.href.replace(/[?&]saved=1/, '').replace(/\?$/, ''));
  }
});
</script>
<?php endif; ?>

<div class="section-box">
  <div class="section-title">Πρότυπο Ημερήσιου Email</div>
  <div class="section-body">

    <div class="alert" style="background:#fef9e7;border:1px solid #f0c040;font-size:11px;padding:8px 12px;margin-bottom:16px;">
      <strong>Διαθέσιμα placeholders:</strong>
      |*name*| (Όνομα παιδιού) &bull;
      |*breakfast*| &bull; |*lunch*| &bull; |*mood*| &bull; |*sleep*| &bull;
      |*WC_txt*| &bull; |*activity*| &bull; |*comments*|<br>
      <strong>Κείμενο χωρίς badge:</strong>
      |*breakfast_text*| &bull; |*lunch_text*| &bull; |*mood_text*|<br>
      <strong>Νέα (χρώματα):</strong>
      |*breakfast_color*| &bull; |*lunch_color*| &bull; |*mood_color*|<br>
      <strong>Νέα (έτοιμα badges):</strong>
      |*breakfast_badge*| &bull; |*lunch_badge*| &bull; |*mood_badge*|
    </div>

    <form method="POST" action="<?= BASE_URL ?>/administration/email-template" data-track-changes>
      <input type="hidden" name="_token" value="<?= (new Controller)->csrfToken() ?>">

      <div class="form-group">
        <label>Θέμα Email</label>
        <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" style="min-width:400px;">
      </div>

      <div class="form-group">
        <label>Περιεχόμενο (HTML)</label>
      </div>

      <!-- Full-width TinyMCE editor -->
      <div style="margin-bottom:10px;">
        <textarea id="template_html" name="template_html"
          style="width:100%;"><?= htmlspecialchars($templateHtml) ?></textarea>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-accent">💾 Αποθήκευση</button>
      </div>
    </form>

    <!-- TinyMCE 5 from CDN (no API key required) -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@5.10.9/tinymce.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce-i18n@23.10.9/langs5/el.js"></script>
    <script>
    tinymce.init({
      selector: '#template_html',
      language: 'el',
      height: 600,
      menubar: 'file edit insert view format table tools',
      plugins: 'advlist autolink lists link image charmap preview searchreplace visualblocks code fullscreen insertdatetime media table paste wordcount print',
      toolbar: 'undo redo | styleselect | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code fullscreen',
      branding: false,
      promotion: false,
      setup: function(editor) {
        editor.on('change', function() { editor.save(); });
      }
    });

    // Always sync TinyMCE to textarea before submit
    document.querySelector('form').addEventListener('submit', function() {
      if (window.tinymce) tinymce.triggerSave();
    });
    </script>
  </div>
</div>
