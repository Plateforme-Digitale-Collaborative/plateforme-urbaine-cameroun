
FROM node:24-alpine AS docs_base
RUN command -v yarn >/dev/null || npm install -g yarn

WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn
COPY . .
EXPOSE 5000

# for Development environnement
FROM docs_base AS docs_dev

CMD ["yarn", "docs:dev"]

# for Production environnement
FROM docs_base AS docs_prod

RUN yarn docs:build && mkdir -p /app/.vitepress/dist