# Carga de fotos de XV Marisol

El endpoint `upload-github.php` recibe hasta 5 imágenes JPG, PNG o WEBP (máximo 5 MB cada una) y las guarda en `fotos-fiesta/` de `otype-dcd/XVMarisol`, en la rama `main`.

## Instalación en el hosting actual

1. Crea un token **fine-grained** en GitHub limitado al repositorio `otype-dcd/XVMarisol` y dale solo permiso **Contents: Read and write**. No uses un token con acceso a todos tus repositorios.
2. En el panel del hosting configura la variable de entorno `GITHUB_TOKEN` con el token. No la pongas en `index2.html`, no la publiques y no la subas al repositorio.
3. Sube `upload-github.php` a la misma carpeta pública donde está `index2.html` (o ajusta la URL del `fetch` si lo pones en otra ruta).
4. Confirma que el hosting tenga PHP con las extensiones cURL y Fileinfo y que permita solicitudes POST de al menos 25 MB. El endpoint limita cada imagen a 5 MB.
5. En tu HTML, el formulario ya envía las imágenes a `upload-github.php`; publícalo de nuevo si modificaste la ruta.

La respuesta JSON no revela el token ni errores internos de GitHub. Si el hosting no permite variables de entorno, configura el secreto mediante el mecanismo seguro que ofrezca el proveedor, fuera de la carpeta pública. Nunca pongas el token dentro de un archivo accesible desde la web.

## Importante

El endpoint es público porque lo usarán los invitados. Los límites de cantidad y tamaño reducen el abuso, pero no lo eliminan; para un enlace ampliamente compartido, agrega protección anti-bots o un código de acceso. Cada foto crea un commit en GitHub y aumenta el tamaño del repositorio. No subas fotos privadas ni sensibles a un repositorio público.
