/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ["class"],
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      colors: {
        paper: "rgb(var(--color-paper) / <alpha-value>)",
        surface: "rgb(var(--color-surface) / <alpha-value>)",
        "surface-alt": "rgb(var(--color-surface-alt) / <alpha-value>)",
        ink: "rgb(var(--color-ink) / <alpha-value>)",
        line: "rgb(var(--color-line) / <alpha-value>)",
        accent: "rgb(var(--color-accent) / <alpha-value>)",
        teal: {
          DEFAULT: "#0F4C4A",
          50: "#E9F1F0",
          100: "#CFE3E1",
          200: "#9FC6C2",
          300: "#6FA9A3",
          400: "#3F8C84",
          500: "#0F4C4A",
          600: "#0C3E3C",
          700: "#0A3230",
          800: "#072423",
          900: "#051615",
        },
        clay: "#D98E2F",
        moss: "#3A7D5C",
        alert: "#C8443C",
      },
      fontFamily: {
        display: ["Fraunces", "Georgia", "serif"],
        body: ["Inter", "system-ui", "sans-serif"],
        mono: ["JetBrains Mono", "ui-monospace", "monospace"],
      },
      boxShadow: {
        card: "0 1px 2px rgba(15,76,74,0.06), 0 1px 12px rgba(15,76,74,0.05)",
      },
    },
  },
  plugins: [],
};

