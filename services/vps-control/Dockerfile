FROM node:22-alpine
RUN apk add --no-cache docker-cli
WORKDIR /srv
COPY server.mjs ./server.mjs
COPY auth.mjs ./auth.mjs
COPY feedback.js ./feedback.js
COPY html2canvas.min.js ./html2canvas.min.js
USER root
CMD ["node", "server.mjs"]
