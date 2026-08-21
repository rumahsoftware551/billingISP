FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM nginx:1.29-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=frontend /app/public/build /var/www/html/public/build
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage || true
