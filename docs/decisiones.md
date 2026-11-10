# Registro de decisiones

Cada decisión importante se anota acá: qué se decidió, por qué y qué alternativas se descartaron.
Lo más nuevo va arriba. Sirve para el informe y para la defensa.
Formato: decisiones tipo ADR (ver la guía de metodología del equipo, sección 9.2).

---

## DEC-020 · AAAA-MM-DD · (título)
- **Contexto:** 
- **Decisión:** 
- **Alternativas descartadas:** 
- **Consecuencias:** 
- **Participaron:** 

---

## DEC-019 · 2026-11-09 · La lista a mano en Mi cocina y Mi despensa, otra cantidad y comprar todo
- **Contexto:** al probar la lista, el equipo pidió verla sin entrar a la página Lista, poder cerrarla o apagarla, anotar cuando se compra otra cantidad de la prevista y marcar todo junto. En Mi despensa, los "+ lista" quedaban abajo de todo y no se veía de qué ingrediente eran.
- **Decisión:**
  - **Nota de la lista:** un post-it celeste al lado de "Usalo pronto" (Mi cocina) y arriba en el medio, entre el título con los filtros y "+ Agregar producto" (Mi despensa). Muestra 4 cosas; "Ver N más" despliega el resto por encima de lo que hay abajo, sin empujar la página (un `<details>`, anda sin JavaScript). "Ir a mi lista" lleva a la página Lista. "✕" la cierra y queda un recuadro chico ("Lista de compras · 3") que la vuelve a desplegar.
  - **Se guarda en la cuenta** (`usuario.nota_lista`: `abierta`, `cerrada` o `nunca`), como el color de la heladera. En Mis preferencias, "Mostrar mi lista de compras en Mi cocina y Mi despensa": apagada, no aparece ni siquiera cerrada. Va en la migración 008, porque la 007 ya estaba importada en el hosting.
  - **"+ lista" en Mi despensa** va al lado de la cantidad, en la misma fila del ingrediente.
  - **Otra cantidad:** cada renglón tiene "Otra cantidad", que abre un campito con la cantidad y la unidad y "Lo compré". Esa cantidad es la que pasa a la despensa y queda en el renglón; "Deshacer" vuelve a la prevista (se guarda en `deshacer`).
  - **Marcar todo como comprado:** un botón que pasa todo lo que falta a la despensa, en una transacción. Cada cosa se puede deshacer por separado.
- **Alternativas descartadas:**
  - Guardar si la nota está cerrada en una cookie: en el celular volvería a aparecer.
  - La nota "asomada" en el costado de la pantalla: se pisa con la manija de la heladera. El recuadro chico queda en el mismo lugar.
  - Editar la cantidad tocándola: sin JavaScript no anda.
- **Consecuencias:** migración nueva `008-nota-lista.sql`. Pruebas nuevas en `ListaTest`.
- **Participaron:** P2 (lista y pantallas), P1 (preferencias de la cuenta)

---

