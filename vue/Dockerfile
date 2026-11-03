FROM node:24-alpine AS vue_base
RUN command -v yarn >/dev/null || npm install -g yarn

WORKDIR /app

# persistent / runtime deps
RUN apk add --no-cache \
    	fish \
	;

COPY package.json yarn.lock ./
RUN yarn

COPY . .

EXPOSE 3000

# for Development environnement
FROM vue_base AS vue_dev

CMD [ "yarn", "dev", "--port", "3000", "--host", "0.0.0.0" ]

# for Production environnement
FROM vue_base AS vue_prod

ARG VITE_SENTRY_DSN
ARG GOAT_COUNTER_NAMESPACE
ENV VITE_SENTRY_DSN=$VITE_SENTRY_DSN VITE_GOAT_COUNTER_NAMESPACE=$GOAT_COUNTER_NAMESPACE

RUN yarn build-only && mkdir -p /app/dist