// Bienvenida (app/bienvenida.php). Sin JavaScript se ven las 4 notas juntas y anda igual; con JavaScript:
//   - de a una nota, con "Siguiente" y "Atrás", y los puntitos de arriba marcan en qué paso estás
//   - "Como de todo" y las dietas se excluyen entre sí
//   - "¿Otra cosa?" busca entre los stickers de la lista general (y los tuyos); lo que no está se agrega
//     "como nuevo" con el frasco, y le llega al administrador
(() => {
  const form = document.querySelector('[data-bienvenida]');
  if (!form) {
    return;
  }
  const frasco = document.currentScript.dataset.frasco;
  const normal = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const mayuscula = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1);

  // ───── Paso a paso ─────
  const pasos = Array.from(form.querySelectorAll('.paso'));
  const puntos = document.querySelector('.pasos__puntos');
  const itemsPuntos = puntos ? Array.from(puntos.children) : [];
  let actual = 0;
  form.classList.add('pasos--js');
  if (puntos) {
    puntos.hidden = false;
  }
  form.querySelectorAll('[data-paso]').forEach((boton) => {
    boton.hidden = false;
    boton.addEventListener('click', () => mostrar(actual + Number(boton.dataset.paso)));
  });
  function mostrar(cual, enfocar = true) {
    actual = Math.max(0, Math.min(pasos.length - 1, cual));
    pasos.forEach((paso, i) => paso.classList.toggle('paso--actual', i === actual));
    itemsPuntos.forEach((punto, i) => {
      if (i === actual) {
        punto.setAttribute('aria-current', 'step');
      } else {
        punto.removeAttribute('aria-current');
      }
      punto.classList.toggle('hecho', i < actual);
    });
    if (enfocar) {
      const titulo = pasos[actual].querySelector('h2');
      titulo.setAttribute('tabindex', '-1');
      titulo.focus();
      if (puntos) {
        puntos.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }
  }
  mostrar(0, false);

  // ───── "Como de todo" o una dieta ─────
  const dietas = form.querySelector('[data-dieta]');
  if (dietas) {
    dietas.addEventListener('change', (evento) => {
      const casilla = evento.target;
      if (!casilla.checked) {
        return;
      }
      dietas.querySelectorAll('input').forEach((otra) => {
        if (casilla.value === 'todo' ? otra !== casilla : otra.value === 'todo') {
          otra.checked = false;
        }
      });
    });
  }

  // ───── Buscador con stickers ─────
  const catalogo = Array.from(document.querySelectorAll('#ingredientes-bienvenida option')).map((opcion) => ({
    id: opcion.dataset.id,
    nombre: opcion.value,
    dibujo: opcion.dataset.dibujo,
    buscar: normal(opcion.dataset.buscar || opcion.value),
  }));
  let numero = 0;

  /** Suma una casilla marcada a la lista (o marca la que ya estaba). */
  function sumar(lista, nombreCampo, valor, texto, dibujo, nuevo) {
    const ya = Array.from(lista.querySelectorAll('input')).find((casilla) => casilla.value === valor);
    if (ya) {
      ya.checked = true;
      return;
    }
    const item = document.createElement('li');
    item.className = 'elegible';
    const casilla = document.createElement('input');
    casilla.type = 'checkbox';
    casilla.id = 'elegible-js-' + (++numero);
    casilla.name = nombreCampo;
    casilla.value = valor;
    casilla.checked = true;
    const etiqueta = document.createElement('label');
    etiqueta.htmlFor = casilla.id;
    const imagen = new Image(46, 46);
    imagen.src = dibujo;
    imagen.alt = '';
    const nombre = document.createElement('span');
    nombre.textContent = texto;
    etiqueta.append(imagen, nombre);
    if (nuevo) {
      const marca = document.createElement('small');
      marca.className = 'elegible__nuevo';
      marca.textContent = 'nuevo';
      etiqueta.append(marca);
    }
    item.append(casilla, etiqueta);
    lista.append(item);
  }

  form.querySelectorAll('[data-buscar]').forEach((caja) => {
    const campo = caja.dataset.buscar;
    const lista = form.querySelector('[data-lista="' + campo + '"]');
    const entrada = caja.querySelector('input');
    // El campo ya no se manda: lo elegido va como casillas
    entrada.removeAttribute('name');
    entrada.removeAttribute('list');
    entrada.type = 'search';
    caja.querySelector('label').textContent = '¿Otra cosa? Buscala:';
    const resultados = document.createElement('ul');
    resultados.className = 'bienvenida__opciones';
    resultados.setAttribute('aria-label', 'Ingredientes que coinciden');
    const agregar = document.createElement('button');
    agregar.type = 'button';
    agregar.className = 'boton boton--chico boton--secundario bienvenida__nuevo';
    agregar.hidden = true;
    const aviso = document.createElement('p');
    aviso.className = 'bienvenida__aviso';
    aviso.setAttribute('role', 'status');
    caja.append(resultados, agregar, aviso);

    function elegir(ingrediente) {
      sumar(lista, campo + '[]', 'i:' + ingrediente.id, ingrediente.nombre, ingrediente.dibujo, false);
      aviso.textContent = 'Listo: sumaste ' + ingrediente.nombre.toLowerCase() + '.';
      entrada.value = '';
      filtrar();
      entrada.focus();
    }
    function agregarNuevo() {
      const texto = mayuscula(entrada.value.trim().replace(/\s+/g, ' ')).slice(0, 80);
      if (texto.length < 2) {
        return;
      }
      sumar(lista, campo + '_nuevo[]', texto, texto, frasco, true);
      aviso.textContent = 'Listo: “' + texto + '” queda en tu cuenta y le avisamos al administrador para que lo sume a la lista de todos.';
      entrada.value = '';
      filtrar();
      entrada.focus();
    }
    function coincidencias() {
      const buscado = normal(entrada.value);
      return buscado === '' ? [] : catalogo.filter((ing) => ing.buscar.includes(buscado)).slice(0, 8);
    }
    function filtrar() {
      const buscado = normal(entrada.value);
      const hay = coincidencias();
      resultados.textContent = '';
      hay.forEach((ingrediente) => {
        const item = document.createElement('li');
        const boton = document.createElement('button');
        boton.type = 'button';
        const imagen = new Image(34, 34);
        imagen.src = ingrediente.dibujo;
        imagen.alt = '';
        const nombre = document.createElement('span');
        nombre.textContent = ingrediente.nombre;
        boton.append(imagen, nombre);
        boton.addEventListener('click', () => elegir(ingrediente));
        item.append(boton);
        resultados.append(item);
      });
      const exacto = hay.some((ingrediente) => normal(ingrediente.nombre) === buscado);
      agregar.hidden = buscado.length < 2 || exacto;
      agregar.textContent = '+ Agregar “' + entrada.value.trim() + '” como nuevo';
    }
    entrada.addEventListener('input', filtrar);
    // Enter no manda el formulario: elige el único que coincide o lo agrega como nuevo
    entrada.addEventListener('keydown', (evento) => {
      if (evento.key !== 'Enter') {
        return;
      }
      evento.preventDefault();
      const hay = coincidencias();
      const exacto = hay.find((ingrediente) => normal(ingrediente.nombre) === normal(entrada.value));
      if (exacto || hay.length === 1) {
        elegir(exacto || hay[0]);
      } else if (hay.length === 0) {
        agregarNuevo();
      }
    });
    agregar.addEventListener('click', agregarNuevo);
  });
})();