## DEC-018 · 2026-11-09 · Tablas del incremento 3 y lista de compras
- **Contexto:** el incremento 3 suma la lista de compras, el plan semanal y la IA. El hosting usa MySQL y Docker usa MariaDB 11.4, que guarda `JSON` como texto y trata algunos `CHECK` distinto. La lista tiene que conectarse con la despensa sin perder datos si algo falla.
- **Decisión:**
  - **Una sola migración** (`007-incremento-3.sql`) con las seis tablas, aunque la IA y el plan se programen después: en el hosting se importa una vez.
  - **`TEXT` en lugar de `JSON`, y sin `CHECK` entre columnas:** lo que tiene estructura (la receta de la IA, la caché, el estado para "Deshacer") se guarda como JSON en un `TEXT`, y reglas como "un renglón tiene ingrediente o texto" se validan en PHP. Así el mismo archivo anda en las dos bases.
  - **Sumar en la lista:** si el ingrediente ya está sin comprar con la misma unidad (o g con kg, ml con l), se suma en el mismo renglón; si no, va aparte. Tocar dos veces "Agregar lo que falta" de la misma receta no duplica.
  - **Comprar:** al tildar, el ingrediente pasa a la despensa en una transacción. Queda en nivel "Normal" (o "Mucho" si ya lo tenía), vence hoy + vida útil y va a la ubicación que ya tenía. Si es nuevo: heladera para lácteos y carnes, y para verduras, frutas y huevos que duran 14 días o menos; alacena para el resto. Si se sabe cuánto había y cuánto se compró, se suman las cantidades.
  - **Deshacer:** se guarda cómo estaba la despensa antes (en `deshacer`) y se puede volver atrás hasta que termina el día.
  - **Lo que falta de una receta** no incluye lo que hay en la despensa, los básicos, lo de "Siempre tengo", lo que la persona no come ni lo opcional, y usa las cantidades de las porciones elegidas.
  - **Compartir:** "Copiar la lista", un enlace `wa.me` con el texto (no se manda nada a otro servidor) y una vista de impresión con `@media print`.
  - **El buscador de stickers** pasó de `despensa.js` a `elegir.js`, que usan la despensa y la lista.
- **Alternativas descartadas:**
  - Columnas `JSON` y `CHECK`: dependen de la versión de la base del hosting.
  - Una tabla aparte para el historial de compras: alcanza con guardar el estado anterior en el mismo renglón.
  - Que tildar solo marque y que la despensa se actualice recién con "Pasar todo": con JavaScript, tildar ya pasa el ingrediente (con Deshacer); "Pasar lo tildado a mi despensa" queda para cuando no hay JavaScript.
- **Consecuencias:** las reglas que no controla la base tienen pruebas (`ListaComprasTest`, `ListaTest`). Si se borra un ingrediente que está en alguna lista, se borra también de la lista. Hay un imán nuevo, **Lista**, en el menú.
- **Participaron:** P2 (lista de compras), P3 (base de datos)

---

## DEC-017 · 2026-11-04 · Flecha de volver a la página anterior, vencidos aparte e ingredientes "a gusto"
- **Contexto:** la flecha de una receta siempre llevaba al catálogo, aunque se hubiera llegado desde Mi cocina o desde las favoritas. En "Usalo pronto" aparecía lo vencido, que ya no se puede usar. Y un ingrediente de receta sin cantidad se mostraba solo con el nombre.
- **Decisión:**
  - **Volver:** `enlaceVolver()` vuelve a la página de la que se vino (con sus filtros y su página), tomada de la dirección anterior del navegador. Solo se aceptan páginas de este sitio que están en `PAGINAS_PARA_VOLVER`. Se recuerda en la sesión por página, así se mantiene aunque la página se recargue (porciones, favorito). Si no se sabe de dónde vino, va a la página de siempre. Al lado puede llevar atajos a otras secciones, y no se repite el destino de la flecha. Los formularios dentro de un listado (editar un usuario o un ingrediente) vuelven siempre al listado.
  - **Vencidos:** Mi cocina los cuenta en un papelito al lado de "Usalo pronto", con un enlace a la despensa filtrada por vencidos.
  - **"A gusto":** sin cantidad ni unidad, la receta dice "a gusto" (la pizca sigue siendo "una pizca"). Con unidad y sin cantidad, el editor avisa del error.
- **Alternativas descartadas:** `history.back()` con JavaScript (no funciona si se entró directo con un enlace, y no se puede mostrar adónde vuelve); pasar el origen en cada enlace con `?desde=` (hay que tocar todos los enlaces del sitio).
- **Consecuencias:** depende de que el navegador mande la dirección anterior, lo que asegura `Referrer-Policy: same-origin` (DEC-016). Sin ella, la flecha va a la página de siempre.
- **Participaron:** P3

