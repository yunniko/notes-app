# syntax=docker/dockerfile:1

# --- build: compile the Vue app to static files ---------------------------------------
FROM node:26-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# --- serve: nginx hosts the SPA and forwards /api to PHP-FPM --------------------------
FROM nginx:stable-alpine
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
