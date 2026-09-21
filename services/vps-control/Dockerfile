FROM node:22-alpine
RUN apk add --no-cache docker-cli
WORKDIR /srv
COPY server.mjs ./server.mjs
USER root
CMD ["node", "server.mjs"]
