# Publicar Cooki en un hosting

Guía para subir el sistema a un hosting con PHP y MySQL (gratuito o pago). Los nombres de los menús cambian de un hosting a otro; los pasos son los mismos.

## 1. Lo que tiene que tener el hosting

- **PHP 8.2 o más nuevo**, con las extensiones `pdo_mysql` y `fileinfo`. Son las que vienen casi siempre.
- **Base de datos:** MySQL 8 o MariaDB 10.6 o más nuevo, con phpMyAdmin o algo parecido para importar archivos `.sql`.
- **Subir archivos:** por FTP o con el administrador de archivos del panel.
- **Apache con `mod_rewrite`:** es lo común. Sin Apache, la API no funciona, pero las páginas sí.
- **Recomendado:** certificado HTTPS. Muchos hostings lo dan gratis.

## 2. Armar el paquete

En tu computadora, desde la carpeta del proyecto:

```bash
php scripts/empaquetar.php
```

Crea `cooki-hosting.zip` con `public/`, `src/`, `database/`, `.htaccess`, `.env.example` y el README. Deja afuera las pruebas, Docker, Composer y la documentación.

Sin PHP en la consola, comprimí esas mismas carpetas y archivos a mano.

## 3. Subir los archivos

Hay dos formas, según lo que permita el hosting:

- **A. Se puede elegir la carpeta raíz del sitio (recomendada).** Subí todo a una carpeta, por ejemplo `cooki/`, y elegí `cooki/public` como raíz del dominio. Así, `src/` y `database/` quedan fuera del alcance del navegador.
- **B. Solo se puede usar la carpeta pública** (`htdocs/` o `public_html/`). Subí todo el contenido del zip ahí. El `.htaccess` de la raíz manda las visitas a `public/` y bloquea `src/`, `database/` y los archivos de configuración.

En los dos casos, la carpeta `public/img/recetas/` tiene que tener **permiso de escritura** (755 o 775, según el hosting): ahí se guardan las fotos de las recetas.

## 4. Crear la base

1. En el panel del hosting, creá una base de datos y un usuario, y anotá:
   - el servidor (a veces no es `localhost`);
   - el nombre de la base;
   - el usuario y la contraseña.
2. En phpMyAdmin, elegí esa base. El cotejamiento tiene que ser `utf8mb4_unicode_ci`.
3. Pestaña **Importar**, en este orden:
   1. `database/schema.sql`;
   2. `database/seed.sql`;
   3. `database/recetas.sql`.

**Importante:** `seed.sql` crea usuarios de prueba con contraseñas conocidas (`Admin1234`, `Usuario1234`). Apenas publiques:
- entrá como `admin@cooki.test` y cambiale la contraseña desde **Mi cuenta**, o creá otra cuenta de administración y borrá esa;
- cambiá también la de `docente@cooki.test`, o dásela a la docente;
- si no los vas a usar para la demostración, borrá los usuarios generales de prueba desde Administración → Usuarios.

## 5. Configurar `.env`

Copiá `.env.example` como `.env`, en la misma carpeta donde está `src/`, y completá:

```ini
DB_HOST=el-servidor-que-te-dio-el-hosting
DB_PORT=3306
DB_NAME=nombre_de_la_base
DB_USER=usuario_de_la_base
DB_PASS=contraseña_de_la_base

APP_ENV=produccion          # no muestra errores en pantalla (quedan en el log del hosting)
APP_URL=https://tu-dominio  # para los enlaces de los correos ("Olvidé mi contraseña")
FORZAR_HTTPS=1              # solo si el sitio ya abre con https

# El correo (MAIL_...) se configura en el paso 6
```

El `.env` tiene contraseñas: **nunca va al repositorio** (ya está en `.gitignore`). Con la forma B del paso 3, el `.htaccess` impide abrirlo desde el navegador.

## 6. El correo

Cooki manda correos para:
- recuperar la contraseña;
- responder las consultas;
- avisar de una consulta nueva.

