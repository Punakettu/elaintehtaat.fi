// theme.js — color / accent / typography presets for the dark gallery.
// Drives CSS custom properties. Color theme + accent + font pairing are
// independent tweak axes (see app.jsx). All variants stay dark so documentary
// imagery glows; chrome recedes.

window.THEMES = {
  // ── Color direction (neutral temperature) ──────────────────────────────
  color: {
    warm: {
      label: 'Lämmin hiili',
      vars: {
        '--bg': 'oklch(0.165 0.006 60)',
        '--bg-deep': 'oklch(0.108 0.006 60)',
        '--surface': 'oklch(0.205 0.006 60)',
        '--surface-2': 'oklch(0.245 0.007 60)',
        '--line': 'oklch(1 0 0 / 0.10)',
        '--line-strong': 'oklch(1 0 0 / 0.20)',
        '--text': 'oklch(0.945 0.008 75)',
        '--text-muted': 'oklch(0.715 0.010 75)',
        '--text-faint': 'oklch(0.545 0.010 75)',
      },
    },
    cool: {
      label: 'Viileä hiili',
      vars: {
        '--bg': 'oklch(0.165 0.007 255)',
        '--bg-deep': 'oklch(0.108 0.007 255)',
        '--surface': 'oklch(0.205 0.008 255)',
        '--surface-2': 'oklch(0.245 0.009 255)',
        '--line': 'oklch(1 0 0 / 0.10)',
        '--line-strong': 'oklch(1 0 0 / 0.20)',
        '--text': 'oklch(0.945 0.006 255)',
        '--text-muted': 'oklch(0.715 0.008 255)',
        '--text-faint': 'oklch(0.545 0.008 255)',
      },
    },
    ink: {
      label: 'Muste',
      vars: {
        '--bg': 'oklch(0.125 0 0)',
        '--bg-deep': 'oklch(0.075 0 0)',
        '--surface': 'oklch(0.170 0 0)',
        '--surface-2': 'oklch(0.215 0 0)',
        '--line': 'oklch(1 0 0 / 0.10)',
        '--line-strong': 'oklch(1 0 0 / 0.22)',
        '--text': 'oklch(0.955 0 0)',
        '--text-muted': 'oklch(0.715 0 0)',
        '--text-faint': 'oklch(0.535 0 0)',
      },
    },
  },

  // ── Accent (used sparingly: active filters, license tags, links) ────────
  accent: {
    oxblood: {
      label: 'Oksveri',
      vars: {
        '--accent': 'oklch(0.55 0.145 25)',
        '--accent-text': 'oklch(0.72 0.135 28)',
        '--accent-soft': 'oklch(0.55 0.145 25 / 0.16)',
        '--accent-line': 'oklch(0.55 0.145 25 / 0.45)',
      },
    },
    ochre: {
      label: 'Okra',
      vars: {
        '--accent': 'oklch(0.62 0.12 70)',
        '--accent-text': 'oklch(0.78 0.11 75)',
        '--accent-soft': 'oklch(0.62 0.12 70 / 0.16)',
        '--accent-line': 'oklch(0.62 0.12 70 / 0.45)',
      },
    },
    bone: {
      label: 'Ei korostusta',
      vars: {
        '--accent': 'oklch(0.80 0.004 75)',
        '--accent-text': 'oklch(0.88 0.004 75)',
        '--accent-soft': 'oklch(0.80 0.004 75 / 0.14)',
        '--accent-line': 'oklch(0.80 0.004 75 / 0.40)',
      },
    },
  },

  // ── Type pairing: grotesque display + humanist body + mono labels ───────
  fonts: {
    grotesk: {
      label: 'Grotesk',
      vars: {
        '--font-display': "'Space Grotesk', sans-serif",
        '--font-body': "'Hanken Grotesk', sans-serif",
        '--font-mono': "'IBM Plex Mono', monospace",
        '--display-tracking': '-0.02em',
        '--display-weight': '600',
      },
    },
    bricolage: {
      label: 'Bricolage',
      vars: {
        '--font-display': "'Bricolage Grotesque', sans-serif",
        '--font-body': "'Source Sans 3', sans-serif",
        '--font-mono': "'Space Mono', monospace",
        '--display-tracking': '-0.015em',
        '--display-weight': '700',
      },
    },
    archivo: {
      label: 'Arkisto',
      vars: {
        '--font-display': "'Archivo', sans-serif",
        '--font-body': "'Figtree', sans-serif",
        '--font-mono': "'IBM Plex Mono', monospace",
        '--display-tracking': '-0.01em',
        '--display-weight': '700',
      },
    },
  },
};

window.applyTheme = function (colorKey, accentKey, fontKey) {
  const T = window.THEMES;
  const root = document.documentElement;
  const apply = (set) => { if (set) for (const k in set.vars) root.style.setProperty(k, set.vars[k]); };
  apply(T.color[colorKey] || T.color.warm);
  apply(T.accent[accentKey] || T.accent.oxblood);
  apply(T.fonts[fontKey] || T.fonts.grotesk);
};
