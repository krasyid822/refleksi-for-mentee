<?php
// Form Interaktif sudah dilebur ke Form Klasik (check-in opsional).
// Redirect permanen agar tautan/bookmark lama tetap bekerja.
header('Location: form.php', true, 301);
exit;
