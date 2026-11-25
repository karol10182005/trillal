Guía de trabajo y commits – Trillal
1. Organización del trabajo
Cada integrante deberá manejar tres espacios de trabajo:

1. Carpeta local

Lugar donde se desarrolla y modifica el código original. Cada integrante conserva sus archivos en su computadora.




2. Servidor ByetHost

Lugar donde se suben temporalmente las modificaciones para comprobar que funcionen correctamente en el entorno real.




3. Repositorio GitHub

Lugar donde se guarda la versión aprobada del código para mantener actualizado el proyecto y compartir los cambios con el equipo.



2. Procedimiento obligatorio para actualizar el proyecto
Todos los integrantes deberán seguir estos pasos en orden:
1. Modificar los archivos locales
   
   El integrante trabaja en su propia carpeta, realiza los cambios correspondientes y verifica que los archivos estén completos.
2. Realizar una copia de seguridad
   
   Antes de reemplazar archivos en ByetHost, se debe conservar una copia de la versión que actualmente funciona. Si se modificará la base de datos, también se debe respaldar.
3. Subir los cambios a ByetHost
   
   Se cargan únicamente los archivos modificados o los necesarios para implementar la actualización, procurando no reemplazar archivos ajenos al módulo.
4. Probar el funcionamiento
   
   El integrante deberá ingresar al sitio y comprobar que la actualización funcione correctamente. No basta con verificar que la página abra: se debe probar la funcionalidad modificada.
5. Determinar el resultado
   
   Se presentan dos escenarios:
   - Si funciona: se continúa con la actualización de GitHub.
   - Si no funciona: se restaura la versión anterior en ByetHost y se corrigen los errores en la carpeta local. Después se repite el procedimiento.
6. Actualizar GitHub
   
   Una vez comprobado el funcionamiento, el integrante registra sus cambios mediante un commit y los sube al repositorio correspondiente.
3. ¿Qué hacer si la actualización falla?
NO APROBADO
La actualización presenta errores

1. No actualizar la versión principal de GitHub.
2. Restaurar en ByetHost la última versión funcional.
3. Corregir los errores en los archivos locales.
4. Volver a subir los archivos corregidos.
5. Repetir las pruebas hasta que funcione.
Importante: no se debe eliminar el proyecto ni borrar el historial de GitHub. Solamente se restaura la versión funcional del servidor.

APROBADO
La actualización funciona correctamente

1. Confirmar que las pruebas fueron satisfactorias.
2. Registrar los archivos modificados con Git.
3. Crear un commit que describa los cambios.
4. Subir los cambios a GitHub.
5. Integrar los cambios a la rama principal después de su revisión.

4. Guía para escribir los commits
Los commits deben describir brevemente qué se modificó. No se deben utilizar mensajes genéricos como «actualización», «cambios» o «versión nueva».

Se recomienda utilizar esta estructura:

tipo: descripción del cambio
Tipo	Cuándo utilizarlo	Ejemplo
feat	Nueva funcionalidad	feat: agregar perfil de usuario
fix	Corrección de errores	fix: corregir inicio de sesión
style	Cambios visuales	style: ajustar diseño del menú
update	Actualización general	update: actualizar archivos del juego
refactor	Mejorar u organizar código	refactor: reorganizar conexión a BD
docs	Documentación	docs: actualizar manual de usuario

Ejemplos de commits para Trillal:
git add .
git commit -m "feat: agregar sistema de perfil"
git push origin nombre-de-rama


Otro ejemplo:
git add .
git commit -m "fix: corregir validación del registro"
git push origin nombre-de-rama


5. Reglas que todos los integrantes deben respetar
- Cada integrante es responsable de probar sus propias modificaciones.
- No se deben subir cambios a la rama principal sin haberlos probado previamente en ByetHost.
- Antes de reemplazar archivos, siempre debe existir una copia de seguridad.
- No se deben modificar archivos de otro compañero sin comunicarlo.
- Los commits deben tener mensajes claros y relacionados con los cambios realizados.
- Se debe avisar al equipo cuando se esté realizando una actualización en ByetHost, para evitar que dos integrantes suban archivos al mismo tiempo.
- Si una actualización falla, se debe restaurar la última versión funcional y corregir el problema antes de volver a intentarlo.
- Los cambios aprobados deben quedar registrados en GitHub.

6. Flujo general de trabajo
Este es el procedimiento que todos deberán seguir:
CARPETA LOCAL

Desarrollar y modificar el código

SUBIR A BYETHOST

Implementar los cambios y probarlos

¿FUNCIONA CORRECTAMENTE?

NO
Restaurar

Volver a la versión anterior, corregir y probar nuevamente.


SÍ
Aprobar

Crear commit y subir los cambios a GitHub.



GITHUB ACTUALIZADO

Integrar los cambios aprobados a la rama principal.


Una consideración importante: recomiendo que cada compañero trabaje en su propia rama de GitHub, en lugar de subir directamente a main. Así, aunque varios desarrollen módulos diferentes, podrán integrar sus cambios sin sobrescribir el trabajo de los demás.

La regla principal del equipo será: ningún cambio llega a la versión principal de GitHub sin haber sido probado y aprobado previamente en ByetHost.