FROM ckan/ckan-base:2.10

USER root

# ── Bake correct config values into ckan.ini at build time ──────────────
RUN sed -i 's|sqlalchemy.url = .*|sqlalchemy.url = postgresql://ckan:ckan_secret@ckan_db/ckan|' /srv/app/ckan.ini \
 && sed -i 's|solr_url = .*|solr_url = http://ckan-solr:8983/solr/ckan|' /srv/app/ckan.ini \
 && sed -i 's|ckan.redis.url = .*|ckan.redis.url = redis://redis:6379/1|' /srv/app/ckan.ini \
 && sed -i 's|ckan.site_url = .*|ckan.site_url = http://localhost:5000|' /srv/app/ckan.ini \
 && chmod 666 /srv/app/ckan.ini

# Copy the env-mapping startup wrapper (for runtime overrides)
COPY start.sh /srv/app/start_with_env.sh
RUN chmod +x /srv/app/start_with_env.sh

# Fix uwsgi: Alpine package needs explicit plugin loading
# Use --http-socket instead of ambiguous --http
ENV UWSGI_OPTS="--plugin python3 --socket /tmp/uwsgi.sock --wsgi-file /srv/app/wsgi.py --module wsgi:application --http-socket [::]:5000 --master --enable-threads --lazy-apps -p 2 -L -b 32768 --vacuum --harakiri 60"

USER ckan

CMD ["/srv/app/start_with_env.sh"]
