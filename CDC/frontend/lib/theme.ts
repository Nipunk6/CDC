'use client';

import { createTheme } from '@mui/material/styles';
import { inter } from './fonts';

// Tokens of the design brief (CDC/docs/ui/DESIGN_BRIEF.md). Components take colours, radius and spacing from here.
const INK = '#1F2328';        // text.primary: 15.8:1 on white
const INK_MUTED = '#5A626C';  // text.secondary: 6.2:1 on white, 5.7:1 on the page background
const RULE = '#E3E6EA';       // divider: card borders, table rules, the sidebar edge
const CANVAS = '#F4F5F7';     // background.default: the page behind everything

// IIT ISM Dhanbad brand colors — maroon/crimson + navy blue
const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#7B1113',     // Deep maroon — IIT ISM header
      light: '#A52422',
      dark: '#5A0C0E',
      contrastText: '#fff',
    },
    secondary: {
      main: '#1a237e',     // Navy blue — institutional accent
      light: '#3949ab',
      dark: '#0d1554',
      contrastText: '#fff',
    },
    error: {
      main: '#d32f2f',
    },
    warning: {
      main: '#ed6c02',
    },
    info: {
      main: '#0288d1',
    },
    success: {
      main: '#2e7d32',
    },
    background: {
      default: CANVAS,
      paper: '#ffffff',
    },
    text: {
      primary: INK,
      secondary: INK_MUTED,
    },
    divider: RULE,
  },
  typography: {
    fontFamily: [
      inter.style.fontFamily,
      '"Roboto"',
      '-apple-system',
      'BlinkMacSystemFont',
      '"Segoe UI"',
      'Arial',
      'sans-serif',
    ].join(','),
    h1: {
      fontSize: '2.75rem',
      fontWeight: 700,
      letterSpacing: '-0.02em',
    },
    h2: {
      fontSize: '2.25rem',
      fontWeight: 700,
      letterSpacing: '-0.01em',
    },
    h3: {
      fontSize: '1.75rem',
      fontWeight: 600,
    },
    h4: {
      fontSize: '1.5rem',
      fontWeight: 700,
      letterSpacing: '-0.01em',
    },
    h5: {
      fontSize: '1.25rem',
      fontWeight: 600,
    },
    h6: {
      fontSize: '1rem',
      fontWeight: 600,
    },
  },
  shape: {
    borderRadius: 8,
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 600,
          borderRadius: 8,
          letterSpacing: '0.01em',
        },
        contained: {
          boxShadow: 'none',
          '&:hover': {
            boxShadow: 'none',
          },
        },
      },
    },
    // Flat, bordered surfaces; shadows are kept for things that float (menus, dialogs, drawers).
    MuiCard: {
      styleOverrides: {
        root: {
          boxShadow: 'none',
          border: `1px solid ${RULE}`,
          borderRadius: 12,
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        elevation1: {
          boxShadow: 'none',
          border: `1px solid ${RULE}`,
        },
      },
    },
    MuiAutocomplete: {
      styleOverrides: {
        paper: {
          border: 'none',
          boxShadow: '0px 6px 24px rgba(31,35,40,0.16)',
        },
      },
    },
    MuiDialog: {
      styleOverrides: {
        paper: {
          borderRadius: 12,
        },
      },
    },
    MuiTableCell: {
      styleOverrides: {
        root: {
          fontVariantNumeric: 'tabular-nums',
          borderBottomColor: RULE,
        },
        head: {
          fontSize: '0.8125rem',
          fontWeight: 600,
          color: INK_MUTED,
          backgroundColor: '#ffffff',
        },
      },
    },
    MuiTextField: {
      styleOverrides: {
        root: {
          '& .MuiOutlinedInput-root': {
            borderRadius: 8,
          },
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          boxShadow: 'none',
        },
      },
    },
  },
});

export default theme;

// Brand colours for the public pages (landing, sign-in), which sit outside the portal shell. Gold is the centenary
// accent of the institute header; `goldText` is the same hue dark enough for text on white (6.2:1).
export const brand = {
  maroon: theme.palette.primary.main,
  maroonDark: theme.palette.primary.dark,
  navy: theme.palette.secondary.main,
  gold: '#D4A843',
  goldHover: '#c9982e',
  goldInk: '#3e1a00',
  goldText: '#7A5C0F',
  cream: '#faf7f2',
  footer: '#1a1a2e',
};