---

## DEC-016 · 2026-11-03 · API ampliada y preparación para el hosting
- **Contexto:** el contrato de la API (docs/api.md) dejaba para el incremento 2 el registro, el ingreso y los datos de cada usuario. Además, el sistema tiene que publicarse en un hosting gratuito.
- **Decisión:**
  - **API:** `POST /usuarios`, `POST /sesion` y `DELETE /sesion`, `GET /yo`, `GET /recetas` (paginado), `GET /recetas/{id}?porciones=N`, `GET /recomendaciones`, `GET /despensa` y `POST`/`DELETE /favoritos/{id}`. Usan la misma sesión que las páginas. Las que modifican datos solo aceptan JSON, lo que también protege contra CSRF. El ingreso por la API tiene el mismo bloqueo por intentos que la página.
  - **Producción** (`APP_ENV=produccion`): los errores no se muestran, quedan en el log, y la persona ve la página de error común.
  - **Encabezados de seguridad** en todas las respuestas: `nosniff`, `SAMEORIGIN`, `Referrer-Policy: same-origin` y `Permissions-Policy`.
  - **HTTPS:** `FORZAR_HTTPS=1` manda todo a https, y la cookie de sesión es `secure` cuando la página llega por https (también detrás del proxy del hosting).
  - **Paquete:** `scripts/empaquetar.php` arma el zip para subir, y `docs/publicar.md` tiene el paso a paso.
- **Alternativas descartadas:**
  - Tokens (JWT) para la API: la app móvil todavía no existe y la cookie de sesión alcanza.
  - Política de contenido (CSP): queda pendiente, porque el mapa de Contacto y los estilos en línea necesitan excepciones que hay que probar en el hosting.
- **Consecuencias:** pruebas nuevas en `ApiTest`. El hosting necesita PHP 8.2 o más nuevo, `pdo_mysql` y `fileinfo`.
- **Participaron:** P3 (API), P1 (hosting y seguridad)

---

## DEC-015 · 2026-11-03 · Foto de receta y paginación
- **Contexto:** el plan del incremento 2 pide que las recetas puedan tener foto y que los listados largos se paginen. Con 62 recetas y 107 ingredientes, las listas ya eran largas.
- **Decisión:**
  - **Foto:** se sube desde el editor de recetas. Puede ser JPG, PNG o WebP, pesar hasta 2 MB y medir entre 200 y 6000 px.
    - El tipo se comprueba por el contenido (`finfo` y `getimagesize`), no por la extensión.
    - El nombre del archivo lo arma el sistema (`receta-ID-azar.ext`).
    - Se guarda en `public/img/recetas/`, que no va al repositorio y tiene un `.htaccess` que impide ejecutar código.
    - Al reemplazar o quitar la foto, y al borrar la receta, se borra el archivo.
  - **Paginación:** `Dominio\Paginacion` y la vista `paginacion.php`. Se usa en el catálogo (12 por página), en las recetas y los usuarios de administración (20) y en los ingredientes (30). Los enlaces conservan la búsqueda y los filtros, y funcionan sin JavaScript.
- **Alternativas descartadas:**
  - Guardar la foto en la base (BLOB): agranda los volcados y complica el hosting.
  - Achicar las fotos con GD: no todos los hostings gratuitos la tienen; el límite de 2 MB alcanza.
  - Paginar en SQL con `LIMIT`: el orden "recomendadas" se calcula en PHP (el puntaje), así que se corta la lista ya ordenada.
- **Consecuencias:**
  - La carpeta `public/img/recetas/` tiene que tener permiso de escritura en el hosting.
  - Pruebas `FotoRecetaTest`, `PaginacionTest` y `ListadosTest`.
- **Participaron:** P3 (foto y catálogo), P5 (listados de administración)

---

