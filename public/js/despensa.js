// Mi despensa: filtro en vivo, editor como nota despegada y cantidad exacta.

// 1. Filtra la lista mientras se escribe, sin recargar la página.
const filtro = document.querySelector('#filtro');
const filas = document.querySelectorAll('#tabla-despensa tbody tr');
const sinResultados = document.querySelector('#sin-resultados');

// Quita tildes y pasa a minúsculas: así "limon" encuentra "Limón"
const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

if (filtro) {
  filtro.addEventListener('input', () => {
    const buscado = normalizar(filtro.value.trim());
    let visibles = 0;
    for (const fila of filas) {
      const coincide = normalizar(fila.dataset.nombre).includes(buscado);
      fila.hidden = !coincide;
      if (coincide) visibles++;
    }
    sinResultados.hidden = visibles > 0;
  });
}

// 2. La fecha de vencimiento no puede ser de hace mucho: avisamos si parece un error de tipeo
const fecha = document.querySelector('#fecha_vencimiento');
if (fecha) {
  fecha.addEventListener('change', () => {
    const elegida = new Date(fecha.value);
    const haceUnMes = new Date();
    haceUnMes.setMonth(haceUnMes.getMonth() - 1);
    fecha.setCustomValidity(elegida < haceUnMes ? 'Esa fecha ya pasó hace más de un mes. ¿Está bien?' : '');
    fecha.reportValidity();
  });
}

// 3. Cantidad exacta: muestra la unidad del ingrediente y mueve el nivel
//    (0 = agotado; si hay algo, no puede quedar "agotado"). El servidor aplica la misma regla.
const ingrediente = document.querySelector('#ingrediente_id');
const cantidad = document.querySelector('#cantidad');
const unidad = document.querySelector('#unidad-cantidad');
const nivel = document.querySelector('#nivel');

const nombreNuevo = document.querySelector('#nombre_nuevo');
const unidadNueva = document.querySelector('#unidad_nueva');

function mostrarUnidad() {
  const opcion = ingrediente.selectedOptions[0];
  let valor = opcion ? opcion.dataset.unidad || '' : '';
  // Ingrediente nuevo: la unidad es la que se eligió en "Se mide en"
  if (!ingrediente.value && nombreNuevo?.value.trim()) valor = unidadNueva.value;
  unidad.textContent = valor === 'u' ? 'unidades' : valor;
  reflejarElegido();
}

// 5. Buscador con stickers (elegir.js): reemplaza a la lista desplegable, que sigue oculta y es la que se envía.
//    Si no está, "+ Agregar … como nuevo" completa el ingrediente propio (con el frasco).
let elegir = null;
let dibujoNota = null;   // el dibujo grande del ingrediente elegido, pegado en la esquina de la nota
const detallesNuevo = document.querySelector('.nuevo-ingrediente');

function reflejarElegido() {
  if (!elegir) return;
  const dibujo = elegir.reflejar();
  const esNuevo = !ingrediente.value && nombreNuevo?.value.trim();
  // El mismo dibujo, grande, en la esquina de la nota: se menea cada vez que cambia
  if (dibujoNota && dibujoNota.getAttribute('src') !== dibujo) {
    dibujoNota.src = dibujo;
    dibujoNota.classList.remove('editor__dibujo--cambia');
    void dibujoNota.offsetWidth;
    dibujoNota.classList.add('editor__dibujo--cambia');
  }
  dibujoNota?.classList.toggle('editor__dibujo--vacio', !ingrediente.value && !esNuevo);
}

function limpiarNuevo() {
  elegir?.limpiar();
}

if (ingrediente && nombreNuevo && window.armarElegir) {
  dibujoNota = document.createElement('img');
  dibujoNota.className = 'editor__dibujo';
  dibujoNota.alt = '';
  dibujoNota.width = 96;
  dibujoNota.height = 96;
  document.querySelector('#formulario').append(dibujoNota);
  elegir = window.armarElegir(ingrediente, {
    nuevo: nombreNuevo,
    detalles: detallesNuevo,
    alCambiar: () => mostrarUnidad(),
    focoNuevo: document.querySelector('#categoria_nueva'),
  });
  unidadNueva.addEventListener('change', mostrarUnidad);
}

