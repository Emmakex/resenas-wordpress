# Gestión de Reseñas v1.2.0 — Store Candidate

## Objetivo

Preparar el segundo producto Kairoseth para WordPress.org elevando UX, accesibilidad y presentación sin romper el core funcional validado en v1.1.3.

## Bloque 1 — Frontend premium y accesible

- Valoración 1–5 expuesta como `radiogroup` accesible.
- Navegación por teclado con flechas, Home y End.
- `aria-checked`, `aria-disabled`, `aria-pressed` y estados `aria-live`.
- Movimiento de foco al cambiar entre pasos.
- Label visible para el comentario de feedback.
- Estados seleccionados perceptibles por forma/check además de color.
- Objetivos táctiles de al menos 44 px.
- Foco visible de alto contraste.
- Soporte `prefers-reduced-motion`.
- Diseño responsive optimizado para móvil/WhatsApp.

## Regla de recuperación del servicio

### 1–3 estrellas
1. Se registra la valoración.
2. Se pide motivo/comentario interno.
3. Se guarda el feedback para seguimiento por el área responsable.
4. En la pantalla final se ofrece, de forma secundaria, la opción de compartir también la experiencia en Google.

### 4–5 estrellas
1. Se registra la valoración.
2. Se muestra Google como CTA principal.

La finalidad de 1–3 estrellas es recuperación del servicio y resolución de incidencias, no bloquear la posibilidad de publicar una opinión.

## Pendiente antes de release

- Marcar versión 1.2.0 en PHP/readme.
- Refactor del marcado PHP para que la semántica accesible no dependa solo de JS.
- Admin premium + `assets/admin.css`.
- i18n completa ES/EN.
- Headers y readme WordPress.org.
- Assets de tienda y screenshots.
- Pruebas instalación limpia, upgrade 1.1.3 → 1.2.0, una sede y multisede.
