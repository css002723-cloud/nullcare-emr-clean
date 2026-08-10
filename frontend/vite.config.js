import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig(({ command }) => ({
  /*
   * Development:
   *   http://localhost:5173/
   *
   * Production:
   *   served from the container root (/) by Apache in Docker
   */
  base: "/",

  build: {
    // Output the frontend build into ./dist inside the frontend workspace
    // so the Docker frontend-builder stage produces /app/dist
    outDir: "dist",

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
         * Application entry point in production.
         */
        navigateFallback: "/index.html",

        runtimeCaching: [
          {
            /*
             * Cache API requests using NetworkFirst.
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
        start_url: "/",

        icons: [
          {
            src: "/icon-192.png",
            sizes: "192x192",
            type: "image/png",
          },
          {
            src: "/icon-512.png",
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
     * Forward React API requests to Laravel while developing locally.
     */
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
}));