if (ingrediente && cantidad) {
  ingrediente.addEventListener('change', mostrarUnidad);
  cantidad.addEventListener('input', () => {
    if (cantidad.value === '') return;
    const numero = Number(cantidad.value);
    if (numero === 0) nivel.value = '0';
    else if (numero > 0 && nivel.value === '0') nivel.value = '2';
  });
  mostrarUnidad();
}

// 4. El formulario se abre como una nota que se despega de la heladera (<dialog>).
//    Sin JavaScript (o en un navegador sin <dialog>) queda arriba de la lista como siempre.
const seccion = document.querySelector('#formulario');
if (seccion && typeof HTMLDialogElement === 'function') {
  const dialogo = document.createElement('dialog');
  dialogo.className = 'nota-despegada';
  dialogo.setAttribute('aria-labelledby', 'titulo-editor');
  seccion.before(dialogo);
  dialogo.append(seccion);

  const form = seccion.querySelector('form');
  const titulo = seccion.querySelector('#titulo-editor');
  const guardar = seccion.querySelector('#guardar-editor');
  const cancelar = seccion.querySelector('#cancelar-editor');
  cancelar.hidden = false;

  const abrir = (foco = elegir?.buscador || ingrediente) => {
    dialogo.showModal();
    window.sonar?.('nota-despega');
    foco.focus();
    if (foco.showPicker && foco.type === 'date') {
      try { foco.showPicker(); } catch { /* algunos navegadores no lo permiten */ }
    }
  };

  // "+ Agregar a la heladera": nota en blanco
  document.querySelector('#abrir-editor').addEventListener('click', (evento) => {
    evento.preventDefault();
    form.elements.id.value = '0';
    ingrediente.value = '';
    nivel.value = '2';
    cantidad.value = '';
    form.elements.ubicacion.value = 'alacena';
    form.elements.fecha_vencimiento.value = '';
    limpiarNuevo();
    seccion.querySelectorAll('.error-campo').forEach((p) => p.remove());
    titulo.textContent = 'Agregar ingrediente';
    guardar.textContent = 'Agregar';
    mostrarUnidad();
    abrir();
  });

  // "Editar": completa la nota con los datos de la fila, sin recargar
  for (const enlace of document.querySelectorAll('[data-editar]')) {
    enlace.addEventListener('click', (evento) => {
      evento.preventDefault();
      const datos = enlace.closest('tr').dataset;
      seccion.querySelectorAll('.error-campo').forEach((p) => p.remove());
      form.elements.id.value = datos.id;
      ingrediente.value = datos.ingrediente;
      nivel.value = datos.nivel;
      cantidad.value = datos.cantidad;
      form.elements.ubicacion.value = datos.ubicacion;
      form.elements.fecha_vencimiento.value = datos.vence;
      limpiarNuevo();
      titulo.textContent = 'Editar ingrediente';
      guardar.textContent = 'Guardar cambios';
      mostrarUnidad();
      // "¿Venció? Cambiar fecha" abre la nota con la fecha seleccionada
      abrir(enlace.dataset.foco ? form.elements[enlace.dataset.foco] : undefined);
    });
  }

  // Cancelar, tecla Escape o click en la niebla de alrededor: se vuelve a pegar
  cancelar.addEventListener('click', (evento) => {
    evento.preventDefault();
    dialogo.close();
  });
  dialogo.addEventListener('click', (evento) => {
    if (evento.target === dialogo) dialogo.close();
  });
  dialogo.addEventListener('close', () => {
    window.sonar?.('nota-pega');
    // Si venía de ?editar=ID, se limpia la dirección para que al recargar no se abra de nuevo
    if (location.search.includes('editar=')) history.replaceState(null, '', location.pathname);
  });

  // Si se estaba editando o el servidor devolvió errores, la nota aparece abierta
  if (seccion.dataset.abrir === '1') abrir();
}
