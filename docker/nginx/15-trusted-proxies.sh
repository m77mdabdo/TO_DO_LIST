#!/bin/sh
# When the app sits behind a load balancer or Cloudflare, nginx only sees the proxy's
# address. TRUSTED_PROXIES (comma-separated IPs or CIDRs) lets it take the visitor's
# address from X-Forwarded-For, but only from those proxies, so nobody can fake it.
# The login throttle and the rate limits depend on that address.
set -eu

out=/etc/nginx/conf.d/00-trusted-proxies.conf
: > "$out"

[ -z "${TRUSTED_PROXIES:-}" ] && exit 0

echo "real_ip_header X-Forwarded-For;" >> "$out"
echo "real_ip_recursive on;" >> "$out"
for proxy in $(echo "$TRUSTED_PROXIES" | tr ',' ' '); do
    echo "set_real_ip_from $proxy;" >> "$out"
done