## DEC-014 · 2026-11-03 · Bloqueo por intentos de ingreso
- **Contexto:** sin límite, alguien puede probar miles de contraseñas contra una cuenta.
- **Decisión:**
  - **Regla:** 5 contraseñas mal en 15 minutos para el mismo email bloquean ese email 15 minutos. Está en `Dominio\Bloqueo`, que se prueba sin base de datos.
  - **Mientras dura** no se prueba ninguna contraseña, ni siquiera la correcta, y se ve "Demasiados intentos" (código 429).
  - **Cuándo se borran los fallos:** al ingresar bien y al cambiar la contraseña con el enlace del correo.
  - **El registro de fallos:** tabla `intento_ingreso` (email, IP, momento). Se guarda aunque la cuenta no exista, para no revelar qué emails están registrados, y se limpia sola después de un día.
- **Alternativas descartadas:**
  - Bloquear por IP: en una facultad o un hogar muchas personas comparten la misma.
  - Un captcha: depende de un servicio externo.
  - Bloquear la cuenta hasta que la desbloquee un administrador: cualquiera podría bloquear la cuenta de otro.
- **Consecuencias:** migración `006-incremento-2-b.sql`. Pruebas `BloqueoTest` y `RecuperarTest`.
- **Participaron:** P1

---

## DEC-013 · 2026-11-03 · Recuperar la contraseña por correo, con un cliente SMTP propio
- **Contexto:** quien se olvida la contraseña no tenía cómo entrar. Para mandar el enlace hace falta un correo que funcione también en el hosting, y el proyecto no usa Composer en producción.
- **Decisión:**
  - **Cliente SMTP propio** (`src/Smtp.php`). Habla con el servidor de correo por un socket: EHLO, STARTTLS o SSL, AUTH LOGIN, MAIL FROM, RCPT TO, DATA y QUIT.
    - Se configura en `.env` (`MAIL_HOST`, `MAIL_PORT`, `MAIL_SEGURIDAD`, `MAIL_USUARIO` y `MAIL_CONTRASENA`).
    - Con Docker va a Mailpit.
    - Si `MAIL_HOST` está vacío, `Correo` usa `mail()` como antes.
    - `Correo::armar()` arma el mensaje: asunto codificado y cuerpo en base64.
  - **"¿Olvidaste tu contraseña?"** (`olvide.php`) manda un enlace a `restablecer.php` con un token de 64 caracteres.
    - En la base se guarda solo su hash SHA-256 (tabla `recuperacion`).
    - El enlace vale 1 hora y una sola vez; al usarlo se anulan los demás.
    - Como mucho se mandan 3 por hora por cuenta.
    - La respuesta es la misma exista o no la cuenta.
- **Alternativas descartadas:**
  - PHPMailer: es la opción más usada, pero suma una dependencia de Composer, y escribir el cliente sirve para entender el protocolo.
  - Mandar una contraseña nueva por correo: viaja en texto y queda en la casilla.
  - Preguntas secretas: son fáciles de adivinar.
- **Consecuencias:**
  - Migración `006-incremento-2-b.sql`.
  - Pruebas `SmtpTest`, con un servidor SMTP de mentira en otro proceso, y `RecuperarTest`.
  - En el hosting hay que configurar un servidor de correo (ver `docs/publicar.md`).
- **Participaron:** P1

---

## DEC-012 · 2026-11-03 · Preferencias: siempre tengo, no puedo comer y favoritas
- **Contexto:** las tablas `usuario_basico`, `restriccion_usuario` y `favorito` estaban desde el incremento 1, y la recomendación ya las consultaba, pero no había pantalla para cargarlas.
- **Decisión:**
  - Página `app/mis-preferencias.php`, a la que se llega desde el menú de la cuenta. Tiene dos hojas con los ingredientes como stickers (se agregan eligiendo de la lista y se quitan con ×) y las recetas favoritas.
  - **"Siempre tengo":** cuenta como disponible en todas las recetas (también en el "Tenés X de Y" del catálogo) y no se descuenta al cocinar.
  - **"No puedo comer":** esas recetas no se recomiendan; en el catálogo llevan "Tiene algo que no comés" y en el detalle el ingrediente dice "No lo comés".
  - **Favoritas:** botón ♡ en el detalle de la receta (sin recargar, con JavaScript), ♥ en las tarjetas y "Solo mis favoritas" en el buscador.
