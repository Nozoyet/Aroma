/* Chat flotante de Aroma. Si algo falla aquí, el widget se retira y la página sigue igual. */
(() => {
  'use strict';
  const raiz = document.getElementById('aroma-chat');
  if (!raiz) return;
  try { iniciar(raiz); } catch (e) { console.error('[Aroma chat]', e); raiz.remove(); }

  function iniciar(raiz) {
    const EMO = new Set(['neutral', 'think', 'alert', 'ok', 'happy', 'sad', 'cry', 'angry',
                         'shocked', 'sleepy', 'confused', 'shy', 'love']);
    const endpoint = raiz.dataset.endpoint, base = raiz.dataset.img, token = raiz.dataset.token;
    const $ = (s) => raiz.querySelector(s);
    const panel = $('.ac-panel'), msgs = $('.ac-msgs'), chips = $('.ac-chips'), cerrar = $('.ac-cerrar'),
          btnMascota = $('.ac-mascota'), img = $('.ac-mascota img'), fila = $('.ac-entrada'),
          input = $('.ac-input'), enviarBtn = $('.ac-enviar');
    const SUGERENCIAS = ['¿Qué vence hoy?', '¿Cuánto pollo queda?', '¿Por qué la última decisión?', 'Explícame la R13'];
    let abierto = false, ocupado = false, saludado = false, tVolver = null, tSueno = null;

    // Pose de la mascota: solo valores de la lista fija.
    function pose(e) {
      if (!EMO.has(e)) e = 'neutral';
      if (img.dataset.pose === e) return;
      img.dataset.pose = e;
      img.src = base + e + '.png';
      img.classList.remove('ac-salto'); void img.offsetWidth; img.classList.add('ac-salto');
    }
    function despertar() {
      clearTimeout(tSueno);
      if (img.dataset.pose === 'sleepy') pose('neutral');
      tSueno = setTimeout(() => { if (!ocupado) pose('sleepy'); }, 180000);   // 3 min sin actividad
    }
    function volverNeutral(ms) {
      clearTimeout(tVolver);
      tVolver = setTimeout(() => { if (!ocupado) pose('neutral'); }, ms);
    }

    function agregar(texto, quien) {
      const d = document.createElement('div');
      d.className = 'ac-msg ' + quien;
      d.textContent = texto;                       // nunca innerHTML: el texto es dato, no HTML
      msgs.appendChild(d);
      msgs.scrollTop = msgs.scrollHeight;
    }

    function alternar(abrir) {
      abierto = abrir;
      raiz.classList.toggle('abierto', abrir);
      panel.hidden = !abrir; fila.hidden = !abrir;
      btnMascota.setAttribute('aria-expanded', String(abrir));
      if (abrir) {
        if (!saludado) {
          saludado = true;
          agregar('¡Hola! Soy el onigiri de Aroma 🍙. Pregúntame por el stock, los lotes que vencen, las decisiones del sistema o las reglas.', 'bot');
          SUGERENCIAS.forEach((s) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = s;
            b.addEventListener('click', () => enviar(s));
            chips.appendChild(b);
          });
        }
        pose('ok'); volverNeutral(1200); input.focus();
      }
      despertar();
    }

    async function enviar(texto) {
      texto = (texto || '').trim();
      if (!texto || ocupado) return;
      ocupado = true; enviarBtn.disabled = true; input.value = '';
      agregar(texto, 'yo'); pose('think'); despertar();
      const ctl = new AbortController(), corte = setTimeout(() => ctl.abort(), 15000);
      let emocion = 'neutral';
      try {
        const res = await fetch(endpoint, {
          method: 'POST', credentials: 'same-origin', signal: ctl.signal,
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
          body: JSON.stringify({ mensaje: texto }),
        });
        let d = null; try { d = await res.json(); } catch (_) { /* respuesta no JSON */ }
        if (!d || typeof d.texto !== 'string') throw new Error('respuesta inválida');
        agregar(d.texto, 'bot'); emocion = d.emocion;
      } catch (e) {
        agregar('Uy, no pude responder ahora. Inténtalo de nuevo en un momento.', 'bot'); emocion = 'cry';
      } finally {
        clearTimeout(corte); ocupado = false; enviarBtn.disabled = false; input.focus();
      }
      pose(emocion);
      volverNeutral(emocion === 'alert' || emocion === 'angry' ? 9000 : 6000);
    }

    btnMascota.addEventListener('click', () => alternar(!abierto));
    cerrar.addEventListener('click', () => { alternar(false); btnMascota.focus(); });
    enviarBtn.addEventListener('click', () => enviar(input.value));
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); enviar(input.value); } });
    raiz.addEventListener('keydown', (e) => { if (e.key === 'Escape' && abierto) { alternar(false); btnMascota.focus(); } });
    raiz.addEventListener('pointerdown', despertar);

    // Precarga las 13 poses cuando el navegador esté libre.
    const precargar = () => EMO.forEach((e) => { new Image().src = base + e + '.png'; });
    (window.requestIdleCallback || ((f) => setTimeout(f, 1500)))(precargar);
    despertar();
  }
})();
