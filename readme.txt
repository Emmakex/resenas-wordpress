=== Reseñas WordPress ===
Contributors: eduardoyauri
Tags: reviews, ratings, feedback, google reviews, whatsapp
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0-rc2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Solicita valoraciones, detecta clientes que necesitan seguimiento y gestiona feedback de una o múltiples sedes desde WordPress.

== Description ==

Reseñas WordPress crea un flujo de valoración post-servicio dentro de tu propio WordPress.

El caso de uso recomendado es crear una página de valoraciones, insertar el shortcode `[resenas_wordpress]` y enviar la URL de esa página al cliente por WhatsApp después de una compra, servicio, reserva, cita o intervención.

Ejemplo:

`https://tuweb.com/resenas/`

También puedes utilizar esa misma URL desde web, email, SMS, QR u otros canales.

= Qué incluye =

* Valoraciones de 1 a 5 estrellas.
* Experiencia optimizada para móvil.
* Feedback interno para facilitar el seguimiento de clientes insatisfechos.
* Acceso a Google Reviews cuando la empresa ha configurado una URL de reseña.
* Una o múltiples sedes.
* URL de Google independiente por sede.
* Referencias externas mediante `?ref=`.
* Selección de sede mediante `?sede=`.
* Registro del origen de la valoración.
* Personalización de logo, colores y textos.
* Avisos por email para valoraciones que requieren seguimiento.
* Dashboard y gestión de respuestas dentro de WordPress.
* Datos almacenados localmente en WordPress.

= Cómo funciona =

1. Crea una página en WordPress, por ejemplo "Reseñas".
2. Inserta `[resenas_wordpress]` dentro de la página.
3. Publica la página.
4. Configura tu empresa, enlace de Google y, si lo necesitas, una o varias sedes.
5. Envía al cliente la URL publicada, por ejemplo `https://tuweb.com/resenas/`.

Para añadir una referencia:

`https://tuweb.com/resenas/?ref=ABC123`

Para una instalación con varias sedes:

`https://tuweb.com/resenas/?sede=sede-centro&ref=ABC123`

= Recuperación del servicio =

Cuando una valoración se encuentra dentro del umbral de seguimiento configurado, Reseñas WordPress solicita un motivo y un comentario interno para que la empresa pueda revisar la experiencia y contactar al cliente si corresponde.

Después de guardar ese feedback, el cliente puede seguir accediendo de forma secundaria al enlace público de Google si está configurado.

Las valoraciones superiores al umbral muestran Google como acción principal cuando existe una URL configurada.

== Installation ==

1. Instala y activa Reseñas WordPress.
2. Abre `Reseñas WordPress > Apariencia y ajustes`.
3. Configura el nombre de la empresa, textos, colores y la URL de Google.
4. Si trabajas con varias ubicaciones, crea las sedes desde `Reseñas WordPress > Sedes`.
5. Crea una página de WordPress e inserta `[resenas_wordpress]`.
6. Publica la página y comparte esa URL con tus clientes.

== Frequently Asked Questions ==

= ¿Qué enlace tengo que enviar al cliente? =

La URL de la página de WordPress donde hayas insertado `[resenas_wordpress]`. El plugin no crea una URL pública paralela.

= ¿Necesito WhatsApp? =

No. WhatsApp es el uso recomendado, pero puedes compartir la misma página por email, SMS, QR, desde tu web o mediante cualquier otro canal.

= ¿Funciona con varias sedes? =

Sí. Cada sede puede tener su propio identificador, URL de Google y email de avisos.

= ¿Los datos se envían a Kairoseth? =

No. El plugin funciona localmente dentro de tu instalación de WordPress y no envía las valoraciones a Kairoseth.

= ¿Puedo automatizar el envío del enlace? =

El core gratuito no incluye automatizaciones específicas. Kairoseth puede desarrollar integraciones con WhatsApp, WooCommerce, CRM, ERP, reservas o APIs como personalización independiente.

== Privacy ==

Reseñas WordPress almacena localmente en la base de datos de WordPress la valoración y, cuando se proporciona, el motivo, comentario, referencia, sede, origen y otros datos operativos asociados al enlace.

La versión actual también puede registrar metadatos técnicos como IP, agente de usuario y URL de la página para contexto operativo y diagnóstico. Antes de utilizar estos datos, el responsable del sitio debe revisar su base jurídica, política de privacidad y plazos de conservación conforme a la normativa aplicable.

El plugin no transmite estas respuestas a Kairoseth ni requiere una cuenta externa para funcionar.

Al pulsar un enlace de Google, el usuario abandona el sitio y accede directamente al servicio externo configurado por el administrador.

== Screenshots ==

1. Formulario de valoración optimizado para móvil.
2. Flujo de feedback para una valoración que requiere seguimiento.
3. Dashboard de Reseñas WordPress.
4. Gestión de sedes.
5. Apariencia y ajustes.

== Changelog ==

= 1.2.0 =
* Nuevo frontend premium y responsive.
* Mejoras de accesibilidad: teclado, foco visible, estados ARIA y soporte de reducción de movimiento.
* Semántica accesible disponible desde el marcado del servidor.
* Nuevo cierre para valoraciones que requieren seguimiento: feedback interno y acceso secundario a Google.
* Dashboard y pantallas de administración con jerarquía visual mejorada.
* Bloque "Cómo funciona" dentro del Dashboard.
* Headers y documentación preparados para WordPress.org.

= 1.1.3 =
* Core funcional validado.
* Soporte para una o múltiples sedes.
* Ciclo completo para valoraciones altas y bajas.
* Compatibilidad con referencias y origen.