- **Alternativas descartadas:**
  - Ocultar del catálogo las recetas con algo que no come: es mejor mostrarlas con el aviso, porque a veces se puede reemplazar.
  - Favoritas solo en el navegador: no siguen al usuario.
- **Consecuencias:**
  - Sin cambios en la base. María (seed) trae ajo en "Siempre tengo" y dos favoritas.
  - Pruebas en `PreferenciasTest`.
- **Participaron:** P1 (página y cuenta), P3 (consultas y recomendación)

---

## DEC-011 · 2026-11-03 · Modo cocina con el progreso en la cuenta y descuento de la despensa
- **Contexto:** el detalle de la receta solo recordaba los pasos tildados en el navegador. El plan pide un modo cocina que sobreviva a recargar la página y que, al terminar, descuente de la despensa lo que se usó una sola vez.
- **Decisión:**
  - **Página `app/cocinar.php`:** se empieza con "Cocinar paso a paso" desde la receta (con las porciones elegidas).
    - Un paso por hoja, con letra grande y el paso actual resaltado; "Listo" lo marca sin recargar la página.
    - Temporizadores que suenan, vibran y avisan a los lectores de pantalla.
    - Pide no apagar la pantalla.
  - **El progreso** queda en `sesion_cocina` y `paso_completado`: se sigue desde Mi cocina, desde la receta o desde otro dispositivo. Cada usuario cocina una receta a la vez.
  - **Al terminar** se muestra cómo queda la despensa ("Papa: 1 kg → 0,4 kg") y se puede destildar lo que no se usó.
    - El descuento (`Dominio\Descuento`) resta la cantidad cuando las unidades se pueden comparar: g y kg, ml y l, y unidades con gramos si se sabe cuánto pesa una. Si no, baja un nivel.
    - No se descuentan los básicos ni lo que el usuario marcó como "Siempre tengo".
    - `stock_descontado` y una transacción evitan descontar dos veces.
- **Alternativas descartadas:**
  - Guardar el progreso solo en el navegador: se pierde al cambiar de dispositivo.
  - Descontar sin preguntar: el usuario puede haber usado otra cosa.
  - Pedir la cantidad usada de cada ingrediente: es lento mientras se cocina.
- **Consecuencias:**
  - Las recetas terminadas pesan en el puntaje ("−8 si la cocinó en la última semana", DEC-008).
  - El tiempo de los temporizadores se guarda en el navegador.
  - Pruebas `DescuentoTest` y `CocinaTest`.
- **Participaron:** P4 (modo cocina), P3 (descuento)

---

## DEC-010 · 2026-10-31 · Perfil: cambiar datos y contraseña, y dar de baja la cuenta
- **Contexto:** el incremento 1 no dejaba al usuario cambiar su nombre, su email ni su contraseña, ni borrar su cuenta. Lo pide el bloque 2.4 del plan.
- **Decisión:**
  - Página `app/perfil.php` con tres formularios. Para cambiar la contraseña o borrar la cuenta pide la contraseña actual.
  - La baja pide además una casilla de confirmación.
  - Al borrar la cuenta, la despensa, los ingredientes propios y las preferencias se van con ella (`ON DELETE CASCADE`). Las consultas de contacto quedan sin usuario.
  - La última cuenta de administración no se puede borrar.
  - Se entra desde el menú de la cuenta.
- **Alternativas descartadas:**
  - Una baja lógica (marcar la cuenta como inactiva): conserva datos personales que ya no hacen falta.
  - Pedir la confirmación por mail: queda para cuando esté el correo real (entrega B).
