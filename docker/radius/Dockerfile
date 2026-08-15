FROM freeradius/freeradius-server:3.2.10

COPY docker/radius/sql.template /opt/jaringanku/sql.template
COPY docker/radius/clients.base.conf /opt/jaringanku/clients.base.conf
COPY docker/radius/default /etc/raddb/sites-enabled/default
COPY docker/radius/inner-tunnel /etc/raddb/sites-enabled/inner-tunnel
COPY docker/radius/entrypoint.sh /usr/local/bin/jaringanku-radius-entrypoint

RUN chmod +x /usr/local/bin/jaringanku-radius-entrypoint

ENTRYPOINT ["/usr/local/bin/jaringanku-radius-entrypoint"]
CMD ["freeradius", "-f"]
