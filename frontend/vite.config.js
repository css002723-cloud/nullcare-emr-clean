import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig(({ command }) => ({
  /*
   * Development:
   *   http://localhost:5173/
   *
   * Production:
   *   https://your-domain.com/app/
   */
  base: command === "build" ? "/app/" : "/",

  build: {
    // Laravel serves the compiled React application from public/app
    outDir: "../public/app",

    // Remove the previous build before creating a new one
    emptyOutDir: true,
  },

  plugins: [
    react(),

    VitePWA({
      registerType: "autoUpdate",
      injectRegister: "auto",

      workbox: {
        // Files that should be precached
        globPatterns: ["**/*.{js,css,html,ico,png,svg,woff2}"],

        /*
         * Laravel/React application entry point in production.
         */
        navigateFallback: "/app/index.html",

        runtimeCaching: [
          {
            /*
             * Cache API requests using NetworkFirst.
             * This allows the application to continue working
             * with cached API responses when connectivity is poor.
             */
            urlPattern: ({ url }) =>
              url.pathname.startsWith("/api/"),

            handler: "NetworkFirst",

            options: {
              cacheName: "nullcare-api-cache",

              networkTimeoutSeconds: 4,

              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
        ],
      },

      /*
       * Progressive Web App configuration
       */
      manifest: {
        name: "NullCare EMR",
        short_name: "NullCare",

        description:
          "Electronic Medical Record system for MUST Teaching Hospital",

        theme_color: "#0F4C4A",
        background_color: "#F7F5F0",

        display: "standalone",

        /*
         * Production application entry point
         */
        start_url: "/app/",

        icons: [
          {
            src: "/app/icon-192.png",
            sizes: "192x192",
            type: "image/png",
          },
          {
            src: "/app/icon-512.png",
            sizes: "512x512",
            type: "image/png",
          },
        ],
      },
    }),
  ],

  /*
   * Vite development server
   */
  server: {
    host: true,
    port: 5173,

    /*
     * Forward React API requests to Laravel
     * while developing locally.
     */
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
}));