- **Consecuencias:** sin cambios en la base. Pruebas en `tests/Integracion/PerfilTest.php`.
- **Participaron:** P1

---

## DEC-009 · 2026-10-31 · Recetario en JSON con importador y validador
- **Contexto:** cargar decenas de recetas desde el ABM, de a una, es lento y no deja revisarlas entre todos en un PR.
- **Decisión:**
  - Cada receta es un archivo en `database/recetas/*.json` (formato en `docs/guia-recetas.md`).
  - `scripts/importar-recetas.php` las valida contra el catálogo y las carga en la base. Si la receta ya existe (mismo título), la actualiza.
  - Con `--sql` genera `database/recetas.sql`, que Docker carga al crear la base y que sirve para el hosting.
  - El validador revisa ingredientes, números, unidades y marcadores `{ing:...}`, y que las etiquetas `vegetariana` y `sin_tacc` sean ciertas.
  - Se suman la etiqueta `dulce`, 31 ingredientes (con dibujo y otros nombres) y **50 recetas originales del equipo**: 62 en total.
  - Los otros nombres de cada ingrediente se editan en el ABM de ingredientes.
  - Los pasos con temporizador se escriben `[⏱ 15 min]` en el ABM de recetas.
- **Alternativas descartadas:**
  - Planilla CSV: no representa bien la lista de ingredientes y los pasos de cada receta.
  - Copiar recetas de sitios web: tienen derechos de autor.
- **Consecuencias:**
  - Migración `005-incremento-2-a.sql` (ingredientes, otros nombres, etiqueta y sustituciones).
  - La prueba `RecetarioTest` falla si alguien cambia un .json y no regenera `recetas.sql`.
- **Participaron:** P3 (importador y recetas), P2 (dibujos)

---

## DEC-008 · 2026-10-31 · Recomendación con puntaje, sustituciones y porciones
- **Contexto:** en el incremento 1, "Podés cocinar" ordenaba solo por cuántos ingredientes tenía el usuario. El informe del proyecto (sección 10) define un puntaje con pesos, prioridad a lo que vence y sustituciones.
- **Decisión:**
  - `Dominio\Puntaje::calcular()` aplica la fórmula del informe:
    - disponibilidad 1, ½ (con sustituto) o 0;
    - cobertura ponderada con pesos 3, 2 y 1 (los opcionales no cuentan);
    - +5 por cada ingrediente que vence en 3 días o menos (hasta +15);
    - −6 por cada principal que falta;
    - −8 si la cocinó en la última semana.
  - Agrupa en "Podés hacerla ahora", "Te falta 1" y "Casi". No muestra las que no tienen ni la mitad de lo principal ni las que chocan con una restricción del usuario.
  - El peso se elige en el ABM de recetas ("Importancia").
  - Las sustituciones se cargan en la tabla `sustitucion`.
  - `Dominio\Porciones` escala cantidades y pasos (`{ing:papa}` → "papa (600 g)") de 1 a 12 porciones, con redondeo de cocina.
- **Alternativas descartadas:**
  - Calcular el puntaje en SQL: difícil de leer y de probar.
  - Mostrar una sola lista ordenada: no responde "¿qué puedo hacer ya?".
- **Consecuencias:**
  - Mi cocina muestra los tres grupos.
  - El listado de recetas suma "Ordenar: recomendadas".
  - Las tarjetas avisan qué usan de lo que vence y con qué reemplazar lo que falta.
  - Pruebas unitarias (`PuntajeTest`, `PorcionesTest`) y de integración (`RecomendacionTest`).
- **Participaron:** P3

---

