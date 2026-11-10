// Sonidos de la heladera. Se generan con Web Audio (no hay archivos de audio ni licencias).
// Son 9 sonidos distintos, bajos y con una pequeña variación de tono en cada vez para que no suenen repetidos.
// Arrancan PRENDIDOS (salvo que el sistema pida "reducir movimiento"); se apagan con el imán del parlante
// y la elección queda guardada en el navegador. Los navegadores no dejan sonar nada hasta el primer clic.
// Desde otros archivos: window.sonar('iman' | 'hoja' | 'pop-arriba' | 'pop-abajo' | 'birome' |
//   'nota-despega' | 'nota-pega' | 'sticker-pega' | 'sticker-arranca' | 'clic' | 'iman-grave' | 'error', tono?)
(() => {
  const CLAVE = 'cooki.sonidos';
  // Volumen general (0 a 100). 50 = sutil pero audible. Con cuenta viene de la base (<html data-volumen>);
  // sin cuenta, de este navegador. La ganancia real es volumen / 100 * 1.1 (50 → 0.55).
  const CLAVE_VOLUMEN = 'cooki.volumen';
  const conCuenta = document.documentElement.dataset.volumen !== undefined;
  let volumen = 50;
  try {
    volumen = Number(conCuenta ? document.documentElement.dataset.volumen : (localStorage.getItem(CLAVE_VOLUMEN) ?? 50));
  } catch {
    // Sin almacenamiento: queda en 50
  }
  if (!Number.isFinite(volumen)) volumen = 50;
  const ganancia = () => (Math.max(0, Math.min(100, volumen)) / 100) * 1.1;
  let guardado = null;
  try {
    guardado = localStorage.getItem(CLAVE);
  } catch {
    // Navegación privada o almacenamiento bloqueado: se usa el valor por defecto
  }
  const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let activo = guardado === null ? !reducir : guardado === '1';

  let contexto = null;
  let salida = null;
  function audio() {
    if (!contexto) {
      const Contexto = window.AudioContext || window.webkitAudioContext;
      if (!Contexto) return null;
      contexto = new Contexto();
      salida = contexto.createGain();
      salida.gain.value = ganancia();
      salida.connect(contexto.destination);
    }
    if (contexto.state === 'suspended') contexto.resume();
    return contexto;
  }

  // Variación chica (±4 %) para que el mismo sonido no sea idéntico cada vez
  const variar = (valor) => valor * (0.96 + Math.random() * 0.08);

  function tono(c, { frecuencia, duracion, tipo = 'sine', volumen = 0.12, hasta = null, demora = 0, ataque = 0.006 }) {
    const inicio = c.currentTime + demora;
    const osc = c.createOscillator();
    const vol = c.createGain();
    osc.type = tipo;
    osc.frequency.setValueAtTime(variar(frecuencia), inicio);
    if (hasta) osc.frequency.exponentialRampToValueAtTime(variar(hasta), inicio + duracion);
    vol.gain.setValueAtTime(0.0001, inicio);
    vol.gain.exponentialRampToValueAtTime(volumen, inicio + ataque);
    vol.gain.exponentialRampToValueAtTime(0.0001, inicio + duracion);
    osc.connect(vol).connect(salida);
    osc.start(inicio);
    osc.stop(inicio + duracion + 0.03);
  }

  // Ruido filtrado: papel, birome, sticker
  function ruido(c, { duracion, volumen = 0.12, filtro = 2000, q = 1, demora = 0, tipo = 'bandpass', subir = false }) {
    const inicio = c.currentTime + demora;
    const muestras = Math.max(1, Math.floor(c.sampleRate * duracion));
    const buffer = c.createBuffer(1, muestras, c.sampleRate);
    const datos = buffer.getChannelData(0);
    for (let i = 0; i < muestras; i++) {
      const avance = i / muestras;
      const envolvente = subir ? Math.sin(Math.PI * avance) : 1 - avance;
      datos[i] = (Math.random() * 2 - 1) * envolvente;
    }
    const fuente = c.createBufferSource();
    fuente.buffer = buffer;
    const filtroNodo = c.createBiquadFilter();
    filtroNodo.type = tipo;
    filtroNodo.frequency.value = variar(filtro);
    filtroNodo.Q.value = q;
    const vol = c.createGain();
    vol.gain.value = volumen;
    fuente.connect(filtroNodo).connect(vol).connect(salida);
    fuente.start(inicio);
  }

  // Tonos de cada sección del menú (una escala pentatónica: siempre suenan bien juntos)
  const ESCALA = [392, 440, 523, 587, 659, 784];

  const SONIDOS = {
    // Cambiar de sección: un imán que se despega y se pega, con un tono por sección
    iman: (c, n = 0) => {
      ruido(c, { duracion: 0.03, volumen: 0.05, filtro: 5200 });
      tono(c, { frecuencia: ESCALA[n % ESCALA.length], duracion: 0.16, tipo: 'triangle', volumen: 0.09, demora: 0.04 });
      tono(c, { frecuencia: 140, duracion: 0.06, tipo: 'sine', volumen: 0.12, hasta: 90, demora: 0.04 });
    },
    // Abrir una receta: una hoja que se da vuelta
    hoja: (c) => ruido(c, { duracion: 0.26, volumen: 0.16, filtro: 2600, q: 0.7, subir: true }),
    // Elegir y soltar en el selector
    'pop-arriba': (c) => tono(c, { frecuencia: 480, duracion: 0.09, volumen: 0.1, hasta: 820 }),
    'pop-abajo': (c) => tono(c, { frecuencia: 700, duracion: 0.09, volumen: 0.08, hasta: 380 }),
    // Tildar un paso: trazo de birome
    birome: (c) => {
      ruido(c, { duracion: 0.07, volumen: 0.07, filtro: 6000, q: 3, subir: true });
      ruido(c, { duracion: 0.11, volumen: 0.07, filtro: 7000, q: 3, subir: true, demora: 0.07 });
    },
    // Nota del editor: se despega (sube) y se vuelve a pegar (baja)
    'nota-despega': (c) => {
      ruido(c, { duracion: 0.18, volumen: 0.14, filtro: 3400, subir: true });
      tono(c, { frecuencia: 300, duracion: 0.14, volumen: 0.05, hasta: 520 });
    },
    'nota-pega': (c) => {
      ruido(c, { duracion: 0.12, volumen: 0.1, filtro: 2200 });
      tono(c, { frecuencia: 260, duracion: 0.08, volumen: 0.07, hasta: 150, demora: 0.06 });
    },
    // Despensa: pegar y arrancar un sticker
    'sticker-pega': (c) => {
      ruido(c, { duracion: 0.05, volumen: 0.1, filtro: 1400 });
      tono(c, { frecuencia: 523, duracion: 0.1, volumen: 0.08, demora: 0.03 });
      tono(c, { frecuencia: 784, duracion: 0.14, volumen: 0.07, demora: 0.1 });
    },
    'sticker-arranca': (c) => {
      ruido(c, { duracion: 0.2, volumen: 0.13, filtro: 4500, q: 0.8, subir: true });
      tono(c, { frecuencia: 620, duracion: 0.16, volumen: 0.05, hasta: 240, demora: 0.04 });
    },
    // Pestañas, filtros, botones comunes
    clic: (c) => tono(c, { frecuencia: 1800, duracion: 0.035, tipo: 'triangle', volumen: 0.05 }),
    // Cambiar color o disposición: imán grande y grave
    'iman-grave': (c) => {
      tono(c, { frecuencia: 110, duracion: 0.12, tipo: 'sine', volumen: 0.16, hasta: 70 });
      ruido(c, { duracion: 0.03, volumen: 0.05, filtro: 3000 });
    },
    // Formulario con datos mal cargados: dos notas suaves que bajan
    error: (c) => {
      tono(c, { frecuencia: 440, duracion: 0.13, volumen: 0.08 });
      tono(c, { frecuencia: 330, duracion: 0.2, volumen: 0.08, demora: 0.12 });
    },
    // Prender los sonidos
    tic: (c) => tono(c, { frecuencia: 1320, duracion: 0.06, volumen: 0.08 }),
    // Cinco notas suaves que suben, una por letra de "COOKI"
    marca: (c) => {
      [0, 4, 7, 9, 12].forEach((paso, i) => {
        tono(c, { frecuencia: 523 * 2 ** (paso / 12), duracion: 0.12, tipo: 'triangle', volumen: 0.035, demora: i * 0.055 });
      });
    },
  };

  window.sonar = (nombre, n) => {
    if (!activo || !SONIDOS[nombre]) return;
    const c = audio();
    if (c) SONIDOS[nombre](c, n);
  };

  // El nombre "COOKI": al pasar el mouse, una nota por letra (como la ola de las letras).
  // Una vez cada 1,5 s como mucho; el navegador recién deja sonar después del primer clic en la página.
  let ultimaMarca = 0;
  document.querySelector('.marca')?.addEventListener('mouseenter', () => {
    if (Date.now() - ultimaMarca < 1500) return;
    ultimaMarca = Date.now();
    window.sonar('marca');
  });

  // Barra de volumen: cambia al instante; al soltarla se guarda (en la cuenta o en el navegador)
  for (const barra of document.querySelectorAll('.volumen__barra')) {
    const etiqueta = barra.closest('.volumen');
    const valor = etiqueta.querySelector('.volumen__valor');
    etiqueta.hidden = false;
    barra.value = String(volumen);
    valor.textContent = String(volumen);
    barra.addEventListener('input', () => {
      volumen = Number(barra.value);
      valor.textContent = barra.value;
      if (salida) salida.gain.value = ganancia();
    });
    barra.addEventListener('change', () => {
      window.sonar('tic');
      if (barra.form) {
        const datos = new FormData(barra.form);
        datos.set('volumen', barra.value);
        fetch(barra.form.action, { method: 'POST', body: datos, headers: { 'X-Requested-With': 'fetch' } });
      } else {
        try {
          localStorage.setItem(CLAVE_VOLUMEN, barra.value);
        } catch {
          // Si no se puede guardar, vale solo para esta página
        }
      }
    });
  }

  // El imán del parlante (en el menú de la cuenta, o en el desplegable del parlante si no hay nadie ingresado)
  const boton = document.querySelector('.iman-sonido');
  if (boton) {
    const texto = boton.querySelector('.iman-sonido__texto');
    const mostrar = () => {
      boton.setAttribute('aria-pressed', String(activo));
      boton.title = activo ? 'Apagar los sonidos' : 'Prender los sonidos';
      if (texto) texto.textContent = activo ? 'Sonidos prendidos' : 'Sonidos apagados';
    };
    boton.hidden = false;
    mostrar();
    boton.addEventListener('click', () => {
      activo = !activo;
      try {
        localStorage.setItem(CLAVE, activo ? '1' : '0');
      } catch {
        // Si no se puede guardar, vale solo para esta página
      }
      mostrar();
      window.sonar('tic');
    });
  }

  // ── Qué suena con cada clic. Un solo sonido por clic: gana la primera regla que coincide.
  //    null = el sonido lo pone otro archivo (despensa.js, cocina.js, receta.js) o no suena.
  const REGLAS = [
    ['.iman-sonido, .volumen, .portada__dibujo, .manija', null],
    ['form[data-sonido] button, form[data-sonido] [type=submit]', null],
    ['#abrir-editor, [data-editar], .nota-despegada, .selector__sticker, .paso, #reiniciar-pasos, .nota-lista button', null],
    ['.menu ul a', 'iman'],
    ['.tarjeta-receta, a[href*="receta.php?id="]', 'hoja'],
    ['.colores__iman, .disposicion__boton, .cuenta__resumen', 'iman-grave'],
    ['a[href], button, summary, [type=submit], input[type=checkbox], input[type=radio], select', 'clic'],
  ];

  document.addEventListener('click', (evento) => {
    if (!activo || evento.defaultPrevented) return;
    let nombre = null;
    let elemento = null;
    for (const [selector, sonido] of REGLAS) {
      elemento = evento.target.closest(selector);
      if (elemento) {
        nombre = sonido;
        break;
      }
    }
    if (!nombre) return;
    const n = nombre === 'iman' ? [...document.querySelectorAll('.menu ul a')].indexOf(elemento) : 0;
    window.sonar(nombre, n);

    // Si el clic cambia de página, se espera un instante para que el sonido no se corte
    const enlace = evento.target.closest('a[href]');
    const nuevaPestana = evento.ctrlKey || evento.metaKey || evento.shiftKey || enlace?.target === '_blank';
    if (enlace && !nuevaPestana && !enlace.getAttribute('href').startsWith('#')) {
      evento.preventDefault();
      setTimeout(() => { location.href = enlace.href; }, 120);
    }
  });

  // Formularios con data-sonido="sticker-pega" (guardar en la despensa) o "sticker-arranca" (quitar).
  // Se escucha al final: si otro código canceló el envío (por ejemplo, la confirmación), no suena.
  document.addEventListener('submit', (evento) => {
    const form = evento.target;
    if (!activo || !form.dataset.sonido || evento.defaultPrevented || form.dataset.enviando) return;
    evento.preventDefault();
    form.dataset.enviando = '1';
    window.sonar(form.dataset.sonido);
    setTimeout(() => form.submit(), 170);
  });

  // Un campo mal cargado (validación del navegador): suena "error" una sola vez por intento
  let ultimoError = 0;
  document.addEventListener('invalid', () => {
    if (Date.now() - ultimoError > 600) window.sonar('error');
    ultimoError = Date.now();
  }, true);
})();
