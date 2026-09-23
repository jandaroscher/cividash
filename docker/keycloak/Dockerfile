# cividash-keycloak — optional prebuilt Keycloak image with the realm import.
#
# Ships a "civitas" realm template with a cividash-dashboard confidential client
# and an sso-demo user. Secrets (client secret, demo password) are NOT baked in;
# they carry "__PLACEHOLDER__" literals that the entrypoint substitutes from the
# runtime environment at container start.
#
FROM quay.io/keycloak/keycloak:26.0.5

# Build the server at image build time so the container starts with
# `start --optimized`; a runtime build would push the container close to a
# 1 GiB memory limit. The local cache drops the JGroups cluster stack, which a
# single instance never uses.
ENV KC_DB=dev-file \
    KC_CACHE=local
RUN /opt/keycloak/bin/kc.sh build

# Realm template + entrypoint. --chmod keeps the script executable without a
# root USER switch (requires BuildKit).
COPY --chmod=0644 realm-civitas.json /opt/keycloak/realm-civitas.json
COPY --chmod=0755 entrypoint.sh /opt/keycloak/entrypoint.sh

# Runs as the image default uid 1000 (keycloak); the entrypoint only writes
# under /opt/keycloak/data which that user owns.
ENTRYPOINT ["/opt/keycloak/entrypoint.sh"]
