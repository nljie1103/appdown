# AppDown Template Builder 2.0
# Multi-architecture runtime: linux/amd64 + linux/arm64.
# Third-party tools are downloaded/built from their upstream projects:
# - zsign (MIT)
# - APKEditor (Apache-2.0)
# - uber-apk-signer (Apache-2.0)
FROM debian:bookworm-slim AS zsign-build

ARG ZSIGN_COMMIT=e803f870dc686e6161d00d9b22c425b8acdfacee
RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates git g++ gcc make pkg-config libssl-dev \
    && rm -rf /var/lib/apt/lists/*
RUN git clone https://github.com/zhlynn/zsign.git /src/zsign \
    && cd /src/zsign \
    && git checkout "${ZSIGN_COMMIT}" \
    && make -C build/linux VERSION="$(printf %s "$ZSIGN_COMMIT" | cut -c1-12)"

FROM eclipse-temurin:17-jre-jammy

ARG APKEDITOR_VERSION=1.4.9
ARG APKEDITOR_SHA256=a9cd40df818845456be6d696de6110c89edf4b0a0580cb83438ed6b25a366e67
ARG UBER_SIGNER_VERSION=1.3.0

RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates curl python3 imagemagick openssl zip unzip file \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /opt/appdown

RUN curl -fsSL --retry 3 \
      "https://github.com/REAndroid/APKEditor/releases/download/V${APKEDITOR_VERSION}/APKEditor-${APKEDITOR_VERSION}.jar" \
      -o /opt/appdown/APKEditor.jar \
    && echo "${APKEDITOR_SHA256}  /opt/appdown/APKEditor.jar" | sha256sum -c -

RUN curl -fsSL --retry 3 \
      "https://github.com/patrickfav/uber-apk-signer/releases/download/v${UBER_SIGNER_VERSION}/uber-apk-signer-${UBER_SIGNER_VERSION}.jar" \
      -o /opt/appdown/uber-apk-signer.jar \
    && curl -fsSL --retry 3 \
      "https://github.com/patrickfav/uber-apk-signer/releases/download/v${UBER_SIGNER_VERSION}/checksum-sha256.txt" \
      -o /tmp/uber-sha256.txt \
    && UBER_SHA="$(awk '/uber-apk-signer-.*\.jar/{print $1; exit}' /tmp/uber-sha256.txt)" \
    && test -n "${UBER_SHA}" \
    && echo "${UBER_SHA}  /opt/appdown/uber-apk-signer.jar" | sha256sum -c - \
    && rm -f /tmp/uber-sha256.txt

COPY --from=zsign-build /src/zsign/bin/zsign /usr/local/bin/zsign
COPY template_builder.py /opt/appdown/template_builder.py
COPY entrypoint.sh /usr/local/bin/appdown-template-builder

RUN chmod 0755 /usr/local/bin/zsign /usr/local/bin/appdown-template-builder \
    && java -jar /opt/appdown/APKEditor.jar -v \
    && zsign -v || true

WORKDIR /workspace
ENTRYPOINT ["/usr/local/bin/appdown-template-builder"]
