# Campus Connect — Shared Color System

Use these exact hex values in both the Android app and the website so the brand feels identical across platforms.

| Token | Hex | Role |
|---|---|---|
| `primary` | `#243B6B` | Navy — headers, primary buttons, nav bar, app toolbar |
| `primary_dark` | `#182747` | Pressed/hover states, status bar, footer background |
| `accent` | `#FF6B45` | Orange — CTA buttons, highlights, badges |
| `success` | `#2E9E6E` | Green — verified badge, success states, star ratings |
| `background` | `#F7F6F2` | App/page background |
| `surface` | `#FFFFFF` | Cards, sheets, form fields |
| `text_primary` | `#1D1B16` | Headings, body text |
| `text_muted` | `#6B6558` | Captions, secondary text, placeholders |
| `border` | `#E4E1D8` | Dividers, card borders |
| `error` | `#C1432E` | Form errors, destructive actions |

## Website (CSS variables — already in style.css)
```css
:root{
  --primary:#243B6B;
  --primary-dark:#182747;
  --accent:#FF6B45;
  --success:#2E9E6E;
  --background:#F7F6F2;
  --surface:#FFFFFF;
  --text-primary:#1D1B16;
  --text-muted:#6B6558;
  --border:#E4E1D8;
  --error:#C1432E;
}
```

## Android app (res/values/colors.xml)
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="primary">#243B6B</color>
    <color name="primary_dark">#182747</color>
    <color name="accent">#FF6B45</color>
    <color name="success">#2E9E6E</color>
    <color name="background">#F7F6F2</color>
    <color name="surface">#FFFFFF</color>
    <color name="text_primary">#1D1B16</color>
    <color name="text_muted">#6B6558</color>
    <color name="border">#E4E1D8</color>
    <color name="error">#C1432E</color>
</resources>
```

Drop that `colors.xml` straight into `app/src/main/res/values/` in your Android Studio project, then reference colors as `@color/primary`, `@color/accent`, etc. in your layouts — same names, same values as the site.
