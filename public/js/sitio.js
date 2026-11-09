// JavaScript común a todas las páginas (se carga desde el encabezado).

// 1. Menú desplegable en el celular
const botonMenu = document.querySelector('.barra__boton');
const menu = document.querySelector('#menu-principal');
if (botonMenu && menu) {
  botonMenu.addEventListener('click', () => {
    const abierto = menu.classList.toggle('abierto');
    botonMenu.setAttribute('aria-expanded', String(abierto));
  });
}

// 2. Confirmar antes de borrar: <form data-confirmar="¿Borrar la receta?" data-confirmar-si="Borrar"
//    data-confirmar-dibujo="img/…svg">. En lugar del cartel del navegador aparece una nota despegada
//    (con la niebla de fondo, como el editor de la despensa) con "Cancelar" y el botón para borrar.
//    Sin JavaScript, el formulario se envía directo.
let notaConfirmar = null;
function pedirConfirmacion(form) {
  if (!notaConfirmar) {
    notaConfirmar = document.createElement('dialog');
    notaConfirmar.className = 'nota-despegada nota-confirmar';
    notaConfirmar.setAttribute('aria-labelledby', 'nota-confirmar-texto');
    notaConfirmar.innerHTML = `
      <div class="postit nota-confirmar__papel">
        <span class="postit__chinche" aria-hidden="true"></span>
        <img class="nota-confirmar__dibujo" alt="" width="64" height="64" hidden>
        <p class="nota-confirmar__texto" id="nota-confirmar-texto"></p>
        <div class="nota-confirmar__botones">
          <button class="boton boton--secundario" type="button" value="no">Cancelar</button>
          <button class="boton boton--peligro" type="button" value="si"></button>
        </div>
      </div>`;
    document.body.append(notaConfirmar);
    // Tocar la niebla (afuera de la nota) también cancela
    notaConfirmar.addEventListener('click', (evento) => {
      if (evento.target === notaConfirmar) notaConfirmar.close('no');
    });
  }
  const texto = notaConfirmar.querySelector('.nota-confirmar__texto');
  const dibujo = notaConfirmar.querySelector('.nota-confirmar__dibujo');
  const [cancelar, aceptar] = notaConfirmar.querySelectorAll('button');
  texto.textContent = form.dataset.confirmar;
  aceptar.textContent = form.dataset.confirmarSi || 'Sí, borrar';
  dibujo.hidden = !form.dataset.confirmarDibujo;
  if (form.dataset.confirmarDibujo) dibujo.src = form.dataset.confirmarDibujo;
  cancelar.onclick = () => notaConfirmar.close('no');
  aceptar.onclick = () => notaConfirmar.close('si');
  notaConfirmar.onclose = () => {
    if (notaConfirmar.returnValue === 'si') {
      form.dataset.confirmado = '1';
      form.requestSubmit();
    } else {
      window.sonar?.('nota-pega');
    }
  };
  notaConfirmar.returnValue = '';
  notaConfirmar.showModal();
  window.sonar?.('nota-despega');
  cancelar.focus();
}
document.addEventListener('submit', (evento) => {
  const form = evento.target;
  if (!form.dataset.confirmar || form.dataset.confirmado) return;
  evento.preventDefault();
  if (typeof HTMLDialogElement === 'function') {
    pedirConfirmacion(form);
  } else if (window.confirm(form.dataset.confirmar)) {   // navegadores muy viejos, sin <dialog>
    form.dataset.confirmado = '1';
    form.submit();
  }
});

// 3. Menú de la cuenta (<details>): se cierra al tocar afuera o con Escape
const cuenta = document.querySelector('.cuenta');
if (cuenta) {
  document.addEventListener('click', (evento) => {
    if (cuenta.open && !cuenta.contains(evento.target)) cuenta.open = false;
  });
  // Al cerrarse, si había quedado preguntando "¿Seguro?", vuelve a mostrar solo "Salir"
  cuenta.addEventListener('toggle', () => {
    if (!cuenta.open) reiniciarSalir();
  });
  document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && cuenta.open) {
      cuenta.open = false;
      cuenta.querySelector('summary').focus();
    }
  });
}

