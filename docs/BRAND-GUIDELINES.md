# Identidad Visual USFQ Galápagos

## Colores principales

| Nombre | Hex | Uso |
|---|---|---|
| Verde Galápagos | `#00A9A8` | Color primario, acentos, botones |
| Azul Pacífico | `#1672A3` | Color secundario, headers, links |
| Negro Corporativo | `#1A1A1A` | Texto principal |
| Gris Texto | `#4A4A4A` | Texto secundario |
| Fondo Claro | `#F8F9FA` | Fondo de tarjetas |

## Gradiente institucional

```css
background: linear-gradient(135deg, #00A9A8 0%, #1672A3 100%);
```

Uso: headers, acentos, barras, badges, bordes superiores de tarjetas.

## Tipografía

**Familia**: [Jost](https://fonts.google.com/specimen/Jost) (Google Fonts)

```html
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;700&display=swap" rel="stylesheet">
```

| Peso | Uso |
|---|---|
| 400 (Regular) | Texto de cuerpo, descripciones |
| 500 (Medium) | Subtítulos, labels |
| 700 (Bold) | Títulos, headers |

## Logos disponibles

| Archivo | Uso |
|---|---|
| `img/LOGO-USFQG-2025.png` | Versión color (gradiente verde→azul), para fondos blancos |
| `img/USGQG-LOGO-NUEVO-BLANCO.png` | Versión blanca, para fondos oscuros |
| `img/USFQG-NEGRO.png` | Versión negra, para fondos claros |

## Aplicación en el tema blanco (index_white.html)

### Estructura visual

- **Fondo**: blanco con orbes degradados sutiles (verde y azul con opacidad baja)
- **Franja superior**: 4px con gradiente institucional en la parte superior de la página
- **Header**: fondo blanco con sombra sutil, logo a la izquierda
- **Tarjetas de eventos**: fondo `#F8F9FA`, borde-izquierdo 4px con gradiente
- **Reloj/fecha**: texto en Negro Corporativo
- **Badges de tipo**: gradiente institucional con texto blanco
- **Hover en tarjetas**: sombra más pronunciada, elevación sutil

### CSS Variables

```css
:root {
  --verde-galapagos: #00A9A8;
  --azul-pacifico: #1672A3;
  --negro-corp: #1A1A1A;
  --gris-texto: #4A4A4A;
  --fondo-card: #F8F9FA;
  --gradiente: linear-gradient(135deg, #00A9A8 0%, #1672A3 100%);
}
```

### Colores de tipo de evento (adaptados a la marca)

```css
/* Colores de tipo de evento para tema blanco */
Académico:    #1672A3 (Azul Pacífico)
Social:       #00A9A8 (Verde Galápagos)
Deportivo:    #E8833A (naranja cálido)
Cultural:     #9B59B6 (púrpura)
Taller:       #2ECC71 (verde esmeralda)
Conferencia:  #34495E (gris oscuro)
Default:      gradiente institucional
```

## Aplicación en el tema oscuro (index.html)

- **Logo**: `img/USGQG-LOGO-NUEVO-BLANCO.png` (versión blanca)
- **Fondo**: gradiente oscuro océano (`#0a0a1a` → `#1a1a2e`)
- **Colores de acento**: tonos cyan/azul oceánicos
- **Fuentes**: Outfit (body) + Playfair Display (títulos)
