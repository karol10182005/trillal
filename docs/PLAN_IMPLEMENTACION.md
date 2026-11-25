# PLAN DE IMPLEMENTACIÓN --- TRILLAL

## 1. Estrategia de construcción

Para el desarrollo de **Trillal** se eligió una estrategia **híbrida,
con enfoque principal de abajo hacia arriba (bottom-up)**. Primero se
trabajó en la estructura de la base de datos y en la identificación de
las entidades y relaciones necesarias; al mismo tiempo, se elaboraron
diseños preliminares de las pantallas para visualizar el funcionamiento
del sistema y reconocer qué información debía capturarse y mostrarse.

**Justificación (3 líneas):**

-   Se comenzó por la base de datos porque era necesario definir cómo se
    almacenarían y relacionarían los usuarios, las trivias, las
    preguntas, las partidas, los resultados y los puntos de Cultura.
-   Los diseños de las pantallas complementaron ese trabajo, ya que
    permitieron identificar los campos, botones y datos que necesitaría
    cada módulo antes de programarlo.
-   Por ello, se combinó el enfoque bottom-up ---construir sobre una
    estructura de datos definida--- con una revisión top-down del flujo
    de pantallas para comprobar que la base de datos respondiera a las
    necesidades del usuario.

## 2. Forma de trabajo

La implementación se organizó en las siguientes etapas:

1.  Karol Dominguez Dominguez **Análisis y diseño preliminar:** se plantearon las pantallas
    principales de Trillal, como el registro, inicio de sesión, creación
    de trivias, selección de modalidad, juego y perfil. Estos diseños
    sirvieron como referencia para identificar la información que se
    solicitaría al usuario y la que se presentaría durante el uso de la
    plataforma.
2.  Karol Dominguez Dominguez **Diseño de la base de datos:** se definieron las tablas, campos y
    relaciones para almacenar las cuentas, las trivias creadas, sus
    preguntas y respuestas, las salas o partidas, los resultados y la
    Cultura acumulada.
3.    Karol Dominguez Dominguez **Desarrollo de la lógica del servidor:** se implementaron los
    procesos en PHP para registrar usuarios, iniciar sesión, crear y
    administrar trivias, guardar respuestas, controlar las partidas y
    consultar los datos de MySQL.
4.  Estrella del Carmen  Zanches de la Cruz **Construcción de la interfaz:** se integraron HTML, CSS y
    JavaScript para presentar las pantallas, aplicar los estilos y
    permitir la interacción con los módulos.
5. Miguel Alexis Velázquez Villega  **Integración y pruebas:** se conectaron los módulos con la base de
    datos y se realizaron pruebas de registro, creación de trivias,
    participación en partidas, actualización de resultados y
    visualización del perfil y del ranking.

## 3. Criterio para definir los datos

Los diseños preliminares de las pantallas se utilizaron como apoyo para
determinar qué datos debía manejar cada módulo. Por ejemplo, en el
registro se identificaron los datos de la cuenta; en la creación de
trivias, el título, la descripción, la categoría y las preguntas con sus
opciones; y en el juego, el tiempo, las respuestas, la puntuación y los
resultados. Posteriormente, estos datos se organizaron en la base de
datos para evitar almacenar información sin una función definida y
mantener relaciones entre los elementos del sistema.

## 4. Resultado esperado

Con esta estrategia, Trillal se construye sobre una base de datos
organizada, pero sin perder de vista la experiencia de uso. La
combinación permite que cada pantalla trabaje con información
previamente definida y que los cambios en los módulos puedan probarse de
manera integrada.
