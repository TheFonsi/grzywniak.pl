FROM node:22-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends git ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 codex \
    && useradd --uid 10001 --gid 10001 --create-home --shell /usr/sbin/nologin codex \
    && npm install --global @openai/codex

WORKDIR /srv/codex-runner
COPY server.mjs ./server.mjs

EXPOSE 3020
CMD ["node", "server.mjs"]