## DEC-007 · 2026-10-28 · Respuestas a las consultas desde la administración
- **Contexto:** las consultas de contacto solo se podían marcar como respondidas; la respuesta había que mandarla por fuera del sistema.
- **Decisión:** columnas `contacto.respuesta`, `respondida_en`, `respondida_por` (→ usuario) y `respuesta_enviada`. Desde `admin/consultas.php` se escribe la respuesta: se guarda, se manda por correo con `Correo::enviar()` (en Docker llega a Mailpit) y la consulta queda como respondida. Si quien consultó tenía cuenta, la ve en Contacto → "Mis consultas".
- **Alternativas descartadas:** solo abrir el programa de correo con un enlace `mailto:` (no queda registro en el sistema); solo mostrarla en la página (quien escribió sin cuenta no se entera).
- **Consecuencias:** migración `database/migraciones/004-tanda-heladera-4.sql`. Si el correo no sale, la respuesta queda guardada igual y se avisa.
- **Participaron:** P3

---

## DEC-006 · 2026-10-28 · Ingredientes propios de cada usuario y color sin cuenta
- **Contexto:** al cargar la despensa, el usuario puede tener algo que no está en la lista de ingredientes. Además, quien no tiene cuenta también quiere elegir el color de la heladera.
- **Decisión:**
  - columna `ingrediente.usuario_id` (NULL = lista general; con un usuario = ingrediente propio, que solo ve él). El nombre deja de ser único en toda la tabla: es único dentro de la lista general y dentro de los propios de cada usuario (columna generada `ambito` + `UNIQUE (nombre, ambito)`). Si el nombre ya existe en la lista general (o es un sinónimo, como "piña"), se usa ese y no se crea otro. Los propios llevan siempre el dibujo del frasco, que no usa ningún ingrediente de la lista. El administrador los ve marcados y los puede pasar a la lista general; recién ahí se pueden usar en recetas;
  - sin cuenta, el color se guarda en la cookie `cooki_color` (un año) y pasa a la cuenta al registrarse.
- **Alternativas descartadas:** que el ingrediente nuevo vaya directo a la lista general (se llenaría de nombres repetidos o mal escritos); una tabla aparte para los propios (habría que duplicar todas las consultas de la despensa); guardar el color sin cuenta en el navegador con JavaScript (la página cargaría primero en verde y después cambiaría).
- **Consecuencias:** migración `database/migraciones/004-tanda-heladera-4.sql`. `IngredienteRepositorio::todos()` y `buscar()` (la API) muestran solo la lista general, salvo en la despensa del dueño. Al borrar un usuario se borran sus ingredientes propios.
- **Participaron:** P3

---

## DEC-005 · 2026-10-28 · Volumen de los sonidos por usuario y más ingredientes
- **Contexto:** los sonidos necesitan poder bajarse o subirse, y la lista de ingredientes tenía pocas frutas y verduras.
- **Decisión:** columna `usuario.volumen` (0 a 100, por defecto 50), guardada desde el menú de la cuenta como el color y la disposición (`app/preferencias.php`). Para quien no ingresó, el volumen se guarda en el navegador. Se suman 32 ingredientes (ids 45 a 76), cada uno con su dibujo, y "Pollo (pechuga)" pasa a llamarse "Pollo" ("pechuga" queda como sinónimo).
- **Alternativas descartadas:** guardar el volumen solo en el navegador (no sigue al usuario en otra computadora).
- **Consecuencias:** migración `database/migraciones/003-tanda-heladera-3.sql`. La prueba de dibujos exige que los 76 ingredientes tengan uno propio.
- **Participaron:** P3

---

