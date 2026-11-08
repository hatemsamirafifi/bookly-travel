# Pinned MIT-licensed upstream HugeRTE distribution (v1.0.15), complete with
# skins, icons, models, themes, and plugins.
FROM nginx:alpine

# Copy the self-hosted official HugeRTE assets into the nginx image
COPY docker/nginx/hugerte/ /usr/share/bookly/hugerte/

# Validate that the official distribution is present and meets size/file expectations
RUN test "$(wc -c < /usr/share/bookly/hugerte/hugerte.min.js)" -gt 100000 \
    && test -f /usr/share/bookly/hugerte/plugins/image/plugin.min.js \
    && test -f /usr/share/bookly/hugerte/skins/ui/oxide/skin.min.css
