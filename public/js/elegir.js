// Buscador de ingredientes con stickers (Mi despensa y la lista de compras).
// Reemplaza a la lista desplegable, que sigue oculta y es la que se envía con el formulario.
// Se escribe y quedan los dibujos que coinciden (también por sinónimo: "piña" encuentra Ananá).
// Si no está, "+ Agregar … como nuevo" completa el campo de texto de al lado (el del frasco).
//
// Uso: const elegir = armarElegir(select, { nuevo: input, detalles: details, alCambiar, focoNuevo });
//   select     la lista desplegable; cada <option> lleva data-dibujo y data-buscar ("nombre,sinónimos")
//   nuevo      el campo donde se escribe lo que no está en la lista
//   detalles   el <details> que contiene a ese campo (con el dibujo del frasco)
//   alCambiar  se llama cada vez que cambia lo elegido
//   focoNuevo  a qué campo pasa el foco al tocar "+ Agregar … como nuevo"
// Devuelve { buscador, reflejar, filtrar, limpiar }. reflejar() actualiza el sticker elegido y devuelve su dibujo.
(() => {
  const normal = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

  window.armarElegir = (lista, { nuevo, detalles, alCambiar = () => {}, focoNuevo = null }) => {
    const campo = lista.closest('.campo');
    const dibujoFrasco = detalles.querySelector('img').src;
    lista.classList.add('elegir__lista-oculta');
    const caja = document.createElement('div');
    caja.className = 'elegir';
    caja.innerHTML = `
      <div class="elegir__elegido"><img alt="" width="56" height="56"><span></span></div>
      <input class="elegir__buscar" type="search" placeholder="Buscá: tomate, arroz, yerba…" autocomplete="off"
             aria-label="Buscar el ingrediente">
      <div class="elegir__opciones" role="group" aria-label="Ingredientes"></div>
      <p class="elegir__nada" hidden>No está en la lista.</p>
      <button class="boton boton--agregar boton--chico elegir__nuevo" type="button" hidden>+ Agregar “<b></b>” como nuevo</button>`;
    lista.after(caja);
    const elegido = caja.querySelector('.elegir__elegido');
    const buscador = caja.querySelector('.elegir__buscar');
    const botonNuevo = caja.querySelector('.elegir__nuevo');
    const opciones = caja.querySelector('.elegir__opciones');

    for (const opcion of lista.querySelectorAll('option[value]:not([value=""])')) {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'elegir__opcion' + (opcion.hasAttribute('data-propio') ? ' elegir__opcion--propio' : '');
      boton.dataset.id = opcion.value;
      boton.dataset.buscar = opcion.dataset.buscar;
      boton.innerHTML = '<img alt="" width="48" height="48" loading="lazy"><span></span>';
      boton.querySelector('img').src = opcion.dataset.dibujo;
      boton.querySelector('span').textContent = opcion.textContent;
      opciones.append(boton);
    }
    // La etiqueta del campo apunta al buscador
    buscador.id = 'buscar-' + lista.id;
    campo.querySelector('label').htmlFor = buscador.id;

    function reflejar() {
      const opcion = lista.selectedOptions[0];
      const imagen = elegido.querySelector('img');
      const texto = elegido.querySelector('span');
      const esNuevo = !lista.value && nuevo.value.trim();
      if (opcion && lista.value) {
        imagen.src = opcion.dataset.dibujo;
        texto.textContent = opcion.textContent;
      } else if (esNuevo) {
        imagen.src = dibujoFrasco;
        texto.textContent = `${nuevo.value.trim()} (nuevo)`;
      } else {
        imagen.src = dibujoFrasco;
        texto.textContent = 'Todavía no elegiste';
      }
      elegido.classList.toggle('elegir__elegido--vacio', !lista.value && !esNuevo);
      for (const boton of opciones.querySelectorAll('.elegir__opcion')) {
        boton.setAttribute('aria-pressed', String(boton.dataset.id === lista.value));
      }
      return imagen.getAttribute('src');
    }

    function filtrar() {
      const buscado = normal(buscador.value);
      let visibles = 0;
      let exacto = false;
      for (const boton of opciones.querySelectorAll('.elegir__opcion')) {
        const nombres = boton.dataset.buscar.split(',').map(normal);
        // Coincide si alguna palabra empieza con lo escrito ("tom" → Tomate y Puré de tomate; "piña" no trae Espinaca)
        const coincide = buscado === '' || nombres.some((n) => n.startsWith(buscado) || n.split(' ').some((p) => p.startsWith(buscado)));
        boton.hidden = !coincide;
        if (coincide) visibles++;
        if (nombres.includes(buscado)) exacto = true;
      }
      caja.querySelector('.elegir__nada').hidden = visibles > 0;
      opciones.hidden = visibles === 0;
      botonNuevo.hidden = buscado.length < 2 || exacto;
      botonNuevo.querySelector('b').textContent = buscador.value.trim();
    }

    function limpiar() {
      nuevo.value = '';
      detalles.open = false;
      buscador.value = '';
      filtrar();
    }

    opciones.addEventListener('click', (evento) => {
      const boton = evento.target.closest('.elegir__opcion');
      if (!boton) return;
      lista.value = boton.dataset.id;
      nuevo.value = '';
      detalles.open = false;
      window.sonar?.('pop-arriba');
      alCambiar();
    });
    buscador.addEventListener('input', filtrar);
    // Enter en el buscador: elige el primero que se ve (o agrega el nuevo), sin enviar el formulario
    buscador.addEventListener('keydown', (evento) => {
      if (evento.key !== 'Enter') return;
      evento.preventDefault();
      const primero = opciones.querySelector('.elegir__opcion:not([hidden])');
      if (primero) primero.click();
      else if (!botonNuevo.hidden) botonNuevo.click();
    });
    botonNuevo.addEventListener('click', () => {
      lista.value = '';
      nuevo.value = buscador.value.trim();
      detalles.open = true;
      window.sonar?.('pop-arriba');
      alCambiar();
      focoNuevo?.focus();
    });
    nuevo.addEventListener('input', () => {
      if (nuevo.value.trim()) lista.value = '';
      alCambiar();
    });
    filtrar();
    return { buscador, reflejar, filtrar, limpiar };
  };
})();
