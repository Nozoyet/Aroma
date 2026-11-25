<?php
/**
 * Chat flotante de Aroma (añadido independiente).
 * Se incluye con UNA línea en index.php, justo antes de </body>:
 *   <?php if (is_file(__DIR__ . '/chat_widget.php')) include __DIR__ . '/chat_widget.php'; ?>
 * Requiere que la sesión ya esté iniciada (index.php la inicia y crea $_SESSION['csrf']).
 */
$acToken = (string)($_SESSION['csrf'] ?? '');
?>
<link rel="stylesheet" href="css/chat.css">
<div id="aroma-chat" class="ac" data-endpoint="chat.php" data-img="mascota/" data-token="<?= htmlspecialchars($acToken, ENT_QUOTES, 'UTF-8') ?>">
  <div class="ac-panel" id="ac-panel" hidden role="dialog" aria-label="Chat de ayuda de Aroma">
    <div class="ac-cab"><span>Asistente Aroma</span><button type="button" class="ac-cerrar" aria-label="Cerrar chat">×</button></div>
    <div class="ac-msgs" role="log" aria-live="polite"></div>
    <div class="ac-chips"></div>
  </div>
  <button type="button" class="ac-mascota" aria-label="Abrir el chat de ayuda" aria-expanded="false" aria-controls="ac-panel">
    <img src="mascota/neutral.png" alt="" width="100" height="70">
  </button>
  <div class="ac-entrada" hidden>
    <input type="text" class="ac-input" maxlength="300" placeholder="Escribe tu pregunta…" autocomplete="off" aria-label="Tu pregunta">
    <button type="button" class="ac-enviar">Enviar</button>
  </div>
</div>
<script src="js/chat.js" defer></script>
