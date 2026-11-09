// Lista de compras (app/lista.php) y los botones "+ lista" de una receta y de Mi despensa.
// Sin JavaScript todo funciona con formularios comunes (recargan la página); acá se hace sin recargar.
// Las respuestas vienen de app/lista.php en JSON (encabezado X-Requested-With: fetch).

(() => {
  // Manda un formulario a app/lista.php sin recargar. extra = campos que se agregan (por ejemplo, el botón tocado).
  async function enviar(form, extra = {}) {
    const datos = new FormData(form);
    for (const [clave, valor] of Object.entries(extra)) datos.append(clave, valor);
    const respuesta = await fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body: datos,
    });
    const json = await respuesta.json().catch(() => null);
    if (!json) throw new Error(String(respuesta.status));
    return json;
  }

  // Un aviso que leen los lectores de pantalla (aria-live), arriba del contenido
  let aviso = null;
  function avisar(mensaje, ok = true) {
    if (!aviso) {
      aviso = document.createElement('p');
      aviso.setAttribute('role', 'status');
      aviso.setAttribute('aria-live', 'polite');
      document.querySelector('#contenido').prepend(aviso);
    }
    aviso.className = 'aviso aviso--' + (ok ? 'exito' : 'error');
    aviso.textContent = mensaje;
  }

  // "En tu lista": reemplaza a un botón "+ lista" ya usado
  function enTuLista(form) {
    const enlace = document.createElement('a');
    enlace.className = 'etiqueta etiqueta--lista';
    enlace.href = form.action;
    enlace.textContent = 'En tu lista';
    form.replaceWith(enlace);
  }

  // 1. Botones "+ lista" (receta y despensa) y "Agregar lo que falta a la lista" (receta)
  for (const form of document.querySelectorAll('form.mas-lista')) {
    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      const boton = form.querySelector('button');
      boton.disabled = true;
      try {
        const respuesta = await enviar(form);
        avisar(respuesta.mensaje, respuesta.ok);
        if (!respuesta.ok) {
          boton.disabled = false;
          return;
        }
        window.sonar?.('sticker-pega');
        if (form.classList.contains('mas-lista--todo')) {
          // Todo lo que faltaba (menos lo opcional) quedó en la lista
          document.querySelectorAll('form.mas-lista:not(.mas-lista--todo):not([data-opcional])').forEach(enTuLista);
          form.remove();
        } else {
          enTuLista(form);
        }
      } catch {
        form.submit();   // si falla, el formulario común
      }
    });
  }

  // 2. La página de la lista
  const lista = document.querySelector('#lista-compras');
  if (!lista) return;

  // El texto para copiar y para WhatsApp se arma con lo que queda sin comprar (el mismo formato que en PHP)
  function textoLista() {
    const renglones = ['Lista de compras'];
    for (const grupo of lista.querySelectorAll('.lista-compras__grupo')) {
      const items = [...grupo.querySelectorAll('.renglon:not(.renglon--comprado)')];
      if (!items.length) continue;
      renglones.push('', `*${grupo.querySelector('h2').textContent.trim()}*`);
      for (const item of items) {
        const nombre = item.querySelector('strong').textContent.trim();
        const cantidad = item.querySelector('.renglon__cantidad')?.textContent.trim();
        renglones.push('- ' + nombre + (cantidad ? ' · ' + cantidad : ''));
      }
    }
    return renglones.join('\n');
  }
  const whatsapp = document.querySelector('#whatsapp-lista');
  const actualizarTexto = () => {
    if (whatsapp) whatsapp.href = 'https://wa.me/?text=' + encodeURIComponent(textoLista());
  };

  // Tildar = lo compré: pasa a la despensa en el momento, así que "Pasar lo tildado" ya no hace falta
  const pasar = document.querySelector('.lista-compras__pasar');
  if (pasar) pasar.hidden = true;

  // "Otra cantidad": muestra en el renglón lo que de verdad se compró (y al deshacer, vuelve la prevista)
  function cambiarCantidad(renglon, texto) {
    let cantidad = renglon.querySelector('.renglon__cantidad');
    if (!cantidad) {
      cantidad = document.createElement('span');
      cantidad.className = 'renglon__cantidad';
      renglon.querySelector('.renglon__texto strong').after(cantidad);
    }
    if (renglon.dataset.prevista === undefined) renglon.dataset.prevista = cantidad.textContent;
    cantidad.textContent = texto;
  }
  function textoOtraCantidad(renglon) {
    const campo = renglon.querySelector('.renglon__otra input');
    if (!campo || campo.value.trim() === '') return null;
    const unidad = renglon.querySelector('.renglon__otra select');
    return `${campo.value.trim().replace('.', ',')} ${unidad.selectedOptions[0].textContent}`;
  }

  function marcarComprado(renglon, comprado) {
    renglon.classList.toggle('renglon--comprado', comprado);
    const check = renglon.querySelector('input[type=checkbox]');
    check.checked = comprado;
    renglon.querySelector('.renglon__quitar').hidden = comprado;
    const otra = renglon.querySelector('.renglon__otra');
    if (otra) {
      otra.open = false;
      otra.hidden = comprado;
      if (!comprado) otra.querySelector('input').value = '';
    }
    if (!comprado && renglon.dataset.prevista !== undefined) {
      cambiarCantidad(renglon, renglon.dataset.prevista);
      delete renglon.dataset.prevista;
    }
    let deshacer = renglon.querySelector('.renglon__deshacer');
    if (comprado && !deshacer) {
      deshacer = document.createElement('button');
      deshacer.type = 'button';
      deshacer.className = 'boton boton--secundario boton--chico renglon__deshacer';
      deshacer.textContent = 'Deshacer';
      deshacer.addEventListener('click', async () => {
        deshacer.disabled = true;
        try {
          const respuesta = await enviar(lista, { deshacer: renglon.dataset.id });
          avisar(respuesta.mensaje, respuesta.ok);
          if (respuesta.ok) {
            window.sonar?.('sticker-arranca');
            marcarComprado(renglon, false);
            check.focus();
          }
        } catch {
          avisar('No se pudo deshacer. Probá de nuevo.', false);
        }
        deshacer.disabled = false;
      });
      renglon.append(deshacer);
    }
    if (deshacer) deshacer.hidden = !comprado;
    actualizarTexto();
  }

  lista.addEventListener('change', async (evento) => {
    const check = evento.target.closest('.renglon input[type=checkbox]');
    if (!check) return;
    const renglon = check.closest('.renglon');
    if (!check.checked) {
      // Se destilda con "Deshacer" (así la despensa vuelve a como estaba)
      check.checked = true;
      renglon.querySelector('.renglon__deshacer')?.focus();
      return;
    }
    check.disabled = true;
    try {
      // El tilde está deshabilitado mientras se envía (FormData no lo incluye): el id va aparte.
      // También van los tildados antes, que ya están comprados: el servidor los saltea
      const respuesta = await enviar(lista, { accion: 'comprar', 'ids[]': renglon.dataset.id });
      avisar(respuesta.mensaje, respuesta.ok);
      if (respuesta.ok) {
        window.sonar?.('tic');
        const otra = textoOtraCantidad(renglon);
        if (otra) cambiarCantidad(renglon, otra);
        marcarComprado(renglon, true);
      } else {
        check.checked = false;
      }
    } catch {
      check.checked = false;
      avisar('No se pudo marcar. Probá de nuevo.', false);
    }
    check.disabled = false;
  });

  // Enter en "Otra cantidad" usa el "Lo compré" de ese renglón (no el primero del formulario)
  lista.addEventListener('keydown', (evento) => {
    const campo = evento.target.closest('.renglon__otra input');
    if (!campo || evento.key !== 'Enter') return;
    evento.preventDefault();
    campo.closest('.renglon__otra').querySelector('button').click();
  });

  // "Lo compré" con otra cantidad, sin recargar
  lista.addEventListener('submit', async (evento) => {
    const boton = evento.submitter;
    if (!boton || boton.name !== 'comprar_uno') return;
    evento.preventDefault();
    const renglon = boton.closest('.renglon');
    boton.disabled = true;
    try {
      const respuesta = await enviar(lista, { comprar_uno: boton.value });
      avisar(respuesta.mensaje, respuesta.ok);
      if (respuesta.ok) {
        window.sonar?.('tic');
        cambiarCantidad(renglon, respuesta.texto_cantidad);
        marcarComprado(renglon, true);
        renglon.querySelector('.renglon__deshacer')?.focus();
      }
    } catch {
      avisar('No se pudo marcar. Probá de nuevo.', false);
    }
    boton.disabled = false;
  });

  // Quitar y Deshacer (los de "Comprado hoy") sin recargar
  lista.addEventListener('submit', async (evento) => {
    const boton = evento.submitter;
    if (!boton || !['quitar', 'deshacer'].includes(boton.name)) return;
    evento.preventDefault();
    const renglon = boton.closest('.renglon');
    boton.disabled = true;
    try {
      const respuesta = await enviar(lista, { [boton.name]: boton.value });
      avisar(respuesta.mensaje, respuesta.ok);
      if (!respuesta.ok) {
        boton.disabled = false;
        return;
      }
      if (boton.name === 'deshacer') {
        location.reload();   // vuelve a su góndola: más simple recargar
        return;
      }
      window.sonar?.('sticker-arranca');
      const grupo = renglon.closest('.lista-compras__grupo');
      renglon.remove();
      if (grupo && !grupo.querySelector('.renglon')) grupo.remove();
      actualizarTexto();
    } catch {
      // Si falla, el formulario común (submit() no manda el botón tocado: va en un campo oculto)
      const campo = document.createElement('input');
      campo.type = 'hidden';
      campo.name = boton.name;
      campo.value = boton.value;
      lista.append(campo);
      lista.submit();
    }
  });

  // Copiar e imprimir solo andan con JavaScript: por eso los botones arrancan ocultos
  const copiar = document.querySelector('#copiar-lista');
  const copiado = document.querySelector('.lista-compras__copiado');
  if (copiar) {
    copiar.hidden = false;
    copiar.addEventListener('click', async () => {
      const texto = textoLista();
      try {
        await navigator.clipboard.writeText(texto);
      } catch {
        // Navegadores sin permiso para el portapapeles: se copia desde un campo de texto oculto
        const campo = document.querySelector('#texto-lista');
        campo.value = texto;
        campo.select();
        document.execCommand('copy');
      }
      copiado.textContent = '¡Copiada!';
      window.sonar?.('pop-arriba');
      setTimeout(() => { copiado.textContent = ''; }, 2500);
    });
  }
  const imprimir = document.querySelector('#imprimir-lista');
  if (imprimir) {
    imprimir.hidden = false;
    imprimir.addEventListener('click', () => window.print());
  }
})();

// 3. Agregar a mano: el buscador con stickers (elegir.js) y la unidad del ingrediente elegido
(() => {
  const select = document.querySelector('#ingrediente_lista');
  const texto = document.querySelector('#texto_lista');
  if (!select || !texto || !window.armarElegir) return;
  const unidad = document.querySelector('#unidad_lista');
  const cantidad = document.querySelector('#cantidad_lista');
  let elegir = null;
  elegir = window.armarElegir(select, {
    nuevo: texto,
    detalles: texto.closest('details'),
    focoNuevo: cantidad,
    alCambiar: () => {
      elegir?.reflejar();
      const propia = select.selectedOptions[0]?.dataset.unidad;
      if (propia && [...unidad.options].some((o) => o.value === propia)) unidad.value = propia;
    },
  });
  elegir.reflejar();
})();
