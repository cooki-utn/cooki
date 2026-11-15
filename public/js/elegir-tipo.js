// Lista desplegable de "Tipo" en Recetas, con los stickers (app/recetas.php).
// Sin JavaScript es un <details> con opciones: se abre, se elige y se toca Buscar.
// Con JavaScript: al elegir (clic o Enter) se cierra y muestra arriba el tipo elegido con su dibujo; con las
// flechas se recorre. También se cierra con Escape o tocando afuera. Como los demás filtros, busca con "Buscar".
(() => {
  document.querySelectorAll('[data-elegir-tipo]').forEach((desplegable) => {
    const resumen = desplegable.querySelector('summary');
    const cerrar = (devolverFoco) => {
      desplegable.open = false;
      if (devolverFoco) {
        resumen.focus();
      }
    };
    // Con las flechas se recorren las opciones (cambia lo de arriba); con un clic o Enter se elige y se cierra
    let conFlechas = false;
    const elegir = () => cerrar(true);
    desplegable.addEventListener('change', (evento) => {
      const opcion = evento.target.closest('label');
      resumen.querySelector('img').src = opcion.querySelector('img').src;
      resumen.querySelector('span').textContent = opcion.querySelector('span').textContent;
      if (!conFlechas) {
        elegir();
      }
      conFlechas = false;
    });
    // Al abrir, el foco va a la opción elegida (con las flechas se pasa a las demás)
    desplegable.addEventListener('toggle', () => {
      if (desplegable.open) {
        const elegida = desplegable.querySelector('input:checked') || desplegable.querySelector('input');
        elegida.focus();
      }
    });
    desplegable.addEventListener('keydown', (evento) => {
      conFlechas = evento.key.startsWith('Arrow');
      if (evento.key === 'Escape' && desplegable.open) {
        evento.preventDefault();
        cerrar(true);
      } else if (evento.key === 'Enter' && evento.target.matches('input')) {
        evento.preventDefault();
        elegir();
      }
    });
    document.addEventListener('click', (evento) => {
      if (desplegable.open && !desplegable.contains(evento.target)) {
        cerrar(false);
      }
    });
  });
})();
