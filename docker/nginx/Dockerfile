# Pin the MIT-licensed upstream HugeRTE distribution, not Bookly's former
# 3-KB document.execCommand shim. Include skins, icons, models, themes, plugins.
FROM node:22-alpine AS hugerte-assets
RUN npm install --prefix /opt/hugerte-build --ignore-scripts --no-save --no-audit --no-fund hugerte@1.0.15 \
    && test "$(wc -c < /opt/hugerte-build/node_modules/hugerte/hugerte.min.js)" -gt 100000 \
    && test -f /opt/hugerte-build/node_modules/hugerte/plugins/image/plugin.min.js \
    && test -f /opt/hugerte-build/node_modules/hugerte/skins/ui/oxide/skin.min.css

FROM nginx:alpine
COPY --from=hugerte-assets /opt/hugerte-build/node_modules/hugerte/ /usr/share/bookly/hugerte/