## DEC-004 · 2026-10-17 · Disposición de la pantalla guardada en la cuenta
- **Contexto:** en la computadora, algunos usuarios prefieren ver todo sin desplazarse hacia abajo (más cosas a lo ancho).
- **Decisión:** columna `usuario.disposicion` (`normal` o `panoramica`, por defecto `normal`), igual que el color (DEC-003): valores en `UsuarioRepositorio::DISPOSICIONES`, se guarda desde el menú de la cuenta con `app/preferencias.php` y se aplica con `<html data-disposicion="...">`. En pantallas angostas siempre se ve la disposición normal.
- **Alternativas descartadas:** guardarlo en el navegador (se pierde al cambiar de computadora); una sola disposición ancha para todos (en notebooks chicas queda apretado).
- **Consecuencias:** migración `database/migraciones/002-tanda-heladera-2.sql`. Se suma también el ingrediente "Bife" (44) para tener un dibujo de carne distinto de la carne picada.
- **Participaron:** P3

---

## DEC-003 · 2026-10-17 · Color de la heladera guardado en la cuenta
- **Contexto:** en el estilo Heladera cada usuario puede elegir el color de la puerta (verde agua, crema, celeste, rosa o noche, que es oscuro). La elección tiene que seguirlo aunque entre desde otra computadora.
- **Decisión:** columna `usuario.color` (`VARCHAR(10)`, por defecto `verde`). Los valores válidos están en `UsuarioRepositorio::COLORES`; la página `app/color.php` (desde la tanda 2, `app/preferencias.php`) lo guarda y lo actualiza en la sesión. El color se aplica con `<html data-color="...">` y variables CSS.
- **Alternativas descartadas:** guardarlo solo en el navegador (`localStorage`): no necesita cambiar la base, pero se pierde al cambiar de computadora o de navegador.
- **Consecuencias:** quien ya tenía la base creada corre `database/migraciones/001-tanda-heladera.sql` (o, con Docker, `docker compose down -v`). Adelanta una parte de "Preferencias del usuario" del incremento 2.
- **Participaron:** P3

---

## DEC-002 · 2026-10-15 · Incremento 1: páginas PHP para el 2.º parcial de Programación 4
- **Contexto:** el 2.º parcial de Programación 4 pide, en dos semanas, páginas concretas: principal con registro e ingreso, contacto con mapa y `mail()`, sesiones con dos roles, página interna para el usuario general, página de administración con ABM y un buscador, con al menos tres tablas (contactos, usuarios y contenido).
- **Decisión:** el incremento 1 se arma con páginas PHP que procesan formularios y generan el HTML en el servidor (`public/*.php`, `public/app/`, `public/admin/`), con sesiones de PHP y JavaScript para validar, filtrar y armar formularios dinámicos. Se agregan el rol del usuario (`general` o `admin`), la tabla `contacto` y el autor de cada receta. La API JSON queda solo para el buscador de ingredientes. El modo cocina, la recomendación con puntaje y el hosting pasan al incremento 2.
- **Alternativas descartadas:** la aplicación de una sola página que consume la API (más trabajo y menos directo para cumplir cada punto del parcial en dos semanas).
- **Consecuencias:** cada requisito del parcial corresponde a una página concreta y fácil de mostrar ([requisitos-parcial.md](requisitos-parcial.md)). El plan anterior del Sprint 0 se reemplaza por este y el repositorio se rehace con el kit nuevo, ya que todavía no se había programado nada.
- **Participaron:** P1, P2, P3, P4

---

## DEC-001 · (completar en C-01) · Stack tecnológico
- **Contexto:** la materia de Programación Web exige HTML, CSS, JavaScript, PHP y MySQL/MariaDB, y el proyecto final debe poder presentarse también ahí.
- **Decisión:** frontend en HTML + CSS + JavaScript sin frameworks; API REST en PHP 8 sin framework; base MariaDB (compatible con MySQL); entorno local con Docker; hosting PHP + MySQL gratuito.
- **Alternativas descartadas:** TypeScript + React + Node + PostgreSQL (no cumple la materia); Laravel (pendiente de confirmar con la cátedra si se permite).
- **Consecuencias:** un solo proyecto sirve para las dos materias; la lógica de recomendación se escribe en PHP; la app móvil futura reutiliza la misma API.
- **Participaron:** P1, P2, P3, P4