Tiene su propio cliente SMTP (`src/Smtp.php`). En InfinityFree `mail()` está bloqueado, pero las conexiones a Gmail
salen bien por el 587 y por el 465 (probado el 08/11/2026). Por eso se usa **una cuenta de Gmail del proyecto**.

### 6.1. Sacar la contraseña de aplicación de Gmail

Gmail no deja entrar desde otro programa con la contraseña de la cuenta: hace falta una **contraseña de aplicación**.

1. Entrá a la cuenta de Gmail del proyecto y abrí <https://myaccount.google.com/security>.
2. Activá la **verificación en dos pasos**, si no está activa. Sin eso, Google no ofrece contraseñas de aplicación.
3. Abrí <https://myaccount.google.com/apppasswords>, escribí un nombre (por ejemplo, `Cooki hosting`) y tocá **Crear**.
4. Copiá las 16 letras que muestra. Se ven una sola vez. Si la perdés, borrala y creá otra.

### 6.2. Completar el `.env` del hosting

```ini
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SEGURIDAD=tls
MAIL_USUARIO=cuenta-del-proyecto@gmail.com
MAIL_CONTRASENA=xxxxxxxxxxxxxxxx         # las 16 letras, sin espacios
MAIL_REMITENTE=cuenta-del-proyecto@gmail.com
MAIL_CONTACTO=cuenta-del-proyecto@gmail.com   # o el correo donde quieran recibir las consultas
```

- `MAIL_REMITENTE` tiene que ser **la misma cuenta**: si ponés otra dirección, Gmail la cambia por la cuenta igual.
- Si el 587 falla, probá con `MAIL_PORT=465` y `MAIL_SEGURIDAD=ssl`. Los dos anduvieron en la prueba.
- Gmail deja mandar unos 500 correos por día desde una cuenta gratuita: sobra para el sitio.

### 6.3. Probarlo

Entrá como administración → **Correo** → **Mandarme un correo de prueba**. La página muestra la configuración (sin la
contraseña) y, si el correo no sale, la respuesta de Gmail:

| Lo que dice | Qué hacer |
|---|---|
| `535 ... Username and Password not accepted` | La contraseña de aplicación está mal copiada o es la contraseña común. Creá otra |
| `No se pudo conectar a ...` | Revisá `MAIL_HOST` y `MAIL_PORT`, o probá con el otro puerto |
| `sin respuesta (tiempo agotado)` | Gmail no contestó a tiempo. Probá de nuevo o con el otro puerto |

Después probá también "¿Olvidaste tu contraseña?" con una cuenta real.

Con Docker los correos van a Mailpit (<http://localhost:8025>) y no hace falta configurar nada. Si el correo no
funciona, todo lo demás sigue andando y las consultas quedan guardadas.

## 7. Comprobar que quedó bien

- [ ] `https://tu-dominio/api/salud` responde `{"api":"ok","base_de_datos":"ok",...}`.
- [ ] El inicio, Quiénes somos y Contacto abren, y el mapa se ve.
- [ ] Se puede crear una cuenta, cargar algo en la despensa y ver recetas recomendadas.
- [ ] El modo cocina guarda los pasos aunque se recargue la página, y al terminar descuenta de la despensa.
- [ ] Como administración: editar una receta y subirle una foto.
- [ ] Administración → Correo manda el correo de prueba, y "¿Olvidaste tu contraseña?" manda el suyo (paso 6).
- [ ] `https://tu-dominio/src/Db.php` y `https://tu-dominio/.env` **no** abren (403 o 404).
- [ ] Una página con error muestra "Algo salió mal", y no el detalle técnico.
- [ ] Cambiaste las contraseñas de los usuarios de prueba (paso 4).

## 8. Actualizar una versión publicada

1. Armá el paquete otra vez y subí los archivos que cambiaron. El `.env` y la carpeta `public/img/recetas/` del hosting no se tocan.
2. Si hay migraciones nuevas en `database/migraciones/`, importalas en phpMyAdmin, en orden y solo las que todavía no corriste.
3. Repetí la comprobación del paso 7.
