// Pedidos a la IA (Mi cocina → "Pedirle una receta a la IA" y "Pedir otra" en una receta de IA).
// Sin JavaScript son formularios comunes a app/receta-ia.php. Acá van por la API (data-ia = su dirección),
// se muestra "Pensando…" (aria-live, para los lectores de pantalla), se bloquea el botón para no pedir dos veces
// y al terminar se abre la receta. Si la IA no puede, se muestra su mensaje y el botón vuelve a andar.

(() => {
  for (const form of document.querySelectorAll('form[data-ia]')) {
    const boton = form.querySelector('button[type="submit"]');
    const estado = form.querySelector('.nota-ia__estado');

    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      if (boton.disabled) return;
      const datos = Object.fromEntries(new FormData(form));
      // "Pedir otra" manda el id de la receta que ya vio; el pedido nuevo, las opciones
      const cuerpo = datos.accion === 'otra'
        ? { otra_de: Number(datos.id) }
        : { porciones: Number(datos.porciones), minutos: Number(datos.minutos), tipo: datos.tipo };

      const textoBoton = boton.textContent;
      boton.disabled = true;
      boton.setAttribute('aria-busy', 'true');
      boton.textContent = 'Pensando…';
      form.classList.add('pensando');
      if (estado) {
        estado.className = 'nota-ia__estado';
        estado.textContent = 'Pensando una receta con lo que tenés… puede tardar unos segundos.';
      }

      let respuesta;
      try {
        respuesta = await fetch(form.dataset.ia, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(cuerpo),
        });
      } catch {
        form.submit();   // sin conexión con la API: el formulario común
        return;
      }
      const json = await respuesta.json().catch(() => null);
      if (!json) {
        form.submit();   // no vino JSON (por ejemplo, la protección del hosting): el formulario común
        return;
      }
      if (respuesta.ok && json.url) {
        if (estado) estado.textContent = `¡Listo! ${json.titulo}. Abriendo la receta…`;
        window.sonar?.('sticker-pega');
        window.location.href = json.url;
        return;
      }
      boton.disabled = false;
      boton.removeAttribute('aria-busy');
      boton.textContent = textoBoton;
      form.classList.remove('pensando');
      if (estado) {
        estado.className = 'nota-ia__estado nota-ia__estado--error';
        estado.textContent = json.error ?? 'La IA no está disponible ahora. Probá de nuevo en un rato.';
      }
      if (respuesta.status === 429) boton.disabled = true;   // ya no le quedan pedidos hoy
    });
  }
})();
