# Guía de uso — Reseñas WordPress

Reseñas WordPress es un plugin gratuito de Kairoseth para solicitar valoraciones, recoger feedback y gestionar una o múltiples sedes desde WordPress.

## 1. Instalación

1. Descarga `dist/resenas-wordpress-1.1.3.zip`.
2. En WordPress entra en **Plugins → Añadir plugin → Subir plugin**.
3. Sube el ZIP y activa **Reseñas WordPress**.
4. En el menú **Reseñas WordPress** configura la marca, textos, colores y enlaces de Google.

## 2. Crear la página de valoración

Crea una página de WordPress y añade:

```text
[resenas_wordpress]
```

El slug de la página es libre. Por ejemplo:

```text
https://empresa.com/resenas/
```

## 3. Negocio con una sola sede

Si el negocio tiene una única sede, puede utilizar la URL sin parámetro `sede`:

```text
https://empresa.com/resenas/?ref=PEDIDO-123
```

Si existe una única sede activa, el plugin la selecciona automáticamente. También se mantiene una URL Google global para instalaciones sin sedes configuradas.

## 4. Negocio con varias sedes

Cada sede se configura desde **Reseñas WordPress → Sedes** con:

- nombre;
- identificador o slug;
- URL de Google Reviews;
- email de avisos opcional;
- estado activo/inactivo.

Ejemplo:

```text
https://empresa.com/resenas/?sede=centro&ref=PEDIDO-123
https://empresa.com/resenas/?sede=norte&ref=PEDIDO-456
```

Cuando hay varias sedes activas, el enlace debe indicar `?sede=` para poder identificar correctamente la ubicación.

## 5. Uso recomendado: WhatsApp

El caso de uso recomendado es enviar el enlace después de completar una compra, servicio, reserva, cita o intervención.

Ejemplo de mensaje:

> Gracias por confiar en nosotros. ¿Nos ayudas valorando tu experiencia? https://empresa.com/resenas/?ref=PEDIDO-123&source=WhatsApp

Para multisede:

> Gracias por confiar en nosotros. ¿Nos ayudas valorando tu experiencia? https://empresa.com/resenas/?sede=centro&ref=PEDIDO-123&source=WhatsApp

WhatsApp no es obligatorio. El mismo enlace puede utilizarse desde web, email, SMS, QR u otros canales.

## 6. Parámetros disponibles

El plugin admite contexto mediante parámetros de URL:

| Parámetro | Uso |
|---|---|
| `sede` | Identificador de la sede |
| `ref` | Referencia externa: pedido, reserva, cita, servicio, etc. |
| `source` | Origen del enlace: WhatsApp, Web, Email, QR, SMS, etc. |
| `customer_id` | Identificador externo opcional del cliente |
| `service` | Tipo de servicio opcional |
| `order_id` | Pedido o transacción opcional |
| `agent` | Técnico, comercial o agente opcional |

Ejemplo completo:

```text
https://empresa.com/resenas/?sede=centro&ref=SV-2026-001&source=WhatsApp&service=instalacion&agent=tecnico-01
```

## 7. Flujo de valoración

1. El cliente abre el enlace.
2. Selecciona entre 1 y 5 estrellas.
3. El registro se guarda en WordPress junto con el contexto disponible.
4. Las valoraciones bajas pueden recoger motivo y comentario interno.
5. Las valoraciones altas facilitan el acceso a la ficha Google configurada.
6. Al finalizar, el flujo vuelve al inicio y queda listo para otra valoración.

## 8. Feedback y seguimiento

Desde **Reseñas WordPress → Feedback** se pueden consultar registros con:

- fecha;
- sede;
- referencia;
- puntuación;
- motivo;
- comentario;
- servicio;
- pedido;
- técnico/comercial;
- origen;
- estado.

## 9. Personalizaciones

El plugin funciona localmente en WordPress y no requiere suscripción.

Para automatizar el envío por WhatsApp, integrarlo con WooCommerce, CRM, ERP, reservas, APIs u otros sistemas, utiliza la página de producto de Kairoseth:

https://kairoseth.com/products/resenas-wordpress