// 4. Preferencias (color de la heladera y disposición): cambian al instante y se guardan
//    en la cuenta en segundo plano. Sin JavaScript, el formulario va a app/preferencias.php.
const formPreferencias = document.querySelector('.preferencias');
if (formPreferencias) {
  formPreferencias.addEventListener('click', (evento) => {
    const boton = evento.target.closest('button[name]');
    if (!boton) return;
    evento.preventDefault();
    const clave = boton.name;   // "color" o "disposicion"
    window.sonar?.('iman-grave');
    document.documentElement.dataset[clave] = boton.value;
    for (const otro of formPreferencias.querySelectorAll(`button[name="${clave}"]`)) {
      otro.setAttribute('aria-pressed', String(otro === boton));
    }
    const datos = new FormData(formPreferencias);
    datos.set(clave, boton.value);
    fetch(formPreferencias.action, { method: 'POST', body: datos, headers: { 'X-Requested-With': 'fetch' } });
  });
}

// 5. Salir: el primer toque pregunta "¿Seguro?" en el mismo lugar; "Sí, salir" cierra la sesión
const formSalir = document.querySelector('.salir');
function reiniciarSalir() {
  if (!formSalir) return;
  formSalir.classList.remove('salir--preguntando');
  formSalir.querySelector('.salir__pregunta').hidden = true;
  formSalir.querySelector('.salir__no').hidden = true;
  formSalir.querySelector('.cuenta__salir').textContent = 'Salir';
}
if (formSalir) {
  formSalir.addEventListener('submit', (evento) => {
    if (formSalir.classList.contains('salir--preguntando')) return;
    evento.preventDefault();
    formSalir.classList.add('salir--preguntando');
    formSalir.querySelector('.salir__pregunta').hidden = false;
    const quedarse = formSalir.querySelector('.salir__no');
    quedarse.hidden = false;
    formSalir.querySelector('.cuenta__salir').textContent = 'Sí, salir';
    quedarse.focus();
  });
  formSalir.querySelector('.salir__no').addEventListener('click', reiniciarSalir);
}

// 6. Ver la contraseña: cada campo de contraseña lleva un ojo adentro que la muestra u oculta
for (const campo of document.querySelectorAll('input[type="password"]')) {
  const envoltorio = document.createElement('span');
  envoltorio.className = 'con-ojo';
  campo.replaceWith(envoltorio);
  envoltorio.append(campo);
  const ojo = document.createElement('button');
  ojo.type = 'button';
  ojo.className = 'ojo';
  ojo.setAttribute('aria-label', 'Mostrar la contraseña');
  ojo.setAttribute('aria-pressed', 'false');
  ojo.setAttribute('aria-controls', campo.id);
  envoltorio.append(ojo);
  ojo.addEventListener('click', () => {
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    ojo.setAttribute('aria-pressed', String(mostrar));
    ojo.setAttribute('aria-label', mostrar ? 'Ocultar la contraseña' : 'Mostrar la contraseña');
    campo.focus();
  });
}

// 7. El nombre "COOKI": cada tanto las letras saltan solas (la misma ola que al pasar el mouse, sin sonido).
//    No pasa si la pestaña no está a la vista, si el mouse ya está encima o si el sistema pide menos movimiento.
const marca = document.querySelector('.marca');
if (marca && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  setInterval(() => {
    if (document.hidden || marca.matches(':hover')) return;
    marca.classList.remove('marca--ola');
    void marca.offsetWidth;   // reinicia la animación
    marca.classList.add('marca--ola');
  }, 9000);
  marca.addEventListener('animationend', (evento) => {
    if (evento.target.classList.contains('marca__letra') && evento.target === marca.querySelector('.marca__letra:last-child')) {
      marca.classList.remove('marca--ola');
    }
  });
}

// Nota de la lista de compras (Mi cocina y Mi despensa): "Cerrar" la deja como un recuadro chico y el recuadro
// la vuelve a desplegar. Cambia en el momento y se guarda en la cuenta en segundo plano.
// Sin JavaScript, cada botón envía su formulario a app/preferencias.php y la página se recarga.
const notaLista = document.querySelector('.nota-lista');
if (notaLista) {
  const abierta = notaLista.querySelector('.nota-lista__abierta');
  const cerrada = notaLista.querySelector('.nota-lista__cerrada');
  notaLista.addEventListener('submit', (evento) => {
    const boton = evento.submitter;
    if (!boton || boton.name !== 'nota_lista') return;
    evento.preventDefault();
    const abrir = boton.value === 'abierta';
    abierta.hidden = !abrir;
    cerrada.hidden = abrir;
    notaLista.dataset.estado = boton.value;
    window.sonar?.(abrir ? 'nota-despega' : 'nota-pega');
    (abrir ? abierta.querySelector('.nota-lista__cerrar') : cerrada.querySelector('button'))?.focus();
    const datos = new FormData(evento.target);
    datos.append('nota_lista', boton.value);
    fetch(evento.target.action, { method: 'POST', body: datos, headers: { 'X-Requested-With': 'fetch' } });
  });
}
