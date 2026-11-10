// Plan semanal (app/plan.php): guardar, quitar, "+ lista", "Armá mi semana" y "Agregar lo que falta para la semana"
// sin recargar. Sin JavaScript todo funciona con formularios comunes (recargan la página).
// app/plan.php responde JSON con el encabezado X-Requested-With: fetch. Como cambiar una comida cambia lo que falta
// en las siguientes (y los grupos de sus desplegables), después de cada cambio se vuelve a pedir la página y se
// reemplazan las comidas y los botones de arriba. Los stickers de decoración quedan como estaban.

(() => {
  const plan = document.querySelector('#plan');
  const acciones = document.querySelector('.plan-acciones');
  if (!plan || !acciones) return;

  // Los avisos van en una zona que ya existe al cargar la página, así los lectores de pantalla leen cada cambio
  const zonaAviso = document.createElement('div');
  zonaAviso.className = 'plan-aviso';
  zonaAviso.setAttribute('role', 'status');
  zonaAviso.setAttribute('aria-live', 'polite');
  acciones.before(zonaAviso);
  function avisar(mensaje, ok) {
    const aviso = document.createElement('p');
    aviso.className = 'aviso aviso--' + (ok ? 'exito' : 'error');
    aviso.textContent = mensaje;
    zonaAviso.replaceChildren(aviso);
  }

  async function enviar(form) {
    const respuesta = await fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body: new FormData(form),
    });
    const json = await respuesta.json().catch(() => null);
    if (!json) throw new Error(String(respuesta.status));
    return json;
  }

  const lugar = (fecha, momento) =>
    plan.querySelector(`.plan-comida[data-fecha="${CSS.escape(fecha)}"][data-momento="${CSS.escape(momento)}"]`);

  // Vuelve a pedir la página y cambia las 14 comidas y los botones de arriba por los nuevos
  async function refrescar() {
    const respuesta = await fetch(location.pathname, { cache: 'no-store' });
    if (!respuesta.ok) throw new Error(String(respuesta.status));
    const nueva = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
    const nuevasAcciones = nueva.querySelector('.plan-acciones');
    if (!nuevasAcciones || !nueva.querySelector('#plan')) throw new Error('sin plan');
    for (const comida of nueva.querySelectorAll('#plan .plan-comida')) {
      lugar(comida.dataset.fecha, comida.dataset.momento)?.replaceWith(document.importNode(comida, true));
    }
    const conIa = acciones.querySelector('input[name=con_ia]')?.checked;
    acciones.replaceChildren(...[...nuevasAcciones.childNodes].map((n) => document.importNode(n, true)));
    const casilla = acciones.querySelector('input[name=con_ia]');
    if (casilla) casilla.checked = Boolean(conIa);
  }

  // Después de un cambio, el foco queda en lo que sigue de ese lugar (los elementos viejos ya no están)
  function enfocar(accion, fecha, momento, form) {
    const comida = fecha ? lugar(fecha, momento) : null;
    let destino = null;
    if (comida && accion === 'guardar') destino = comida.querySelector('.plan-comida__receta a');
    if (comida && accion === 'quitar') destino = comida.querySelector('select');
    if (comida && accion === 'faltantes') destino = comida.querySelector('.plan-comida__boton button, summary');
    if (!comida) {
      const clase = form.classList.contains('plan-acciones__armar') ? '.plan-acciones__armar' : '.plan-acciones__lista';
      destino = acciones.querySelector(`${clase} button:not([disabled])`)
        ?? acciones.querySelector('a, button:not([disabled])');
    }
    (destino ?? plan.querySelector('a, select'))?.focus();
  }

  // En captura: corre antes que sonidos.js, que si no reenvía el formulario de la forma común
  document.addEventListener('submit', async (evento) => {
    const form = evento.target;
    if (!(form instanceof HTMLFormElement) || !form.closest('#plan, .plan-acciones')) return;
    evento.preventDefault();
    if (form.dataset.enviando) return;
    form.dataset.enviando = '1';
    const botones = [...form.querySelectorAll('button')];
    botones.forEach((b) => { b.disabled = true; });
    plan.setAttribute('aria-busy', 'true');
    const accion = form.elements.accion?.value ?? '';
    const fecha = form.elements.fecha?.value ?? '';
    const momento = form.elements.momento?.value ?? '';
    let respuesta;
    try {
      respuesta = await enviar(form);
    } catch {
      form.submit();   // si falla, el formulario común
      return;
    }
    if (!respuesta.ok) {
      avisar(respuesta.mensaje, false);
      window.sonar?.('error');
      botones.forEach((b) => { b.disabled = false; });
      delete form.dataset.enviando;
      plan.removeAttribute('aria-busy');
      return;
    }
    window.sonar?.(form.dataset.sonido);
    try {
      await refrescar();
    } catch {
      location.reload();   // se guardó, pero no se pudo traer la página nueva
      return;
    }
    plan.removeAttribute('aria-busy');
    avisar(respuesta.mensaje, true);
    enfocar(accion, fecha, momento, form);
  }, true);
})();
