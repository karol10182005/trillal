[README.md](https://github.com/user-attachments/files/35381491/README.md)
# TRILLAL 👑

**Trillal** es una plataforma web de trivias en la que los usuarios pueden crear preguntas, publicar desafíos, competir con otras personas y acumular puntos de **Cultura**. Está desarrollada con **PHP, HTML, CSS, JavaScript y MySQL**.

Este archivo explica qué hace la plataforma, cómo instalarla en un servidor como **ByetHost**, cómo importar su base de datos y dónde se encuentran los archivos para configurar la conexión, los estilos, los iconos y las imágenes.

> **Importante:** GitHub almacena el código fuente, pero **GitHub Pages no ejecuta PHP ni MySQL**. Para utilizar todas las funciones de Trillal se necesita un hosting compatible con PHP y MySQL.

## 1. ¿Para qué sirve?

Trillal busca crear una **comunidad de preguntas y respuestas** en la que cada usuario pueda:

- Registrarse, iniciar sesión y personalizar su perfil con una fotografía.
- Crear y administrar **hasta cinco trivias por cuenta**, con diferentes categorías y preguntas de texto o imagen.
- Participar en trivias públicas, privadas o concursos con salas programadas.
- Jugar partidas **Normales** y **Rápidas**: la rápida utiliza la mitad del tiempo y otorga **10 puntos adicionales de puntuación** al completarse.
- Consultar resultados, historial de partidas, clasificaciones y puntos de **Cultura**.
- Competir por Cultura: **25 puntos** para el primer lugar y **20 puntos** para el segundo y el tercero. En trivias públicas, los premios se determinan tras cerrar la clasificación del día; en privadas, por sala finalizada.

Los puntos de **puntuación de partida** y los puntos de **Cultura** son diferentes. En las partidas individuales de cada trivia hay un intento Normal y uno Rápido por jugador.

## 2. Tecnologías utilizadas

| Tecnología | Función |
|---|---|
| **HTML** | Estructura y contenido de las páginas. |
| **CSS** | Colores, tarjetas, botones, diseño adaptable y animaciones visuales. |
| **JavaScript** | Interacciones, temporizador visible, actualización del juego, confeti y efectos. |
| **PHP** | Registro, sesiones, validaciones, consultas, partidas y entrega de Cultura. |
| **MySQL/MariaDB** | Almacenamiento de usuarios, trivias, preguntas, partidas, resultados y premios. |

La base de datos **no** se conecta directamente desde HTML ni desde el JavaScript del navegador: **PHP** realiza las consultas mediante PDO.

## 3. Requisitos

- Hosting con **PHP 8.0 o posterior**, recomendado, y soporte para **PDO MySQL**.
- Una base de datos **MySQL o MariaDB** y acceso a **phpMyAdmin**.
- Un administrador de archivos o acceso FTP para subir el proyecto.
- Permiso de escritura en `uploads/avatars/` y `uploads/trivias/` para las fotografías y las imágenes de preguntas.
- Un dominio con **HTTPS** habilitado para proteger el inicio de sesión.

## 4. Estructura de carpetas

La estructura principal del proyecto debe conservarse al subirlo al servidor:

```text
htdocs/
├── index.php                  # Inicio de sesión y registro
├── login.php                  # Validación del acceso
├── registro.php               # Creación de cuentas
├── logout.php                 # Cerrar sesión
├── inicio.php                 # Página principal
├── crear.php                  # Mis trivias y editor
├── crear_accion.php           # Guardar/editar trivias y preguntas
├── jugar.php                  # Explorar trivias
├── trivia.php                 # Información y elección de partida
├── sala.php                   # Pantalla de la partida
├── jugar_api.php              # Peticiones y respuestas del juego
├── perfil.php                 # Perfil y estadísticas
├── perfil_accion.php          # Actualización del perfil
├── ranking.php                # Clasificación de Cultura
├── cultura_estado.php         # Estado de premios de Cultura
├── config/
│   ├── db.php                 # DATOS PRIVADOS de conexión a MySQL
│   ├── bootstrap.php          # Sesiones y funciones comunes
│   ├── creador.php            # Funciones del creador
│   ├── juego.php              # Motor del juego y salas
│   ├── juego_ui.php           # Componentes y menú de Jugar
│   ├── cultura.php            # Reglas de Cultura
│   ├── perfil.php             # Consultas del perfil
│   └── ranking.php            # Consultas del ranking
├── assets/
│   ├── css/
│   │   ├── estilos.css        # Estilos generales
│   │   ├── creador.css        # Pantalla Crear
│   │   ├── juego.css          # Juego, tarjetas y reloj
│   │   ├── perfil.css         # Perfil
│   │   └── ranking.css        # Ranking
│   ├── js/
│   │   ├── app.js             # Interacciones generales
│   │   ├── creador.js         # Editor de preguntas
│   │   ├── juego.js           # Partidas y efectos
│   │   ├── perfil.js          # Interacciones del perfil
│   │   └── ranking.js         # Interacciones del ranking
│   └── img/
│       └── logo-trillal.png   # Logotipo
└── uploads/
    ├── avatars/              # Fotos de perfil cargadas
    └── trivias/              # Imágenes de trivias y opciones
```

> Esta es la **estructura esperada del proyecto integrado**. Debes subir los archivos de la versión consolidada que tengas instalada; no mezcles sin revisar archivos de parches antiguos. Algunas carpetas pueden tener archivos `.htaccess` o `index.html` para protegerlas: consérvalos.

## 5. Instalación de la base de datos (SQL)

### Paso 1. Crear la base de datos

1. Entra al panel de control de **ByetHost** (o de tu proveedor).
2. Abre **MySQL Databases** y crea una base de datos, si aún no existe.
3. Guarda los siguientes datos que indique tu proveedor:
   - **Servidor MySQL (host)**.
   - **Nombre completo de la base de datos**.
   - **Usuario MySQL**.
   - **Contraseña MySQL**.
4. Abre **phpMyAdmin** desde el panel del hosting y selecciona esa base de datos en la columna lateral.

**No confundas el servidor MySQL con la dirección de phpMyAdmin.** El valor `host` debe ser el servidor MySQL indicado por el hosting, no la URL de acceso a phpMyAdmin.

### Paso 2. Importar los archivos SQL

Para una instalación **completamente nueva**, en phpMyAdmin entra a **Importar → Seleccionar archivo → Continuar** y carga los siguientes archivos **en este orden** (espera a que cada importación termine antes de continuar):

1. `trillal_byethost_nueva.sql`: crea las **12 tablas** principales y las categorías iniciales, sin vistas ni triggers.
2. `trillal_modos_partida_migracion.sql`: añade la columna `tipo_partida` para distinguir sala, partida Normal y partida Rápida.
3. `01_migracion_5_trivias.sql`: sustituye la restricción de una trivia por usuario, para que PHP pueda permitir **hasta cinco**.

Puedes guardar esos tres archivos en una carpeta `database/` del repositorio como documentación e instaladores; **no necesitas subirlos a la carpeta pública `htdocs`** después de importarlos.

> **Si ya tienes Trillal funcionando con datos**, no vuelvas a ejecutar toda la instalación. Haz una copia de seguridad y aplica **solo** las migraciones que todavía te falten. Ejecutar dos veces una sentencia `ALTER TABLE` puede producir errores por columnas o índices existentes.

### Paso 3. Comprobar las tablas

En phpMyAdmin, verifica que aparezcan, entre otras:

```text
usuarios · categorias · trivias · preguntas · opciones
accesos_privados · salas · inscripciones_sala · intentos
respuestas · cierres_cultura · movimientos_cultura
```

La columna `cultura` pertenece a `usuarios`. La columna `tipo_partida` pertenece a `salas` después de su migración.

## 6. Configurar PHP y la conexión a MySQL

El archivo de configuración está en:

```text
config/db.php
```

Ábrelo y sustituye los valores de ejemplo **por los datos MySQL reales del hosting**:

```php
<?php
return [
    'host' => 'TU_SERVIDOR_MYSQL',
    'nombre' => 'TU_BASE_DE_DATOS',
    'usuario' => 'TU_USUARIO_MYSQL',
    'contrasena' => 'TU_CONTRASENA_MYSQL',
];
```

Por ejemplo, el `host` puede ser uno asignado por ByetHost y **no necesariamente `localhost`**. Respeta exactamente el nombre completo de la base de datos y del usuario que muestra el panel.

`config/bootstrap.php` se encarga de iniciar sesiones y establecer la conexión PDO utilizando `config/db.php`. Normalmente **no necesitas editar `bootstrap.php`** para instalar Trillal.

### ¿Dónde se configura el administrador?

- **phpMyAdmin** es la herramienta del hosting para administrar MySQL; se abre desde el panel de control y **no se instala dentro de `config/`**.
- `config/db.php` contiene las **credenciales de conexión a MySQL**, no la contraseña de una cuenta administradora de Trillal.
- La tabla `usuarios` incluye el campo `rol` (`usuario` o `administrador`), pero el **panel de administración y moderación** es un módulo pendiente. No necesitas crear una cuenta administradora para instalar o probar el juego.

**Seguridad:** nunca publiques `config/db.php` con contraseñas reales en GitHub. En el repositorio utiliza un archivo de ejemplo sin secretos y excluye `config/db.php` con `.gitignore` si usas Git. Tampoco subas bases de datos con usuarios reales.

## 7. Subir el proyecto a `htdocs`

1. Descomprime tu copia **completa y actualizada** de Trillal en tu computadora.
2. Abre el administrador de archivos del hosting (o tu cliente FTP).
3. Entra a la carpeta raíz de tu sitio web, que en ByetHost normalmente se llama **`htdocs`**.
4. Sube **el contenido** del proyecto: archivos `.php` y carpetas `assets/`, `config/`, `uploads/`, etc., respetando los mismos nombres y rutas.
5. Comprueba que `index.php` quede directamente dentro de `htdocs`, por ejemplo: `htdocs/index.php` (no `htdocs/Trillal/index.php`, a menos que quieras instalarlo como subcarpeta).
6. Revisa que `uploads/avatars/` y `uploads/trivias/` existan y tengan permisos de escritura. Normalmente se usan permisos `755` para carpetas, pero dependen del hosting; **no utilices `777` de forma indiscriminada**.
7. Abre `https://TU_DOMINIO/index.php` y registra una cuenta de prueba.

Si el hosting permite subir un ZIP y **extraerlo dentro de `htdocs`**, también puedes usar ese método. No basta con dejar el ZIP sin descomprimir.

## 8. ¿Dónde están los estilos, imágenes e iconos?

### Estilos CSS

Los estilos se encuentran en `assets/css/`:

- `estilos.css`: identidad visual, diseño general, encabezados, navegación y formularios.
- `creador.css`: formulario, tarjetas y editor de trivias.
- `juego.css`: selector de partidas, botones de respuesta, reloj y animaciones de juego.
- `perfil.css`: perfil, avatar e historial.
- `ranking.css`: clasificación y podio.

Por ejemplo, una página los enlaza así:

```html
<link rel="stylesheet" href="assets/css/estilos.css">
<link rel="stylesheet" href="assets/css/juego.css">
```

### JavaScript

Está en `assets/js/`. El archivo `juego.js` controla el comportamiento visual de las partidas (temporizador, respuestas, clasificación visible y confeti); **PHP conserva la validación oficial de tiempo y puntos**.

```html
<script src="assets/js/juego.js" defer></script>
```

### Logotipo e iconos

- **Logotipo:** `assets/img/logo-trillal.png`. Puedes cambiar esa imagen, manteniendo su nombre y ruta si no quieres modificar los enlaces del proyecto.
- **Iconos de botones y menú:** en los módulos revisados se utilizan principalmente **símbolos escritos en HTML/PHP** (por ejemplo, `♛`, `✎`, `⚡`) y clases CSS como `icono-menu`. **No existe una carpeta única `assets/icons/`** en los archivos de referencia.
- Para cambiar esos símbolos, localiza el elemento correspondiente en el archivo `.php` y ajusta su apariencia en el CSS de la pantalla.
- **Imágenes de usuarios y preguntas:** se almacenan en `uploads/avatars/` y `uploads/trivias/` respectivamente. No sustituyen a `assets/img/`.

## 9. Verificación después de instalar

1. Abre `index.php`, crea una cuenta y entra al sistema. Comprueba que el nombre se muestre en `inicio.php`.
2. En `perfil.php`, verifica que Cultura comience en 0 y prueba subir una fotografía.
3. En `crear.php`, crea una trivia, agrega preguntas y publícala. Comprueba que puedas crear hasta cinco.
4. En `jugar.php`, abre una trivia libre y verifica que existan los botones de **Partida normal** y **Partida rápida**.
5. Responde las preguntas, revisa el reloj, el botón para continuar, la posición final y el confeti.
6. Abre `ranking.php` y verifica que tu nombre y Cultura coincidan con el perfil.
7. Cierra sesión y verifica que no puedas abrir las páginas privadas sin iniciar sesión.

**Nota sobre Cultura:** los premios de trivias públicas se calculan al finalizar el día y se procesan cuando vuelve a ejecutarse el mantenimiento de la aplicación; no necesariamente se reflejan de inmediato al terminar una partida.

## 10. Errores frecuentes

| Problema | Qué revisar |
|---|---|
| **Error al conectar a MySQL** | Los cuatro valores de `config/db.php`, el servidor MySQL y que la base exista. |
| **Página en blanco / error 500** | Versión PHP, rutas de archivos y errores PHP del hosting. |
| **Faltan estilos o imágenes** | Que exista `assets/` y que las rutas conserven sus mayúsculas/minúsculas. |
| **No se guarda el avatar** | Existencia y permisos de `uploads/avatars/`, tamaño y formato de imagen. |
| **No se puede jugar Normal/Rápida** | Que se haya importado la migración `tipo_partida` y que `jugar_api.php` corresponda a la versión actual. |
| **No se puede crear una segunda trivia** | Que se haya aplicado `01_migracion_5_trivias.sql` y estén actualizados los archivos del creador. |
| **No aparece Cultura inmediatamente** | Revisar `cultura_estado.php` y si la jornada pública ya terminó. |

## 11. Publicación en GitHub

Para compartir o respaldar el proyecto en GitHub, sube los archivos **descomprimidos** y mantén sus carpetas. Incluye este `README.md` en la raíz del repositorio. Es recomendable guardar los scripts de instalación en `database/` y crear un `.gitignore` que excluya archivos privados y las imágenes subidas por los usuarios.

Ejemplo de `.gitignore`:

```gitignore
/config/db.php
.env
/uploads/avatars/*
/uploads/trivias/*
/backups/
```

**Advertencia:** `.gitignore` evita el seguimiento normal con Git, pero debes revisar manualmente los archivos al utilizar **Upload files** en la página de GitHub y no subir archivos que contengan contraseñas. Si se publicó una clave por error, cámbiala inmediatamente en el hosting.

---

**Estado del proyecto:** Trillal cuenta con módulos de acceso, creación de trivias, juego, perfil y ranking. La administración/moderación y las pruebas integrales de despliegue aún pueden requerir trabajo adicional.
