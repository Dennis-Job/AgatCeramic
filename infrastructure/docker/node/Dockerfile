FROM node:24.19-bookworm-slim

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        libasound2 \
        libatk-bridge2.0-0 \
        libatk1.0-0 \
        libatspi2.0-0 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libdrm2 \
        libgbm1 \
        libglib2.0-0 \
        libnspr4 \
        libnss3 \
        libpango-1.0-0 \
        libx11-6 \
        libxcb1 \
        libxcomposite1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libxkbcommon0 \
        libxrandr2 \
        fonts-liberation \
        util-linux \
    && rm -rf /var/lib/apt/lists/*

COPY --chmod=755 infrastructure/docker/install-locked-dependencies.sh /usr/local/bin/install-locked-dependencies

# Both lock sets are rebuild inputs because Admin and Client share this image.
COPY frontend/admin/package.json frontend/admin/package-lock.json /opt/agatceramic/dependency-inputs/admin/
COPY frontend/client/package.json frontend/client/package-lock.json /opt/agatceramic/dependency-inputs/client/

WORKDIR /app
