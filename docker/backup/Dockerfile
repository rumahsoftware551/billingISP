FROM postgres:18-alpine
COPY docker/backup/backup-loop.sh /usr/local/bin/jaringanku-backup-loop
RUN chmod +x /usr/local/bin/jaringanku-backup-loop
ENTRYPOINT ["/usr/local/bin/jaringanku-backup-loop"]
